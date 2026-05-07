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
        $cacheKey = "dashboard_data_user_{$user->id}_v2_page_{$page}_per_{$perPage}";
        
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addMinutes(5), function () use ($user, $request, $perPage, $page) {
            // 1. Stats - Optimized to use fewer queries
            $stats = [];
            
            if ($user->hasPermissionTo('faculty.view_all')) {
                $stats['total_users'] = \Illuminate\Support\Facades\Cache::remember('total_users_count', now()->addMinutes(10), fn() => User::count());
                $stats['active_users'] = \Illuminate\Support\Facades\Cache::remember('active_users_count', now()->addMinutes(10), fn() => User::where('is_active', true)->count());
                
                $stats['total_memos'] = \Illuminate\Support\Facades\Cache::remember('total_memos_count', now()->addMinutes(5), fn() => Memo::where('status', '!=', 'draft')->count());
                $stats['pending_approval'] = Memo::where('status', 'pending_approval')->count(); // Keep real-time
                $stats['upcoming_deadlines'] = \Illuminate\Support\Facades\Cache::remember('upcoming_deadlines_count', now()->addMinutes(5), fn() => Memo::where('status', 'sent')
                                                    ->where('deadline_at', '>', now())
                                                    ->count());
                
                // Optimized acknowledgment rate calculation
                $stats['acknowledgment_rate'] = \Illuminate\Support\Facades\Cache::remember('global_ack_rate', now()->addMinutes(10), function() {
                    $totalAcks = \App\Models\MemoAcknowledgment::count();
                    if ($totalAcks === 0) return 0;
                    $acknowledgedCount = \App\Models\MemoAcknowledgment::where('is_acknowledged', true)->count();
                    return round(($acknowledgedCount / $totalAcks) * 100);
                });
                
            } else if ($user->hasPermissionTo('faculty.view')) {
                $stats['total_users'] = \Illuminate\Support\Facades\Cache::remember("dept_users_count_{$user->department}", now()->addMinutes(10), fn() => User::where('department', $user->department)->count());
                $stats['active_users'] = \Illuminate\Support\Facades\Cache::remember("dept_active_users_count_{$user->department}", now()->addMinutes(10), fn() => User::where('department', $user->department)
                                            ->where('is_active', true)
                                            ->count());
            }

            if ($user->hasPermissionTo('memo.view_all')) {
                $stats['total_memos'] = \Illuminate\Support\Facades\Cache::remember('total_memos_count_memo_view', now()->addMinutes(5), fn() => Memo::where('status', '!=', 'draft')->count());
                $stats['pending_approval'] = Memo::where('status', 'pending_approval')->count();
            } else if ($user->hasPermissionTo('memo.view')) {
                $stats['total_memos'] = \Illuminate\Support\Facades\Cache::remember("user_total_memos_{$user->id}", now()->addMinutes(5), function() use ($user) {
                    return Memo::where(function($q) use ($user) {
                                    $q->where('recipient_id', $user->id)
                                      ->orWhere('sender_id', $user->id);
                                })
                                ->where('status', '!=', 'draft')
                                ->count();
                });
                $stats['pending_approval'] = Memo::where('sender_id', $user->id)
                                              ->where('status', 'pending_approval')
                                              ->count();
            }

            // 2. Recent Activities - Optimized to fetch only necessary fields and limit
            $logsQuery = UserActivityLog::query();
            
            if (!$user->hasPermissionTo('activity.view_all')) {
                if ($user->hasPermissionTo('activity.view_department')) {
                    // Use a simpler where check if possible
                    $logsQuery->where('actor_department', $user->department);
                } else {
                    $logsQuery->where('actor_id', $user->id);
                }
            }
            
            // Limit to 5 for dashboard to keep it fast
            $recentActivities = $logsQuery->with(['actor:id,first_name,last_name,email,role'])
                                          ->latest()
                                          ->limit(5)
                                          ->get();

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

            // 4. Calendar Events (for mini calendar) - Optimized MongoDB query
            $startDate = now()->startOfMonth();
            $endDate = now()->endOfMonth();
            $userId = (string)$user->id;
            
            // Get event IDs where user is a participant first (avoids expensive orWhereHas)
            $participatingEventIds = \App\Models\CalendarEventParticipant::where('user_id', $userId)
                ->pluck('calendar_event_id')
                ->toArray();
            
            $calendarEvents = \App\Models\CalendarEvent::where(function ($query) use ($userId, $participatingEventIds) {
                    $query->where('created_by', $userId)
                          ->orWhereIn('_id', $participatingEventIds);
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
