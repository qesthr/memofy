<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Memo;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // 1. Stats
        $stats = [];
        
        if ($user->hasPermissionTo('faculty.view_all')) {
            $stats['total_users'] = User::count();
            $stats['active_users'] = User::where('is_active', true)->count();
        } else if ($user->hasPermissionTo('faculty.view')) {
            $stats['total_users'] = User::where('role', 'faculty')->where('department', $user->department)->count();
            $stats['active_users'] = User::where('role', 'faculty')->where('department', $user->department)->where('is_active', true)->count();
        }

        if ($user->hasPermissionTo('memo.view_all')) {
            $stats['total_memos'] = Memo::count();
            $stats['pending_memos'] = Memo::where('status', 'draft')->count();
        } else if ($user->hasPermissionTo('memo.view')) {
            // For general view, count memos where they are sender or recipient or department-wide if authorized
            $stats['total_memos'] = Memo::where(function($q) use ($user) {
                $q->where('sender_id', $user->id)
                  ->orWhere('recipient_id', $user->id);
            })->count();
            $stats['pending_memos'] = Memo::where('sender_id', $user->id)->where('status', 'draft')->count();
        }

        // 2. Recent Activities (Global for admin, or filtered)
        // If admin, show system wide. If normal user, show relevant.
        // Legacy dashboard showed logs based on role.
        $logsQuery = UserActivityLog::with('actor');
        
        if (!$user->hasPermissionTo('activity.view_all')) {
            if ($user->hasPermissionTo('activity.view_department')) {
                // Show logs from people in the same department
                $userIds = User::where('department', $user->department)->pluck('_id');
                $logsQuery->whereIn('actor_id', $userIds);
            } else {
                // Just their own logs
                $logsQuery->where('actor_id', $user->id);
            }
        }
        
        $recentActivities = $logsQuery->latest()->take(5)->get();

        // 3. User Specific Stats
        $roleName = (isset($user->role) && is_object($user->role)) ? $user->role->name : ($user->role ?? '');
        
        if ($roleName === 'secretary') {
            // Received memos (including those directed to their department)
            $deptUserIds = User::where('department_id', $user->department_id)->pluck('id')->toArray();
            
            $userStats = [
                'sent_memos' => Memo::where('sender_id', $user->id)
                                    ->where('is_draft', false)
                                    ->where('status', '!=', 'pending_approval')
                                    ->count(),
                'received_memos' => Memo::whereIn('recipient_id', array_merge($deptUserIds, [$user->id]))
                                        ->where('is_draft', false)
                                        ->where('status', '!=', 'pending_approval')
                                        ->count(),
                'pending_memos' => Memo::where('sender_id', $user->id)
                                        ->where('status', 'pending_approval')
                                        ->count(),
                'draft_memos' => Memo::where('sender_id', $user->id)
                                      ->where('is_draft', true)
                                      ->count(),
            ];
            
            // Sync global stats for secretary view if needed
            $stats['pending_memos'] = $userStats['pending_memos'];
        } else {
            $userStats = [
                'sent_memos' => Memo::where('sender_id', $user->id)->count(),
                'received_memos' => Memo::where('recipient_id', $user->id)->count(),
            ];
        }

        return response()->json([
            'stats' => $stats,
            'user_stats' => $userStats,
            'user' => $user, // Include user data as expected by Dashboard.vue
            'recent_activities' => $recentActivities
        ]);
    }
}
