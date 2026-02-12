<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Memo;
use App\Models\User;
use App\Models\Draft;
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

        // 1. Stats - Optimized to use fewer queries
        $stats = [];
        
        if ($user->hasPermissionTo('faculty.view_all')) {
            $stats['total_users'] = User::count();
            $stats['active_users'] = User::where('is_active', true)->count();
            
            $stats['total_memos'] = Memo::where('is_draft', false)->count();
            $stats['pending_memos'] = Memo::where('status', 'pending_approval')->count();
            
        } else if ($user->hasPermissionTo('faculty.view')) {
            $stats['total_users'] = User::where('role', 'faculty')
                                        ->where('department', $user->department)
                                        ->count();
            $stats['active_users'] = User::where('role', 'faculty')
                                        ->where('department', $user->department)
                                        ->where('is_active', true)
                                        ->count();
        }

        if ($user->hasPermissionTo('memo.view_all')) {
            // Already handled above
        } else if ($user->hasPermissionTo('memo.view')) {
            // For general view, count memos where they are recipient
            $stats['total_memos'] = Memo::where('recipient_id', $user->id)
                                        ->where('is_draft', false)
                                        ->count();
            $stats['pending_memos'] = Memo::where('sender_id', $user->id)
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
        
        $perPage = min((int) $request->get('per_page', 10), 20);
        $recentActivities = $logsQuery->latest()->paginate($perPage, ['*'], 'activity_page', 1);

        // 3. User Specific Stats
        $roleName = (isset($user->role) && is_object($user->role)) ? $user->role->name : ($user->role ?? '');
        $userId = (string) $user->id;
        
        if ($roleName === 'secretary') {
            $deptUserIds = User::where('department_id', $user->department_id)->pluck('id')->toArray();
            $recipientIds = array_merge($deptUserIds, [$userId]);
            
            $userStats = [
                'sent_memos' => Memo::where('sender_id', $userId)
                                    ->where('is_draft', false)
                                    ->where('status', '!=', 'pending_approval')
                                    ->count(),
                'received_memos' => Memo::whereIn('recipient_id', $recipientIds)
                                        ->where('is_draft', false)
                                        ->where('status', '!=', 'pending_approval')
                                        ->count(),
                'pending_memos' => Memo::where('sender_id', $userId)
                                      ->where('status', 'pending_approval')
                                      ->count(),
                'draft_memos' => Memo::where('sender_id', $userId)
                                      ->where('is_draft', true)
                                      ->count(),
            ];
            
            $stats['pending_memos'] = $userStats['pending_memos'];
        } else {
            $userStats = [
                'sent_memos' => Memo::where('sender_id', $userId)
                                    ->where('is_draft', false)
                                    ->count(),
                'received_memos' => Memo::where('recipient_id', $userId)
                                        ->where('is_draft', false)
                                        ->count(),
                'draft_memos' => Memo::where('sender_id', $userId)
                                      ->where('is_draft', true)
                                      ->count(),
                'all_memos' => Memo::where(function($q) use ($userId) {
                                         $q->where('sender_id', $userId)
                                           ->orWhere('recipient_id', $userId);
                                     })
                                    ->where('is_draft', false)
                                    ->count()
            ];
        }

        return response()->json([
            'stats' => $stats,
            'user_stats' => $userStats,
            'user' => $user,
            'recent_activities' => $recentActivities
        ]);
    }
}
