<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Memo;
use App\Models\MemoAcknowledgment;
use App\Models\User;
use App\Models\CalendarEvent;
use App\Models\Draft;
use App\Models\Department;
use App\Models\UserSignature;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;

class AdminMemoController extends Controller
{
    protected $activityLogger;
    protected $notificationService;

    public function __construct(ActivityLogger $activityLogger, NotificationService $notificationService)
    {
        $this->activityLogger = $activityLogger;
        $this->notificationService = $notificationService;
    }

    /**
     * Convert user ID to consistent format for MongoDB comparison
     */
    protected function normalizeUserId($userId)
    {
        if ($userId instanceof ObjectId) {
            return $userId;
        }
        // Try to create ObjectId from string
        if (is_string($userId) && strlen((string)$userId) === 24) {
            try {
                return new ObjectId((string)$userId);
            } catch (\Exception $e) {
                return (string)$userId;
            }
        }
        return (string)$userId;
    }

    /**
     * Get all memos pending approval from secretaries.
     * 
     * PERFORMANCE: Uses eager loading to prevent N+1 queries.
     */
    public function pendingApprovals(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 15), 50);
        
        $query = Memo::with([
            'sender:id,first_name,last_name,email,role,department',
            'recipient:id,first_name,last_name,email,role,department',
            'department:id,name'
        ])
                     ->where('status', 'pending_approval')
                     ->orderBy('created_at', 'desc');

        // Filter by department if not admin with all permissions
        $user = $request->user();
        if (!$user->hasPermissionTo('memo.approve_all')) {
            $query->where('department_id', $user->department_id);
        }

        $memos = $query->paginate($perPage);

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

        // Normalize IDs for comparison
        $userDepartmentId = $this->normalizeUserId($user->department_id);
        $memoDepartmentId = $this->normalizeUserId($memo->department_id);

        // Check department if not super admin
        if (!$user->hasPermissionTo('memo.approve_all') && $memoDepartmentId != $userDepartmentId) {
            return response()->json(['message' => 'Unauthorized to approve memos from other departments'], 403);
        }

        DB::transaction(function () use ($memo, $user, $request) {
            // Determine status based on schedule
            $isScheduled = $memo->scheduled_send_at && strtotime($memo->scheduled_send_at) > time();
            $targetStatus = $isScheduled ? 'scheduled' : 'sent';

            // Update memo status
            $memo->update([
                'status' => $targetStatus,
                'approved_by' => $user->id,
                'approved_at' => now()
            ]);

            // Create acknowledgment records only if NOT scheduled for future
            $recipients = [];
            if ($targetStatus === 'sent') {
                if ($memo->department_id) {
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
                        $recipients[] = $deptUser;
                    }
                } else {
                    if ($memo->recipient_id) {
                        $recipient = User::find($memo->recipient_id);
                        if ($recipient) {
                            MemoAcknowledgment::create([
                                'memo_id' => $memo->id,
                                'recipient_id' => $memo->recipient_id,
                                'is_acknowledged' => false,
                                'sent_at' => now()
                            ]);
                            $recipients[] = $recipient;
                        }
                    }
                }
            }

            // Create or Update calendar event if scheduled
            if ($memo->scheduled_send_at) {
                $this->createCalendarEventForMemo($memo, $user->id);
            }

            // Send notifications
            $sender = User::find($memo->sender_id);
            if ($sender) {
                // Notify memo creator that their memo was approved
                $this->notificationService->notifyMemoApproved($user, $sender, $memo);
                
                // ONLY notify recipients if sent now
                if ($targetStatus === 'sent') {
                    $this->notificationService->notifyMemoRecipients($user, $memo, $recipients);
                }
            }

            // Send notifications to creator and recipients
            $sender = User::find($memo->sender_id);
            if ($sender) {
                // Notify memo creator that their memo was approved
                $this->notificationService->notifyMemoApproved($user, $sender, $memo);
                
                // Notify all recipients about the new memo
                $this->notificationService->notifyMemoRecipients($user, $memo, $recipients);
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

            // Send notification to memo creator
            $sender = User::find($memo->sender_id);
            if ($sender) {
                $this->notificationService->notifyMemoRejected($user, $sender, $memo, $validated['rejection_reason'] ?? null);
            }

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
     * Send reminder for acknowledgment
     */
    public function sendReminder(Request $request, $id)
    {
        $user = $request->user();
        
        // Permission check
        if (!$user->hasPermissionTo('memo.approve') && !$user->hasPermissionTo('memo.approve_all')) {
            return response()->json(['message' => 'Unauthorized to send reminders'], 403);
        }

        $memo = Memo::with(['sender'])->findOrFail($id);

        if ($memo->status !== 'sent') {
            return response()->json(['message' => 'Can only send reminders for sent memos'], 422);
        }

        // Get pending acknowledgments
        $pendingAcknowledgments = MemoAcknowledgment::where('memo_id', $id)
                                                    ->where('is_acknowledged', false)
                                                    ->with('recipient')
                                                    ->get();

        if ($pendingAcknowledgments->isEmpty()) {
            return response()->json(['message' => 'All recipients have acknowledged this memo'], 422);
        }

        $pendingRecipients = $pendingAcknowledgments->pluck('recipient');
        $sender = User::find($memo->sender_id);

        // Send reminders
        $result = $this->notificationService->sendAcknowledgmentReminders($memo, $sender, $pendingRecipients);

        // Log the action
        $this->activityLogger->logUserAction(
            $user,
            'send_memo_reminder',
            "Sent acknowledgment reminder for memo: {$memo->subject}",
            [
                'memo_id' => $memo->id,
                'recipients_reminded' => $result['sent']
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => "Sent {$result['sent']} reminder(s) successfully",
            'stats' => $result
        ]);
    }

    /**
     * Create calendar event for approved memo
     */
    private function createCalendarEventForMemo($memo, $userId)
    {
        $calendarEvent = CalendarEvent::updateOrCreate(
            ['memo_id' => $memo->id],
            [
                'title' => $memo->subject,
                'description' => $memo->message,
                'start' => $memo->scheduled_send_at,
                'end' => $memo->schedule_end_at ?? $memo->scheduled_send_at,
                'all_day' => $memo->all_day_event ?? false,
                'category' => $this->mapPriorityToCategory($memo->priority),
                'created_by' => $userId,
                'status' => $memo->scheduled_send_at && strtotime($memo->scheduled_send_at) > time() ? 'scheduled' : 'sent',
                'source' => 'MEMO'
            ]
        );

        // Clear and add participants
        \App\Models\CalendarEventParticipant::where('calendar_event_id', $calendarEvent->id)->delete();
        
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
            'high' => 'high',
            'medium' => 'standard',
            'low' => 'low'
        ];
        return $mapping[$priority] ?? 'standard';
    }

    /**
     * Get drafts for the authenticated admin user
     * 
     * STRICT FILTERING: Only returns drafts where creatorId matches authenticated user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function drafts(Request $request)
    {
        $user = $request->user();
        $creatorId = $this->normalizeUserId($user->id);
        
        $perPage = min((int) $request->get('per_page', 15), 50);
        
        // STRICT QUERY: Always filter by creatorId
        $query = Draft::where('creatorId', $creatorId)
                      ->orderBy('updatedAt', 'desc');
        
        // Optional filters
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }
        
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }
        
        $drafts = $query->paginate($perPage);
        
        return response()->json($drafts);
    }

    /**
     * Get a single draft by ID for admin
     * 
     * SECURITY: Verifies that the draft belongs to the authenticated admin
     * 
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function showDraft(Request $request, $id)
    {
        $user = $request->user();
        $creatorId = $this->normalizeUserId($user->id);
        
        // STRICT QUERY: Find draft by ID AND creatorId
        $draft = Draft::where('_id', $id)
                      ->where('creatorId', $creatorId)
                      ->first();
        
        if (!$draft) {
            return response()->json([
                'message' => 'Draft not found or access denied'
            ], 404);
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $draft
        ]);
    }

    /**
     * Create a new draft for admin
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeDraft(Request $request)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'subject' => 'sometimes|string|max:255',
            'message' => 'sometimes|string',
            'priority' => 'nullable|in:high,medium,low',
            'recipientIds' => 'nullable|array',
            'departmentId' => 'nullable|string',
            'attachments' => 'nullable|array',
            'signatureId' => 'nullable|string',
            'attachmentPath' => 'nullable|string',
            'scheduledSendAt' => 'nullable|date',
            'scheduleEndAt' => 'nullable|date',
            'allDayEvent' => 'nullable|boolean',
        ]);
        
        $draft = Draft::create([
            'creatorId' => $this->normalizeUserId($user->id),
            'subject' => $validated['subject'] ?? null,
            'message' => $validated['message'] ?? null,
            'priority' => $validated['priority'] ?? 'medium',
            'recipientIds' => $validated['recipientIds'] ?? [],
            'departmentId' => $validated['departmentId'] ?? null,
            'attachments' => $validated['attachments'] ?? [],
            'signatureId' => $validated['signatureId'] ?? null,
            'attachmentPath' => $validated['attachmentPath'] ?? null,
            'scheduledSendAt' => $validated['scheduledSendAt'] ?? null,
            'scheduleEndAt' => $validated['scheduleEndAt'] ?? null,
            'allDayEvent' => $validated['allDayEvent'] ?? false,
            'status' => 'draft',
            'metadata' => [
                'editCount' => 0,
                'lastEditedAt' => now()->toIso8601String()
            ],
        ]);
        
        $this->activityLogger->logUserAction(
            $user,
            'admin_create_draft',
            "Admin created draft: {$draft->subject}",
            $this->activityLogger->extractRequestInfo($request)
        );
        
        return response()->json([
            'status' => 'success',
            'message' => 'Draft saved successfully',
            'data' => $draft
        ], 201);
    }

    /**
     * Update a draft for admin
     * 
     * SECURITY: Verifies that the draft belongs to the authenticated admin
     * 
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateDraft(Request $request, $id)
    {
        $user = $request->user();
        $creatorId = $this->normalizeUserId($user->id);
        
        // STRICT QUERY: Find draft by ID AND creatorId
        $draft = Draft::where('_id', $id)
                      ->where('creatorId', $creatorId)
                      ->first();
        
        if (!$draft) {
            return response()->json([
                'message' => 'Draft not found or access denied'
            ], 404);
        }
        
        $validated = $request->validate([
            'subject' => 'sometimes|string|max:255',
            'message' => 'sometimes|string',
            'priority' => 'sometimes|in:high,medium,low',
            'recipientIds' => 'nullable|array',
            'departmentId' => 'nullable|string',
            'attachments' => 'nullable|array',
            'signatureId' => 'nullable|string',
            'attachmentPath' => 'nullable|string',
            'scheduledSendAt' => 'nullable|date',
            'scheduleEndAt' => 'nullable|date',
            'allDayEvent' => 'nullable|boolean',
        ]);
        
        $draft->fill($validated);
        $draft->touchMetadata();
        $draft->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Draft updated successfully',
            'data' => $draft
        ]);
    }

    /**
     * Delete a draft for admin
     * 
     * SECURITY: Verifies that the draft belongs to the authenticated admin
     * 
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyDraft(Request $request, $id)
    {
        $user = $request->user();
        $creatorId = $this->normalizeUserId($user->id);
        
        // STRICT QUERY: Find draft by ID AND creatorId
        $draft = Draft::where('_id', $id)
                      ->where('creatorId', $creatorId)
                      ->first();
        
        if (!$draft) {
            return response()->json([
                'message' => 'Draft not found or access denied'
            ], 404);
        }
        
        $subject = $draft->subject;
        $draft->delete();
        
        $this->activityLogger->logUserAction(
            $user,
            'admin_delete_draft',
            "Admin deleted draft: {$subject}",
            $this->activityLogger->extractRequestInfo($request)
        );
        
        return response()->json([
            'status' => 'success',
            'message' => 'Draft deleted successfully'
        ]);
    }

    /**
     * Convert draft to memo and send directly (Admin can bypass approval)
     * 
     * SECURITY: Verifies that the draft belongs to the authenticated admin
     * 
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendDraftAsMemo(Request $request, $id)
    {
        $user = $request->user();
        $creatorId = $this->normalizeUserId($user->id);
        
        // STRICT QUERY: Find draft by ID AND creatorId
        $draft = Draft::where('_id', $id)
                      ->where('creatorId', $creatorId)
                      ->first();
        
        if (!$draft) {
            return response()->json([
                'message' => 'Draft not found or access denied'
            ], 404);
        }
        
        $validated = $request->validate([
            'recipient_ids' => 'required_without:department_id|array',
            'recipient_ids.*' => 'exists:users,id',
            'department_id' => 'required_without:recipient_ids|exists:departments,id',
            'send_directly' => 'nullable|boolean', // Admin can send directly without approval
        ]);
        
        // Get memo data from draft
        $memoData = $draft->toMemoData();
        $memoData['created_by'] = $user->id;
        $memoData['sender_id'] = $user->id;
        $memoData['is_draft'] = false;
        
        // Admin can send directly or submit for approval
        $sendDirectly = $validated['send_directly'] ?? true;
        $memoData['status'] = $sendDirectly ? 'sent' : 'pending_approval';
        
        // Override with request data
        if (isset($validated['recipient_ids'])) {
            $memoData['recipient_ids'] = $validated['recipient_ids'];
            $memoData['recipient_id'] = count($validated['recipient_ids']) === 1 
                ? $validated['recipient_ids'][0] 
                : null;
        }
        
        if (isset($validated['department_id'])) {
            $memoData['department_id'] = $validated['department_id'];
        }
        
        // Create the memo
        $memo = Memo::create($memoData);
        
        // If sent directly, create acknowledgment records
        if ($memoData['status'] === 'sent') {
            if ($memo->department_id) {
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
            }
        }
        
        // Delete the draft after successful conversion
        $draft->delete();
        
        $this->activityLogger->logUserAction(
            $user,
            'admin_send_draft_as_memo',
            "Admin sent draft as memo: {$memo->subject}",
            $this->activityLogger->extractRequestInfo($request)
        );
        
        return response()->json([
            'status' => 'success',
            'message' => $sendDirectly ? 'Memo sent successfully' : 'Memo submitted for approval',
            'data' => $memo
        ], 201);
    }

    /**
     * Get draft statistics for admin dashboard
     * 
     * STRICT FILTERING: Only counts drafts where creatorId matches authenticated admin
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function draftStats(Request $request)
    {
        $user = $request->user();
        $creatorId = $this->normalizeUserId($user->id);
        
        // STRICT QUERIES: All filtered by creatorId
        $stats = [
            'total' => Draft::where('creatorId', $creatorId)->count(),
            'draft' => Draft::where('creatorId', $creatorId)->where('status', 'draft')->count(),
            'auto_saved' => Draft::where('creatorId', $creatorId)->where('status', 'auto_saved')->count(),
            'recent' => Draft::where('creatorId', $creatorId)
                           ->where('updatedAt', '>=', now()->subDays(7))
                           ->count(),
        ];
        
        return response()->json($stats);
    }
}
