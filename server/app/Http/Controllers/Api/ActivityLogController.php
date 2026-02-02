<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = UserActivityLog::with('actor');

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

        $logs = $query->latest()->paginate(20);

        return response()->json($logs);
    }

    public function show($id)
    {
        $log = UserActivityLog::with('actor')->findOrFail($id);
        return response()->json($log);
    }
}
