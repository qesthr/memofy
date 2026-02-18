<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ActivityLogController extends Controller
{
    /**
     * Export activity logs as PDF
     */
    public function exportPdf(Request $request)
    {
        $user = $request->user();
        $query = UserActivityLog::with(['actor:_id,first_name,last_name,email,role']);

        // RBAC Scoping (Same as index)
        if (!$user->hasPermissionTo('activity.view_all')) {
            if ($user->hasPermissionTo('activity.view_department')) {
                $userIds = \App\Models\User::where('department', $user->department)->pluck('_id');
                $query->whereIn('actor_id', $userIds);
            } else {
                $query->where('actor_id', $user->id);
            }
        }

        // Filters (Same as index)
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

        $logs = $query->latest()->get();

        $data = [
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'generated_by' => $user->name,
            'tracking_number' => 'LOG-' . now()->format('Ymd') . '-' . strtoupper(substr(md5($user->id . now()), 0, 8)),
            'logs' => $logs,
            'filters' => [
                'search' => $request->search,
                'action_type' => $request->action_type,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ],
            'logo_base64' => base64_encode(file_get_contents(public_path('images/memofy-logo.png'))),
        ];

        $pdf = Pdf::loadView('reports.activity_logs_pdf', $data)
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

        return $pdf->download('activity-logs-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Display activity logs with pagination and eager loading.
     * 
     * PERFORMANCE: Uses paginate() with eager loading to prevent N+1 queries.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = min((int) $request->get('per_page', 20), 100);
        $query = UserActivityLog::with(['actor:_id,first_name,last_name,email,role']);

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
        $log = UserActivityLog::with(['actor:_id,first_name,last_name,email,role,department'])
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
