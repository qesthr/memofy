<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Memo;
use App\Models\UserActivityLog;
use App\Models\Department;
use Illuminate\Http\Request;
// use Illuminate\Support\Facades\DB; // Removed DB facade
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

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

    private function getOverviewStats($user, $startDate)
    {
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $totalMemos = Memo::count();
        $totalMemosThisPeriod = Memo::where('created_at', '>=', $startDate)->count();
        $totalActivities = UserActivityLog::where('created_at', '>=', $startDate)->count();

        $memosByStatus = Memo::select('status')
            ->get()
            ->groupBy('status')
            ->map
            ->count()
            ->toArray();

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
        $activeUsersThisPeriod = UserActivityLog::where('created_at', '>=', $startDate)
            ->distinct('actor_id')
            ->count(); // MongoDB distinct count

        $topActiveUsers = UserActivityLog::where('created_at', '>=', $startDate)
            ->get(['actor_id'])
            ->groupBy('actor_id')
            ->map(function ($events, $actorId) {
                return [
                    'actor_id' => $actorId,
                    'count' => $events->count()
                ];
            })
            ->sortByDesc('count')
            ->take(5)
            ->map(function ($stat) {
                 $user = User::find($stat['actor_id']);
                 if (!$user) return null;
                 return [
                    'id' => $user->_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'activity_count' => $stat['count'],
                 ];
            })
            ->filter()
            ->values();

        $usersByDepartment = User::get(['department'])
            ->groupBy('department')
            ->map
            ->count()
            ->toArray();

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
        $draftMemos = Memo::where('status', 'draft')->count();
        $sentMemos = Memo::where('status', 'sent')->count();
        $readMemos = Memo::where('status', 'read')->count();

        $byPriority = Memo::select('priority')->get()
            ->groupBy('priority')
            ->map->count()
            ->toArray();

        return [
            'total' => $totalMemos,
            'this_period' => $memosThisPeriod,
            'by_status' => [
                'draft' => $draftMemos,
                'sent' => $sentMemos,
                'read' => $readMemos,
            ],
            'by_priority' => $byPriority,
        ];
    }

    private function getActivityStats($user, $startDate)
    {
        $totalActivities = UserActivityLog::where('created_at', '>=', $startDate)->count();

        $activitiesByType = UserActivityLog::select('action')->where('created_at', '>=', $startDate)->get()
            ->groupBy('action')
            ->map->count()
            ->sortDesc()
            ->toArray();

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
                        'id' => $item->actor->_id,
                        'name' => $item->actor->name,
                        'email' => $item->actor->email,
                        'avatar' => $item->actor->avatar,
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

        foreach ($departments as $dept) {
            $departmentData[$dept->name] = [
                'total_users' => User::where('department', $dept->name)->count(),
                'total_memos' => Memo::where('department', $dept->name)->count(),
                'memos_this_period' => Memo::where('department', $dept->name)
                    ->where('created_at', '>=', $startDate)
                    ->count(),
            ];
        }

        return $departmentData;
    }

    private function getUserActivityTimeline($user, $startDate)
    {
        $timeline = [];
        $currentDate = clone $startDate;

        while ($currentDate->lte(Carbon::now())) {
            $dateStr = $currentDate->format('Y-m-d');
            $count = UserActivityLog::whereDate('created_at', $dateStr)->count();
            $timeline[] = [
                'date' => $dateStr,
                'label' => $currentDate->format('M d'),
                'count' => $count,
            ];
            $currentDate->addDay();
        }

        return $timeline;
    }

    private function getMemoStatusDistribution($user, $startDate)
    {
        $distribution = Memo::select('status')->get()
            ->groupBy('status')
            ->map->count()
            ->toArray();

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
        $daily = [];
        $currentDate = clone $startDate;

        while ($currentDate->lte(Carbon::now())) {
            $dateStr = $currentDate->format('Y-m-d');
            $count = Memo::whereDate('created_at', $dateStr)->count();
            $daily[] = [
                'date' => $dateStr,
                'label' => $currentDate->format('M d'),
                'count' => $count,
            ];
            $currentDate->addDay();
        }

        return $daily;
    }
}
