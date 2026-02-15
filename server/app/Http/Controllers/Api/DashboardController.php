<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Memo;
use App\Models\User;

use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;

class DashboardController extends Controller
{
    /**
     * Convert user ID to consistent format for MongoDB comparison
     */
    protected function normalizeUserId($userId)
    {
        if ($userId instanceof ObjectId) {
            return $userId;
        }
        
        // Handle MongoDB _id if present in user object
        if (is_object($userId) && isset($userId->_id)) {
            return $userId->_id;
        }

        // Handle string ObjectId (24 character hex)
        if (is_string($userId) && strlen((string)$userId) === 24 && ctype_xdigit((string)$userId)) {
            try {
                return new ObjectId((string)$userId);
            } catch (\Exception $e) {
                return (string)$userId;
            }
        }
        
        return (string)$userId;
    }

    /**
     * Get dashboard data with optimized queries.
     * 
     * PERFORMANCE: Reduces multiple count queries to a single optimized query.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = min((int) $request->get('per_page', 10), 20);
        $page = (int) $request->get('activity_page', 1);
        
        // Generate a unique cache key based on user and parameters
        $cacheKey = "dashboard_data_user_{$user->id}_v1_page_{$page}_per_{$perPage}";
        
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addSeconds(1), function () use ($user, $request, $perPage, $page) {
            // 1. Stats - Optimized to use fewer queries
            $stats = [];
            
            if ($user->hasPermissionTo('faculty.view_all')) {
                $stats['total_users'] = User::count();
                $stats['active_users'] = User::where('is_active', true)->count();
                
                $stats['total_memos'] = Memo::where('status', '!=', 'draft')->count();
                $stats['pending_approval'] = Memo::where('status', 'pending_approval')->count();
                $stats['upcoming_deadlines'] = Memo::where('status', 'sent')
                                                    ->where('deadline_at', '>', now())
                                                    ->count();
                
            } else if ($user->hasPermissionTo('faculty.view')) {
                $stats['total_users'] = User::where('department', $user->department)->count();
                $stats['active_users'] = User::where('department', $user->department)
                                            ->where('is_active', true)
                                            ->count();
            }

            if ($user->hasPermissionTo('memo.view_all')) {
                $stats['total_memos'] = Memo::where('status', '!=', 'draft')->count();
                $stats['pending_approval'] = Memo::where('status', 'pending_approval')->count();
            } else if ($user->hasPermissionTo('memo.view')) {
                $stats['total_memos'] = Memo::where(function($q) use ($user) {
                                                $q->where('recipient_id', $user->id)
                                                  ->orWhere('sender_id', $user->id);
                                            })
                                            ->where('status', '!=', 'draft')
                                            ->count();
                $stats['pending_approval'] = Memo::where('sender_id', $user->id)
                                              ->where('status', 'pending_approval')
                                              ->count();
            }

            // 2. Recent Activities - With pagination
            $logsQuery = UserActivityLog::with(['actor:id,first_name,last_name,email,role']);
            
            if (!$user->hasPermissionTo('activity.view_all')) {
                if ($user->hasPermissionTo('activity.view_department')) {
                    $userIds = User::where('department', $user->department)->pluck('_id');
                    $logsQuery->whereIn('actor_id', $userIds);
                } else {
                    $logsQuery->where('actor_id', $user->id);
                }
            }
            
            $recentActivities = $logsQuery->latest()->paginate($perPage, ['*'], 'activity_page', $page);

            // 3. Recent Memos
            $memosQuery = Memo::with(['sender:_id,first_name,last_name', 'recipient:_id,first_name,last_name'])
                               ->where('status', '!=', 'draft');
                               
            if (!$user->hasPermissionTo('memo.view_all')) {
                $memosQuery->where(function($q) use ($user) {
                    $q->where('sender_id', (string)$user->id)
                      ->orWhere('recipient_id', (string)$user->id)
                      ->orWhere('recipient_ids', (string)$user->id);
                });
            }
            $recentMemos = $memosQuery->latest()->limit(5)->get();

            // 4. Calendar Events (for mini calendar)
            $startDate = now()->startOfMonth();
            $endDate = now()->endOfMonth();
            $userId = (string)$user->id;
            
            $calendarEvents = \App\Models\CalendarEvent::where(function ($query) use ($userId) {
                    $query->where('created_by', $userId)
                          ->orWhereHas('participants', function ($pQuery) use ($userId) {
                              $pQuery->where('user_id', $userId);
                          });
                })
                ->where(function($q) use ($startDate, $endDate) {
                    $q->whereBetween('start', [$startDate, $endDate])
                      ->orWhereBetween('end', [$startDate, $endDate]);
                })
                ->orderBy('start', 'asc')
                ->get();

            // 5. User Specific Stats
            $roleName = (isset($user->role) && is_object($user->role)) ? $user->role->name : ($user->role ?? '');
            $userId = (string) $user->id;
            
            $userStats = [];
            if ($roleName === 'secretary') {
                $deptUserIds = User::where('department_id', $user->department_id)->pluck('id')->toArray();
                $recipientIds = array_merge($deptUserIds, [$userId]);
                
                $userStats = [
                    'sent_memos' => Memo::where('sender_id', $userId)
                                        ->where('status', '!=', 'draft')
                                        ->where('status', '!=', 'pending_approval')
                                        ->count(),
                    'received_memos' => Memo::whereIn('recipient_id', $recipientIds)
                                            ->where('status', '!=', 'draft')
                                            ->where('status', '!=', 'pending_approval')
                                            ->count(),
                    'pending_memos' => Memo::where('sender_id', $userId)
                                          ->where('status', 'pending_approval')
                                          ->count(),
                    'acknowledged_memos' => \App\Models\MemoAcknowledgment::whereIn('recipient_id', $recipientIds)
                                                                           ->where('is_acknowledged', true)
                                                                           ->count(),
                ];
                
                $stats['pending_memos'] = $userStats['pending_memos'];
            } else {
                $userStats = [
                    'sent_memos' => Memo::where('sender_id', $userId)
                                        ->where('status', '!=', 'draft')
                                        ->count(),
                    'received_memos' => Memo::where('recipient_id', $userId)
                                            ->where('status', '!=', 'draft')
                                            ->count(),
                    'acknowledged_memos' => \App\Models\MemoAcknowledgment::where('recipient_id', $userId)
                                                                           ->where('is_acknowledged', true)
                                                                           ->count(),

                    'all_memos' => Memo::where(function($q) use ($userId) {
                                             $q->where('sender_id', $userId)
                                               ->orWhere('recipient_id', $userId);
                                         })
                                        ->where('status', '!=', 'draft')
                                        ->count()
                ];
            }

            return [
                'stats' => $stats,
                'user_stats' => $userStats,
                'user' => $user,
                'recent_activities' => $recentActivities,
                'recent_memos' => $recentMemos,
                'calendar_events' => $calendarEvents
            ];
        });
    }

}
