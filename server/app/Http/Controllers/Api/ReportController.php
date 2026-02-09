<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Memo;
use App\Models\UserActivityLog;
use App\Models\Department;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
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
        $startDate = Carbon::now()->subDays($period);

        $reports = [];

        $reports['overview'] = $this->getOverviewStats($user, $startDate);
        $reports['users'] = $this->getUserStats($user, $startDate);
        $reports['memos'] = $this->getMemoStats($user, $startDate);
        $reports['activity'] = $this->getActivityStats($user, $startDate);
        $reports['departments'] = $this->getDepartmentStats($user, $startDate);
        $reports['userActivityTimeline'] = $this->getUserActivityTimeline($user, $startDate);
        $reports['memoStatusDistribution'] = $this->getMemoStatusDistribution($user, $startDate);
        $reports['dailyMemos'] = $this->getDailyMemos($user, $startDate);

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
            'overview' => $this->getOverviewStats($user, $startDate),
            'users' => $this->getUserStats($user, $startDate),
            'memos' => $this->getMemoStats($user, $startDate),
            'activity' => $this->getActivityStats($user, $startDate),
            'departments' => $this->getDepartmentStats($user, $startDate),
            'memoStatusDistribution' => $this->getMemoStatusDistribution($user, $startDate),
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
        $font = null; // Use default
        $size = 9;
        $color = [100/255, 116/255, 139/255]; // #64748b - footer color
        
        // DomPDF Canvas: text(x, y, text, font, size, color, word_space, char_space, angle)
        // The numbers below are approximate for A4 Landscape (842pt wide)
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
            'overview' => $this->getOverviewStats($user, $startDate),
            'users' => $this->getUserStats($user, $startDate),
            'memos' => $this->getMemoStats($user, $startDate),
            'activity' => $this->getActivityStats($user, $startDate),
            'departments' => $this->getDepartmentStats($user, $startDate),
        ];

        // For simplicity, return JSON that client can convert to CSV/Excel
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Excel export data prepared'
        ]);
    }

    private function getOverviewStats($user, $startDate)
    {
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $totalMemos = Memo::count();
        $totalMemosThisPeriod = Memo::where('created_at', '>=', $startDate)->count();
        $totalActivities = UserActivityLog::where('created_at', '>=', $startDate)->count();

        // Optimized aggregation for memos by status
        $rawStatusStats = Memo::raw(function ($collection) {
            return $collection->aggregate([
                ['$group' => ['_id' => '$status', 'count' => ['$sum' => 1]]]
            ]);
        });
        
        $memosByStatus = [];
        foreach ($rawStatusStats as $stat) {
            if (isset($stat['_id'])) {
                $memosByStatus[$stat['_id']] = $stat['count'];
            }
        }

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'total_memos' => $totalMemos,
            'memos_this_period' => $totalMemosThisPeriod,
            'total_activities' => $totalActivities,
            'memos_by_status' => $memosByStatus,
        ];
    }

    private function getUserStats($user, $startDate)
    {
        $newUsersThisPeriod = User::where('created_at', '>=', $startDate)->count();

        // Optimized active users count (actors in logs)
        $activeUsersThisPeriod = UserActivityLog::where('created_at', '>=', $startDate)
            ->distinct('actor_id')
            ->count();

        // Optimized top active users using aggregation
        $rawTopUsers = UserActivityLog::raw(function ($collection) use ($startDate) {
            return $collection->aggregate([
                ['$match' => ['created_at' => ['$gte' => new \MongoDB\BSON\UTCDateTime($startDate->timestamp * 1000)]]],
                ['$group' => ['_id' => '$actor_id', 'count' => ['$sum' => 1]]],
                ['$sort' => ['count' => -1]],
                ['$limit' => 5]
            ]);
        });

        $topActiveUsers = [];
        foreach ($rawTopUsers as $stat) {
            $user = User::find($stat['_id']);
            if ($user) {
                $topActiveUsers[] = [
                    'id' => $user->_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'activity_count' => $stat['count'],
                ];
            }
        }

        // Optimized users by department using aggregation
        $rawDeptStats = User::raw(function ($collection) {
            return $collection->aggregate([
                ['$group' => ['_id' => '$department', 'count' => ['$sum' => 1]]]
            ]);
        });
        
        $usersByDepartment = [];
        foreach ($rawDeptStats as $stat) {
             if (isset($stat['_id'])) {
                $usersByDepartment[$stat['_id']] = $stat['count'];
             }
        }

        return [
            'new_users' => $newUsersThisPeriod,
            'active_users' => $activeUsersThisPeriod,
            'top_active_users' => $topActiveUsers,
            'users_by_department' => $usersByDepartment,
        ];
    }

    private function getMemoStats($user, $startDate)
    {
        $totalMemos = Memo::count();
        $memosThisPeriod = Memo::where('created_at', '>=', $startDate)->count();
        
        // Single aggregation for status counts
        $statusCounts = Memo::raw(function ($collection) {
            return $collection->aggregate([
                ['$group' => ['_id' => '$status', 'count' => ['$sum' => 1]]]
            ]);
        });
        
        $counts = ['draft' => 0, 'sent' => 0, 'read' => 0];
        foreach ($statusCounts as $stat) {
            if (isset($stat['_id']) && isset($counts[$stat['_id']])) {
                $counts[$stat['_id']] = $stat['count'];
            }
        }

        // Optimized priority distribution
        $priorityCounts = Memo::raw(function ($collection) {
            return $collection->aggregate([
                ['$group' => ['_id' => '$priority', 'count' => ['$sum' => 1]]]
            ]);
        });
        
        $byPriority = [];
        foreach ($priorityCounts as $stat) {
            if (isset($stat['_id'])) {
                $byPriority[$stat['_id']] = $stat['count'];
            }
        }

        return [
            'total' => $totalMemos,
            'this_period' => $memosThisPeriod,
            'by_status' => $counts,
            'by_priority' => $byPriority,
        ];
    }

    private function getActivityStats($user, $startDate)
    {
        $totalActivities = UserActivityLog::where('created_at', '>=', $startDate)->count();

        // Optimized aggregation for action types
        $rawActionStats = UserActivityLog::raw(function ($collection) use ($startDate) {
            return $collection->aggregate([
                ['$match' => ['created_at' => ['$gte' => new \MongoDB\BSON\UTCDateTime($startDate->timestamp * 1000)]]],
                ['$group' => ['_id' => '$action', 'count' => ['$sum' => 1]]],
                ['$sort' => ['count' => -1]]
            ]);
        });

        $activitiesByType = [];
        foreach ($rawActionStats as $stat) {
            if (isset($stat['_id'])) {
                $activitiesByType[$stat['_id']] = $stat['count'];
            }
        }

        $recentActivities = UserActivityLog::with('actor:id,name,email,avatar')
            ->where('created_at', '>=', $startDate)
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->_id,
                    'action' => $item->action,
                    'description' => $item->description,
                    'actor' => [
                        'id' => $item->actor->_id ?? null,
                        'name' => $item->actor->name ?? 'Unknown',
                        'email' => $item->actor->email ?? '',
                        'avatar' => $item->actor->avatar ?? '',
                    ],
                    'created_at' => $item->created_at,
                ];
            });

        return [
            'total' => $totalActivities,
            'by_type' => $activitiesByType,
            'recent' => $recentActivities,
        ];
    }

    private function getDepartmentStats($user, $startDate)
    {
        $departments = Department::all();
        $departmentData = [];
        
        // 1. Get user counts per department
        $userCountsRaw = User::raw(function($c) {
            return $c->aggregate([['$group' => ['_id' => '$department', 'count' => ['$sum' => 1]]]]);
        });
        $userCounts = [];
        foreach($userCountsRaw as $r) { if(isset($r['_id'])) $userCounts[$r['_id']] = $r['count']; }

        // 2. Get total memos per department
        $memoCountsRaw = Memo::raw(function($c) {
            return $c->aggregate([['$group' => ['_id' => '$department', 'count' => ['$sum' => 1]]]]);
        });
        $memoCounts = [];
        foreach($memoCountsRaw as $r) { if(isset($r['_id'])) $memoCounts[$r['_id']] = $r['count']; }

        // 3. Get recent memos per department
        $recentMemoCountsRaw = Memo::raw(function($c) use ($startDate) {
            return $c->aggregate([
                ['$match' => ['created_at' => ['$gte' => new \MongoDB\BSON\UTCDateTime($startDate->timestamp * 1000)]]],
                ['$group' => ['_id' => '$department', 'count' => ['$sum' => 1]]]
            ]);
        });
        $recentMemoCounts = [];
        foreach($recentMemoCountsRaw as $r) { if(isset($r['_id'])) $recentMemoCounts[$r['_id']] = $r['count']; }

        foreach ($departments as $dept) {
            $deptName = $dept->name;
            $departmentData[$deptName] = [
                'total_users' => $userCounts[$deptName] ?? 0,
                'total_memos' => $memoCounts[$deptName] ?? 0,
                'memos_this_period' => $recentMemoCounts[$deptName] ?? 0,
            ];
        }

        return $departmentData;
    }

    private function getUserActivityTimeline($user, $startDate)
    {
        // Use MongoDB aggregation to group by date
        $timelineRaw = UserActivityLog::raw(function($collection) use ($startDate) {
            return $collection->aggregate([
                ['$match' => ['created_at' => ['$gte' => new \MongoDB\BSON\UTCDateTime($startDate->timestamp * 1000)]]],
                ['$group' => [
                    '_id' => ['$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$created_at']],
                    'count' => ['$sum' => 1]
                ]],
                ['$sort' => ['_id' => 1]]
            ]);
        });
        
        $timelineMap = [];
        foreach ($timelineRaw as $item) {
            $timelineMap[$item['_id']] = $item['count'];
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
    }

    private function getMemoStatusDistribution($user, $startDate)
    {
        // Already optimized in getOverviewStats, but specific method requested here
        $distributionRaw = Memo::raw(function ($collection) {
            return $collection->aggregate([
                ['$group' => ['_id' => '$status', 'count' => ['$sum' => 1]]]
            ]);
        });
        
        $distribution = [];
        foreach ($distributionRaw as $stat) {
            if (isset($stat['_id'])) {
                $distribution[$stat['_id']] = $stat['count'];
            }
        }

        return [
            'labels' => array_keys($distribution),
            'data' => array_values($distribution),
            'colors' => [
                'draft' => '#fbbf24',
                'sent' => '#3b82f6',
                'read' => '#10b981',
                'archived' => '#6b7280',
            ],
        ];
    }

    private function getDailyMemos($user, $startDate)
    {
        // Use MongoDB aggregation to group by date
        $dailyRaw = Memo::raw(function($collection) use ($startDate) {
            return $collection->aggregate([
                ['$match' => ['created_at' => ['$gte' => new \MongoDB\BSON\UTCDateTime($startDate->timestamp * 1000)]]],
                ['$group' => [
                    '_id' => ['$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$created_at']],
                    'count' => ['$sum' => 1]
                ]],
                ['$sort' => ['_id' => 1]]
            ]);
        });
        
        $dailyMap = [];
        foreach ($dailyRaw as $item) {
            $dailyMap[$item['_id']] = $item['count'];
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
    }
}
