<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Display activity logs with pagination and eager loading.
     * 
     * PERFORMANCE: Uses paginate() with eager loading to prevent N+1 queries.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = min((int) $request->get('per_page', 20), 100);
        $query = UserActivityLog::with(['actor:id,first_name,last_name,email,role']);

        // RBAC Scoping
        if (!$user->hasPermissionTo('activity.view_all')) {
            if ($user->hasPermissionTo('activity.view_department')) {
                // Show logs from people in the same department
                $userIds = \App\Models\User::where('department', $user->department)->pluck('_id');
                $query->whereIn('actor_id', $userIds);
            } else {
                // Just their own logs
                $query->where('actor_id', $user->id);
            }
        }

        // Filters
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('description', 'like', "%{$request->search}%")
                  ->orWhere('action', 'like', "%{$request->search}%")
                  ->orWhere('actor_email', 'like', "%{$request->search}%");
            });
        }

        if ($request->action_type) {
            $query->where('action', $request->action_type);
        }

        if ($request->user_id) {
            $query->where('actor_id', $request->user_id);
        }

        if ($request->start_date && $request->end_date) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        $logs = $query->latest()->paginate($perPage);

        return response()->json($logs);
    }

    public function show($id)
    {
        $log = UserActivityLog::with(['actor:id,first_name,last_name,email,role,department'])
                    ->findOrFail($id);
        $user = auth()->user();

        // RBAC Check for single log
        if (!$user->hasPermissionTo('activity.view_all')) {
            if ($user->hasPermissionTo('activity.view_department')) {
                $targetUser = \App\Models\User::find($log->actor_id);
                if (!$targetUser || $targetUser->department !== $user->department) {
                    return response()->json(['message' => 'Unauthorized'], 403);
                }
            } else if ($log->actor_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        return response()->json($log);
    }
}
