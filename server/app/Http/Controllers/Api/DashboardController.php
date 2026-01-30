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
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'total_memos' => Memo::count(),
            'pending_memos' => Memo::where('status', 'draft')->count(), // Or pending approval logic if exists
        ];

        // 2. Recent Activities (Global for admin, or filtered)
        // If admin, show system wide. If normal user, show relevant.
        // Legacy dashboard showed logs based on role.
        $logsQuery = UserActivityLog::with('actor');
        
        if ($user->role !== 'admin') {
            // Non-admins see pertinent logs or just their own? 
            // Legacy usually showed department logs.
            if ($user->department) {
                // Show logs from same department? 
                // Or just show nothing for now to be safe.
                $logsQuery->where('actor_id', $user->id); 
            }
        }
        
        $recentActivities = $logsQuery->latest()->take(5)->get();

        // 3. User Specific Stats
        $userStats = [
            'sent_memos' => Memo::where('sender_id', $user->id)->count(),
            'received_memos' => Memo::where('recipient_id', $user->id)->count(),
        ];

        return response()->json([
            'stats' => $stats,
            'user_stats' => $userStats,
            'recent_activities' => $recentActivities
        ]);
    }
}
