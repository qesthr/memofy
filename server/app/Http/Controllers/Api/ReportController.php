<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Memo;
use App\Models\UserActivityLog;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use MongoDB\BSON\UTCDateTime;

class ReportController extends Controller
{
    /**
     * Cache duration in seconds (5 minutes for reports)
     */
    private const CACHE_TTL = 300;

    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized - User not authenticated'], 401);
        }

        if (!$user->hasPermissionTo('reports.view') && !$user->hasPermissionTo('reports.view_analytics')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $period = $request->get('period', '30');
        $cacheKey = "reports_{$period}";

        // Try to get from cache first
        $reports = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($period) {
            $startDate = Carbon::now()->subDays($period);

            // Run all aggregations in parallel-friendly manner
            return [
                'overview' => $this->getOverviewStats($startDate),
                'users' => $this->getUserStats($startDate),
                'memos' => $this->getMemoStats($startDate),
                'activity' => $this->getActivityStats($startDate),
                'departments' => $this->getDepartmentStats($startDate),
                'userActivityTimeline' => $this->getUserActivityTimeline($startDate),
                'memoStatusDistribution' => $this->getMemoStatusDistribution(),
                'dailyMemos' => $this->getDailyMemos($startDate),
            ];
        });

        return response()->json($reports);
    }

    /**
     * Export PDF Report with header, footer, watermark, and digital signature
     */
    public function exportPdf(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized - User not authenticated'], 401);
        }

        if (!$user->hasPermissionTo('reports.export')) {
            return response()->json(['message' => 'Unauthorized - Export permission required'], 403);
        }

        $period = $request->get('period', '30');
        $startDate = Carbon::now()->subDays($period);

        $data = [
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'generated_by' => $user->name,
            'tracking_number' => 'MEMOFY-' . now()->format('Ymd') . '-' . strtoupper(substr(md5($user->id . now()), 0, 8)),
            'period' => $period,
            'period_label' => $period == 7 ? 'Last 7 days' : ($period == 365 ? 'This Year' : 'Last 30 days'),
            'overview' => $this->getOverviewStats($startDate),
            'users' => $this->getUserStats($startDate),
            'memos' => $this->getMemoStats($startDate),
            'activity' => $this->getActivityStats($startDate),
            'departments' => $this->getDepartmentStats($startDate),
            'memoStatusDistribution' => $this->getMemoStatusDistribution(),
            'logo_base64' => base64_encode(file_get_contents(public_path('images/memofy-logo.png'))),
        ];

        $pdf = Pdf::loadView('reports.pdf', $data)
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'defaultFont' => 'sans-serif',
            ]);

        $pdf->render();
        $canvas = $pdf->getCanvas();
        $font = null;
        $size = 9;
        $color = [100/255, 116/255, 139/255];
        
        $canvas->page_text(740, 545, "Page {PAGE_NUM} / {PAGE_COUNT}", $font, $size, $color);

        return $pdf->download('memofy-report-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Export Excel Report
     */
    public function exportExcel(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized - User not authenticated'], 401);
        }

        if (!$user->hasPermissionTo('reports.export')) {
            return response()->json(['message' => 'Unauthorized - Export permission required'], 403);
        }

        $period = $request->get('period', '30');
        $startDate = Carbon::now()->subDays($period);

        $data = [
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'generated_by' => $user->name,
            'tracking_number' => 'MEMOFY-' . now()->format('Ymd') . '-' . strtoupper(substr(md5($user->id . now()), 0, 8)),
            'period' => $period,
            'overview' => $this->getOverviewStats($startDate),
            'users' => $this->getUserStats($startDate),
            'memos' => $this->getMemoStats($startDate),
            'activity' => $this->getActivityStats($startDate),
            'departments' => $this->getDepartmentStats($startDate),
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Excel export data prepared'
        ]);
    }

    /**
     * Optimized overview stats with single aggregation
     */
    private function getOverviewStats($startDate)
    {
        $cacheKey = 'overview_stats_' . $startDate->format('Ymd');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($startDate) {
            // Simple counts
            $totalUsers = User::count();
            $activeUsers = User::where('is_active', true)->count();
            $totalMemos = Memo::count();
            $memosThisPeriod = Memo::where('created_at', '>=', $startDate)->count();
            $totalActivities = UserActivityLog::where('created_at', '>=', $startDate)->count();

            // Memo status distribution
            $statusRaw = Memo::raw(function ($collection) {
                return $collection->aggregate([
                    ['$group' => ['_id' => '$status', 'count' => ['$sum' => 1]]]
                ]);
            });

            $memosByStatus = [];
            foreach ($statusRaw as $stat) {
                if (isset($stat->_id)) {
                    $memosByStatus[$stat->_id] = $stat->count;
                }
            }

            return [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'total_memos' => $totalMemos,
                'memos_this_period' => $memosThisPeriod,
                'total_activities' => $totalActivities,
                'memos_by_status' => $memosByStatus,
            ];
        });
    }

    /**
     * Optimized user stats with batch loading
     */
    private function getUserStats($startDate)
    {
        $cacheKey = 'user_stats_' . $startDate->format('Ymd');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($startDate) {
            $newUsersThisPeriod = User::where('created_at', '>=', $startDate)->count();

            // Active users count
            $activeUsersThisPeriod = UserActivityLog::where('created_at', '>=', $startDate)
                ->distinct('actor_id')
                ->count();

            // Top active users
            $topUsersRaw = UserActivityLog::raw(function ($collection) use ($startDate) {
                return $collection->aggregate([
                    ['$match' => ['created_at' => ['$gte' => new UTCDateTime($startDate->timestamp * 1000)]]],
                    ['$group' => ['_id' => '$actor_id', 'count' => ['$sum' => 1]]],
                    ['$sort' => ['count' => -1]],
                    ['$limit' => 5]
                ]);
            });

            // Collect user IDs
            $userIds = [];
            foreach ($topUsersRaw as $stat) {
                if ($stat->_id) {
                    $userIds[] = (string)$stat->_id;
                }
            }

            // Batch load users
            $users = User::whereIn('_id', $userIds)->get()->keyBy('_id');

            $topActiveUsers = [];
            foreach ($topUsersRaw as $stat) {
                $u = $users->get((string)$stat->_id);
                if ($u) {
                    $name = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
                    $topActiveUsers[] = [
                        'id' => (string)$u->_id,
                        'name' => $name ?: $u->email,
                        'email' => $u->email,
                        'avatar' => $u->profile_picture ?? '',
                        'activity_count' => $stat->count,
                    ];
                }
            }

            // Users by department
            $deptStats = User::raw(function ($collection) {
                return $collection->aggregate([
                    ['$group' => ['_id' => '$department', 'count' => ['$sum' => 1]]]
                ]);
            });
            
            $usersByDepartment = [];
            foreach ($deptStats as $stat) {
                if (isset($stat->_id) && $stat->_id) {
                    $usersByDepartment[$stat->_id] = $stat->count;
                }
            }

            return [
                'new_users' => $newUsersThisPeriod,
                'active_users' => $activeUsersThisPeriod,
                'top_active_users' => $topActiveUsers,
                'users_by_department' => $usersByDepartment,
            ];
        });
    }

    /**
     * Optimized memo stats
     */
    private function getMemoStats($startDate)
    {
        $cacheKey = 'memo_stats_' . $startDate->format('Ymd');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($startDate) {
            $total = Memo::count();
            $thisPeriod = Memo::where('created_at', '>=', $startDate)->count();

            // Status distribution
            $statusRaw = Memo::raw(function ($collection) {
                return $collection->aggregate([
                    ['$group' => ['_id' => '$status', 'count' => ['$sum' => 1]]]
                ]);
            });

            $byStatus = [];
            foreach ($statusRaw as $s) {
                if (isset($s->_id)) {
                    $byStatus[$s->_id] = $s->count;
                }
            }

            // Priority distribution
            $priorityRaw = Memo::raw(function ($collection) {
                return $collection->aggregate([
                    ['$group' => ['_id' => '$priority', 'count' => ['$sum' => 1]]]
                ]);
            });

            $byPriority = [];
            foreach ($priorityRaw as $s) {
                if (isset($s->_id)) {
                    $byPriority[$s->_id] = $s->count;
                }
            }

            return [
                'total' => $total,
                'this_period' => $thisPeriod,
                'by_status' => $byStatus,
                'by_priority' => $byPriority,
            ];
        });
    }

    /**
     * Optimized activity stats
     */
    private function getActivityStats($startDate)
    {
        $cacheKey = 'activity_stats_' . $startDate->format('Ymd');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($startDate) {
            $total = UserActivityLog::where('created_at', '>=', $startDate)->count();

            // Get action types
            $byTypeRaw = UserActivityLog::raw(function ($collection) use ($startDate) {
                return $collection->aggregate([
                    ['$match' => ['created_at' => ['$gte' => new UTCDateTime($startDate->timestamp * 1000)]]],
                    ['$group' => ['_id' => '$action', 'count' => ['$sum' => 1]]],
                    ['$sort' => ['count' => -1]]
                ]);
            });

            $byType = [];
            foreach ($byTypeRaw as $s) {
                if (isset($s->_id)) {
                    $byType[$s->_id] = $s->count;
                }
            }

            // Get recent activities with actor info
            $recentLogs = UserActivityLog::with('actor')
                ->where('created_at', '>=', $startDate)
                ->latest()
                ->take(10)
                ->get()
                ->map(function ($item) {
                    $actor = $item->actor;
                    return [
                        'id' => (string)$item->_id,
                        'action' => $item->action,
                        'description' => $item->description,
                        'actor' => [
                            'id' => $actor ? (string)$actor->_id : null,
                            'full_name' => $actor ? trim(($actor->first_name ?? '') . ' ' . ($actor->last_name ?? '')) : 'System',
                            'email' => $actor->email ?? '',
                            'avatar' => $actor->profile_picture ?? '',
                        ],
                        'created_at' => $item->created_at->toIso8601String(),
                    ];
                })->values()->toArray();

            return [
                'total' => $total,
                'by_type' => $byType,
                'recent_logs' => $recentLogs,
            ];
        });
    }

    /**
     * Optimized department stats
     */
    private function getDepartmentStats($startDate)
    {
        $cacheKey = 'dept_stats_' . $startDate->format('Ymd');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($startDate) {
            $departments = Department::all()->pluck('name')->toArray();

            // Single aggregation for user counts
            $userCounts = User::raw(function ($collection) {
                return $collection->aggregate([
                    ['$group' => ['_id' => '$department', 'count' => ['$sum' => 1]]]
                ]);
            })->pluck('count', '_id')->toArray();

            // Single aggregation for memo counts
            $memoCounts = Memo::raw(function ($collection) {
                return $collection->aggregate([
                    ['$group' => ['_id' => '$department', 'count' => ['$sum' => 1]]]
                ]);
            })->pluck('count', '_id')->toArray();

            // Single aggregation for recent memos
            $recentMemoCounts = Memo::raw(function ($collection) use ($startDate) {
                return $collection->aggregate([
                    ['$match' => ['created_at' => ['$gte' => new UTCDateTime($startDate->timestamp * 1000)]]],
                    ['$group' => ['_id' => '$department', 'count' => ['$sum' => 1]]]
                ]);
            })->pluck('count', '_id')->toArray();

            $departmentData = [];
            foreach ($departments as $deptName) {
                $departmentData[$deptName] = [
                    'total_users' => $userCounts[$deptName] ?? 0,
                    'total_memos' => $memoCounts[$deptName] ?? 0,
                    'memos_this_period' => $recentMemoCounts[$deptName] ?? 0,
                ];
            }

            return $departmentData;
        });
    }

    /**
     * Optimized user activity timeline
     */
    private function getUserActivityTimeline($startDate)
    {
        $cacheKey = 'activity_timeline_' . $startDate->format('Ymd');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($startDate) {
            $timelineRaw = UserActivityLog::raw(function ($collection) use ($startDate) {
                return $collection->aggregate([
                    ['$match' => ['created_at' => ['$gte' => new UTCDateTime($startDate->timestamp * 1000)]]],
                    [
                        '$group' => [
                            '_id' => ['$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$created_at']],
                            'count' => ['$sum' => 1]
                        ]
                    ],
                    ['$sort' => ['_id' => 1]]
                ]);
            });

            $timelineMap = [];
            foreach ($timelineRaw as $item) {
                $timelineMap[$item->_id] = $item->count;
            }

            // Fill in missing dates
            $timeline = [];
            $currentDate = clone $startDate;
            $now = Carbon::now();

            while ($currentDate->lte($now)) {
                $dateStr = $currentDate->format('Y-m-d');
                $timeline[] = [
                    'date' => $dateStr,
                    'label' => $currentDate->format('M d'),
                    'count' => $timelineMap[$dateStr] ?? 0,
                ];
                $currentDate->addDay();
            }

            return $timeline;
        });
    }

    /**
     * Optimized memo status distribution
     */
    private function getMemoStatusDistribution()
    {
        $cacheKey = 'memo_status_dist';

        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $raw = Memo::raw(function ($collection) {
                return $collection->aggregate([
                    ['$group' => ['_id' => '$status', 'count' => ['$sum' => 1]]]
                ]);
            });

            $distribution = [];
            foreach ($raw as $item) {
                if (isset($item->_id)) {
                    $distribution[$item->_id] = $item->count;
                }
            }

            return [
                'labels' => array_keys($distribution),
                'data' => array_values($distribution),
                'colors' => [
                    'sent' => '#3b82f6',
                    'read' => '#10b981',
                    'archived' => '#6b7280',
                    'pending_approval' => '#f59e0b',
                    'draft' => '#6b7280',
                ],
            ];
        });
    }

    /**
     * Optimized daily memos
     */
    private function getDailyMemos($startDate)
    {
        $cacheKey = 'daily_memos_' . $startDate->format('Ymd');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($startDate) {
            $dailyRaw = Memo::raw(function ($collection) use ($startDate) {
                return $collection->aggregate([
                    ['$match' => ['created_at' => ['$gte' => new UTCDateTime($startDate->timestamp * 1000)]]],
                    [
                        '$group' => [
                            '_id' => ['$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$created_at']],
                            'count' => ['$sum' => 1]
                        ]
                    ],
                    ['$sort' => ['_id' => 1]]
                ]);
            });

            $dailyMap = [];
            foreach ($dailyRaw as $item) {
                $dailyMap[$item->_id] = $item->count;
            }

            $daily = [];
            $currentDate = clone $startDate;
            $now = Carbon::now();

            while ($currentDate->lte($now)) {
                $dateStr = $currentDate->format('Y-m-d');
                $daily[] = [
                    'date' => $dateStr,
                    'label' => $currentDate->format('M d'),
                    'count' => $dailyMap[$dateStr] ?? 0,
                ];
                $currentDate->addDay();
            }

            return $daily;
        });
    }
}
