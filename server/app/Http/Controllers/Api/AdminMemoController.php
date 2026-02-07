<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Memo;
use App\Models\MemoAcknowledgment;
use App\Models\User;
use App\Models\CalendarEvent;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminMemoController extends Controller
{
    protected $activityLogger;

    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }

    /**
     * Get all memos pending approval from secretaries
     */
    public function pendingApprovals(Request $request)
    {
        $query = Memo::with(['sender', 'recipient'])
                     ->where('status', 'pending_approval')
                     ->orderBy('created_at', 'desc');

        // Filter by department if not admin with all permissions
        $user = $request->user();
        if (!$user->hasPermissionTo('memo.approve_all')) {
            $query->where('department_id', $user->department_id);
        }

        $memos = $query->paginate(15);

        return response()->json($memos);
    }

    /**
     * Approve a pending memo (Admin action)
     */
    public function approve(Request $request, $id)
    {
        $user = $request->user();
        
        // Permission check
        if (!$user->hasPermissionTo('memo.approve') && !$user->hasPermissionTo('memo.approve_all')) {
            return response()->json(['message' => 'Unauthorized to approve memos'], 403);
        }

        $memo = Memo::findOrFail($id);

        if ($memo->status !== 'pending_approval') {
            return response()->json(['message' => 'This memo is not pending approval'], 422);
        }

        // Check department if not super admin
        if (!$user->hasPermissionTo('memo.approve_all') && $memo->department_id !== $user->department_id) {
            return response()->json(['message' => 'Unauthorized to approve memos from other departments'], 403);
        }

        DB::transaction(function () use ($memo, $user, $request) {
            // Update memo status
            $memo->update([
                'status' => 'sent',
                'approved_by' => $user->id,
                'approved_at' => now()
            ]);

            // Create acknowledgment records for all recipients
            if ($memo->department_id) {
                // Department-wide memo - create acknowledgments for all department members
                $departmentUsers = User::where('department_id', $memo->department_id)
                                        ->where('id', '!=', $memo->sender_id)
                                        ->get();
                
                foreach ($departmentUsers as $deptUser) {
                    MemoAcknowledgment::create([
                        'memo_id' => $memo->id,
                        'recipient_id' => $deptUser->id,
                        'is_acknowledged' => false,
                        'sent_at' => now()
                    ]);
                }
            } else {
                // Individual memo
                if ($memo->recipient_id) {
                    MemoAcknowledgment::create([
                        'memo_id' => $memo->id,
                        'recipient_id' => $memo->recipient_id,
                        'is_acknowledged' => false,
                        'sent_at' => now()
                    ]);
                }
            }

            // Create calendar event if scheduled
            if ($memo->scheduled_send_at) {
                $this->createCalendarEventForMemo($memo, $user->id);
            }

            // Log the approval
            $this->activityLogger->logUserAction(
                $user,
                'approve_memo',
                "Approved memo: {$memo->subject}",
                $this->activityLogger->extractRequestInfo($request)
            );
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Memo approved and sent to recipients',
            'data' => $memo->load(['sender', 'recipient'])
        ]);
    }

    /**
     * Reject a pending memo (Admin action)
     */
    public function reject(Request $request, $id)
    {
        $user = $request->user();
        
        // Permission check
        if (!$user->hasPermissionTo('memo.approve') && !$user->hasPermissionTo('memo.approve_all')) {
            return response()->json(['message' => 'Unauthorized to reject memos'], 403);
        }

        $memo = Memo::findOrFail($id);

        if ($memo->status !== 'pending_approval') {
            return response()->json(['message' => 'This memo is not pending approval'], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => 'nullable|string'
        ]);

        DB::transaction(function () use ($memo, $user, $validated) {
            // Update memo status to rejected
            $memo->update([
                'status' => 'rejected',
                'rejected_by' => $user->id,
                'rejected_at' => now(),
                'rejection_reason' => $validated['rejection_reason'] ?? null
            ]);

            // Log the rejection
            $this->activityLogger->logUserAction(
                $user,
                'reject_memo',
                "Rejected memo: {$memo->subject}. Reason: " . ($validated['rejection_reason'] ?? 'No reason provided'),
                ['memo_id' => $memo->id, 'rejection_reason' => $validated['rejection_reason'] ?? null]
            );
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Memo rejected',
            'data' => $memo
        ]);
    }

    /**
     * Get acknowledgment statistics for a memo
     */
    public function acknowledgmentStats(Request $request, $id)
    {
        $user = $request->user();
        
        $memo = Memo::with(['sender', 'recipient', 'acknowledgments.recipient'])->findOrFail($id);

        // Check access
        if ($user->role !== 'admin' && $memo->sender_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $totalAcknowledgments = $memo->acknowledgments->count();
        $acknowledgedCount = $memo->acknowledgments->where('is_acknowledged', true)->count();
        $pendingCount = $totalAcknowledgments - $acknowledgedCount;
        $percentage = $totalAcknowledgments > 0 ? round(($acknowledgedCount / $totalAcknowledgments) * 100) : 0;

        // Group by acknowledgment status
        $acknowledgedBy = $memo->acknowledgments
                              ->where('is_acknowledged', true)
                              ->map(function ($ack) {
                                  return [
                                      'recipient' => $ack->recipient,
                                      'acknowledged_at' => $ack->acknowledged_at
                                  ];
                              });

        $pendingBy = $memo->acknowledgments
                         ->where('is_acknowledged', false)
                         ->map(function ($ack) {
                             return [
                                 'recipient' => $ack->recipient,
                                 'sent_at' => $ack->sent_at
                             ];
                         });

        return response()->json([
            'memo' => $memo,
            'stats' => [
                'total' => $totalAcknowledgments,
                'acknowledged' => $acknowledgedCount,
                'pending' => $pendingCount,
                'percentage' => $percentage
            ],
            'acknowledged_by' => $acknowledgedBy,
            'pending_by' => $pendingBy
        ]);
    }

    /**
     * Create calendar event for approved memo
     */
    private function createCalendarEventForMemo($memo, $userId)
    {
        $calendarEvent = CalendarEvent::create([
            'title' => $memo->subject,
            'description' => $memo->message,
            'start' => $memo->scheduled_send_at,
            'end' => $memo->schedule_end_at ?? $memo->scheduled_send_at,
            'all_day' => $memo->all_day_event ?? false,
            'category' => $this->mapPriorityToCategory($memo->priority),
            'memo_id' => $memo->id,
            'created_by' => $userId,
            'status' => 'scheduled',
            'source' => 'MEMO'
        ]);

        // Add participants
        \App\Models\CalendarEventParticipant::create([
            'calendar_event_id' => $calendarEvent->id,
            'user_id' => $memo->sender_id,
            'status' => 'accepted'
        ]);

        if ($memo->recipient_id) {
            \App\Models\CalendarEventParticipant::create([
                'calendar_event_id' => $calendarEvent->id,
                'user_id' => $memo->recipient_id,
                'status' => 'pending'
            ]);
        }

        return $calendarEvent;
    }

    private function mapPriorityToCategory($priority)
    {
        $mapping = [
            'urgent' => 'urgent',
            'high' => 'high',
            'normal' => 'standard',
            'low' => 'low'
        ];
        return $mapping[$priority] ?? 'standard';
    }
}
