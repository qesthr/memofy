<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Memo;
use App\Models\MemoAcknowledgment;
use App\Models\RollbackLog;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;


use App\Models\Department;

class MemoController extends Controller
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
     * Display a listing of memos with pagination and eager loading.
     * 
     * PERFORMANCE: Uses paginate() with eager loading to prevent N+1 queries.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = min((int) $request->get('per_page', 15), 50);
        
        // Eager load with consistent column selections
        $query = Memo::with([
            'sender:_id,id,first_name,last_name,email,role,department_id,department',
            'recipient:_id,id,first_name,last_name,email,role,department_id,department',
            'department:_id,id,name'
        ]);

        // Determine target IDs for filtering (only for non-admins)
        $targetIds = [];
        $isAdmin = $user->role === 'admin';
        
        if (!$isAdmin) {
            $selfId = $this->normalizeUserId($user->id);
            $selfStringId = (string) $user->id;
            $targetIds = array_unique([$selfId, $selfStringId], SORT_REGULAR);
        }

        // Scope: Sent, Received, Drafts, or All
        if ($request->scope === 'sent') {
            if (!$isAdmin) {
                $query->whereIn('sender_id', $targetIds);
            }
            $query->whereIn('status', ['sent', 'archived']); // Include archived
        } elseif ($request->scope === 'received') {
            if (!$isAdmin) {
                // Get memo IDs from MemoAcknowledgment records for this user
                $memoIdsFromAcknowledgments = MemoAcknowledgment::where('recipient_id', $user->id)
                                                                 ->pluck('memo_id')
                                                                 ->toArray();
                
                $query->where(function ($q) use ($targetIds, $memoIdsFromAcknowledgments) {
                    $q->whereIn('recipient_id', $targetIds)  // Direct recipient
                      ->orWhereIn('_id', $memoIdsFromAcknowledgments); // Via acknowledgment record
                })
                ->whereNotIn('sender_id', $targetIds); // Exclude memos sent by self
            }
            $query->whereIn('status', ['sent', 'read', 'acknowledged', 'archived']);

        } elseif ($request->scope === 'pending') {
            if (!$isAdmin) {
                $query->whereIn('sender_id', $targetIds);
            }
            $query->where('status', 'pending_approval');
        } else {
            // Default: All (Received + Sent + Drafts) - EXCLUDING ONLY PENDING
            // Get memo IDs from MemoAcknowledgment records for this user
            $memoIdsFromAcknowledgments = [];
            if (!$isAdmin) {
                $memoIdsFromAcknowledgments = MemoAcknowledgment::where('recipient_id', $user->id)
                                                                 ->pluck('memo_id')
                                                                 ->toArray();
            }
            
            $query->where(function ($q) use ($targetIds, $isAdmin, $memoIdsFromAcknowledgments) {
                if ($isAdmin) {
                    $q->where('status', '!=', 'pending_approval');
                } else {
                    // Regular users: restricted to self
                    // Received/Sent memos (not pending)
                    $q->where(function ($sq) use ($targetIds, $memoIdsFromAcknowledgments) {
                        $sq->where('status', '!=', 'pending_approval')
                           ->where(function ($ssq) use ($targetIds, $memoIdsFromAcknowledgments) {
                               $ssq->whereIn('recipient_id', $targetIds)
                                   ->orWhereIn('sender_id', $targetIds)
                                   ->orWhereIn('_id', $memoIdsFromAcknowledgments);
                           });
                    });
                    
                    // Drafts (created by or sent by user)

                }
            });
        }

        // Search
        if ($request->search) {
            $query->where('subject', 'like', "%{$request->search}%");
        }

        // Priority sorting: Low (0), Medium (1), High (2)
        // Sort by priority first (Low to High), then by created_at
        $sortOrder = $request->get('sort', 'desc');
        $query->orderBy('priority', 'asc')
              ->orderBy('created_at', $sortOrder);

        $result = $query->paginate($perPage);

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'recipient_id' => 'nullable|exists:users,_id',
            'recipient_ids' => 'nullable|array',
            'recipient_ids.*' => 'exists:users,_id',
            'department_id' => 'nullable|exists:departments,_id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'priority' => 'required|in:high,medium,low',
            'attachments' => 'nullable|array',

            'scheduled_send_at' => 'nullable|date',
            'schedule_end_at' => 'nullable|date',
            'deadline_at' => 'nullable|date',
            'all_day_event' => 'nullable|boolean',
            'attachment_path' => 'nullable|string'
        ]);

        $memos = [];
        $userIds = [];

        if ($request->department_id) {
            $userIds = \App\Models\User::where('department_id', $request->department_id)->pluck('id')->toArray();
            if (empty($userIds)) {
                return response()->json(['message' => 'No users found in this department'], 422);
            }
        } elseif (!empty($request->recipient_ids)) {
            $userIds = $request->recipient_ids;
        } elseif ($request->recipient_id) {
            $userIds = [$request->recipient_id];
        } else {
             return response()->json(['message' => 'No recipients specified'], 422);
        }

        $userId = (string) $request->user()->id;

        if (false) {
            // DRAFT LOGIC REMOVED
        } else {
        // Create a single consolidated memo record
        $memo = Memo::create([
            'created_by' => $userId,
            'sender_id' => $userId,
            'recipient_id' => count($userIds) === 1 ? (string)$userIds[0] : null,
            'recipient_ids' => $userIds,
            'department_id' => $request->department_id ?? null,
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'priority' => $validated['priority'],
            'attachments' => $validated['attachments'] ?? [],
            'status' => 'sent',
            'version' => 1,
            'scheduled_send_at' => $validated['scheduled_send_at'] ?? null,
            'schedule_end_at' => $validated['schedule_end_at'] ?? null,
            'deadline_at' => $validated['deadline_at'] ?? null,
            'all_day_event' => $validated['all_day_event'] ?? false,
            'attachment_path' => $validated['attachment_path'] ?? null
        ]);

        // Create calendar event if memo is scheduled or has a deadline
        if (!empty($validated['scheduled_send_at']) || !empty($validated['deadline_at'])) {
            $this->createCalendarEventForMemo($memo, $validated, $request->user()->id);
        }

        // Create acknowledgment records and notify each recipient
        foreach ($userIds as $recipientId) {
            \App\Models\MemoAcknowledgment::create([
                'memo_id' => $memo->id,
                'recipient_id' => (string)$recipientId,
                'is_acknowledged' => false,
                'sent_at' => now()
            ]);

            // Notify this specific recipient
            $recipient = \App\Models\User::find($recipientId);
            if ($recipient) {
                $this->notificationService->notifyMemoReceived($request->user(), $recipient, $memo);
            }
        }
        $memos[] = $memo;
        }

        $action = 'create_memo';
        $this->activityLogger->logUserAction($request->user(), $action, count($memos) . " memos created", $this->activityLogger->extractRequestInfo($request));

        // Invalidate dashboard cache for creator and recipients
        \Illuminate\Support\Facades\Cache::forget("dashboard_data_user_{$userId}_v1_page_1_per_10");
        foreach ($userIds as $recipientId) {
            \Illuminate\Support\Facades\Cache::forget("dashboard_data_user_{$recipientId}_v1_page_1_per_10");
        }

        return response()->json([
            'status' => 'success',
            'message' => count($memos) . ' memo(s) sent successfully',
            'data' => $memos[0]->load('calendarEvents') // Return the first one or a summary
        ], 201);
    }

    /**
     * Create a calendar event for a scheduled memo
     */
    private function createCalendarEventForMemo($memo, $scheduleData, $userId)
    {
        $isDeadline = !empty($scheduleData['deadline_at']);
        $eventDate = $isDeadline ? $scheduleData['deadline_at'] : $scheduleData['scheduled_send_at'];

        $calendarEvent = \App\Models\CalendarEvent::updateOrCreate(
            ['memo_id' => $memo->id],
            [
                'title' => ($isDeadline ? "[Deadline] " : "[Scheduled] ") . $memo->subject,
                'description' => $memo->message,
                'start' => $eventDate,
                'end' => $scheduleData['schedule_end_at'] ?? ($isDeadline ? $scheduleData['deadline_at'] : $scheduleData['scheduled_send_at']),
                'all_day' => $scheduleData['all_day_event'] ?? false,
                'category' => $isDeadline ? 'deadline' : $this->mapPriorityToCategory($memo->priority),
                'created_by' => $userId,
                'status' => $isDeadline ? 'pending' : 'scheduled',
                'source' => $isDeadline ? 'DEADLINE' : 'MEMO'
            ]
        );

        // Clear and add participants
        \App\Models\CalendarEventParticipant::where('calendar_event_id', $calendarEvent->id)->delete();
        
        // Add sender as accepted
        \App\Models\CalendarEventParticipant::create([
            'calendar_event_id' => $calendarEvent->id,
            'user_id' => $memo->sender_id,
            'status' => 'accepted'
        ]);

        // Add recipients
        if ($memo->department_id) {
            $normDeptId = $this->normalizeUserId($memo->department_id);
            $departmentUsers = \App\Models\User::whereIn('department_id', [$memo->department_id, $normDeptId])
                                               ->where('id', '!=', $memo->sender_id)
                                               ->get();
            foreach ($departmentUsers as $deptUser) {
                \App\Models\CalendarEventParticipant::create([
                    'calendar_event_id' => $calendarEvent->id,
                    'user_id' => (string)$deptUser->id,
                    'status' => 'pending'
                ]);
            }
        } elseif ($memo->recipient_ids && is_array($memo->recipient_ids)) {
            foreach ($memo->recipient_ids as $recipientId) {
                \App\Models\CalendarEventParticipant::create([
                    'calendar_event_id' => $calendarEvent->id,
                    'user_id' => (string)$recipientId,
                    'status' => 'pending'
                ]);
            }
        } elseif ($memo->recipient_id) {
            \App\Models\CalendarEventParticipant::create([
                'calendar_event_id' => $calendarEvent->id,
                'user_id' => (string)$memo->recipient_id,
                'status' => 'pending'
            ]);
        }

        return $calendarEvent;
    }

    /**
     * Map memo priority to calendar category
     */
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
     * Display the specified memo with eager-loaded relationships.
     * 
     * PERFORMANCE: Uses with() for eager loading to prevent N+1 queries.
     */
    public function show($id)
    {
        $memo = Memo::with(['sender:_id,id,first_name,last_name,email,role,department,department_id,profile_picture', 
                           'recipient:_id,id,first_name,last_name,email,role,department,department_id,profile_picture',
                           'department:_id,name',
                           'acknowledgments.recipient:_id,first_name,last_name,email,profile_picture',
                           'calendarEvents'])
                    ->findOrFail($id);
        
        // If there are multiple recipients, fetch all their profiles
        if (!empty($memo->recipient_ids)) {
            $memo->recipients_list = User::whereIn('_id', $memo->recipient_ids)
                ->get(['id', 'first_name', 'last_name', 'email', 'profile_picture']);
        } else if ($memo->recipient_id) {
            // Fallback for single recipient memos if recipient_ids is empty
            $memo->recipients_list = User::where('_id', $memo->recipient_id)
                ->get(['id', 'first_name', 'last_name', 'email', 'profile_picture']);
        }

        $user = auth()->user();
        
        // Normalize IDs for comparison
        $userId = $this->normalizeUserId($user->id);
        $senderId = $this->normalizeUserId($memo->sender_id);
        $recipientId = $this->normalizeUserId($memo->recipient_id);
        $createdById = $this->normalizeUserId($memo->created_by);
        
        // Check if user is owner (sender, recipient, or creator)
        $isOwner = ($senderId == $userId) || 
                   ($recipientId == $userId) || 
                   ($createdById == $userId);
        
        // Admin check OR ownership/recipient check
        if (!$user->hasPermissionTo('memo.view') && !$isOwner) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($memo);
    }

    public function update(Request $request, $id)
    {
        $memo = Memo::findOrFail($id);
        $user = $request->user();
        
        // Normalize IDs for comparison
        $userId = $this->normalizeUserId($user->id);
        $createdById = $this->normalizeUserId($memo->created_by);
        
        // Permission check
        if (!$user->hasPermissionTo('memo.edit') && !$user->hasPermissionTo('memo.create')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Ownership: Only creator can update, or Admin
        if ($createdById != $userId && $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized to edit this memo.'], 403);
        }

        // Capture state for rollback
        $beforeState = $memo->toArray();

        $validated = $request->validate([
            'subject' => 'sometimes|string',
            'message' => 'sometimes|string',
            'priority' => 'sometimes|in:high,medium,low',
            'status' => 'sometimes|string',

            'scheduled_send_at' => 'nullable|date',
            'schedule_end_at' => 'nullable|date',
            'deadline_at' => 'nullable|date',
            'all_day_event' => 'nullable|boolean'
        ]);

        $memo->update(array_merge($validated, ['version' => $memo->version + 1]));

        // Update or create calendar event if schedule or deadline changed
        if (isset($validated['scheduled_send_at']) || isset($validated['deadline_at'])) {
            $existingEvent = $memo->calendarEvents()->first();
            
            if ($existingEvent) {
                $isDeadline = isset($validated['deadline_at']) ? !empty($validated['deadline_at']) : !empty($memo->deadline_at);
                $eventDate = $isDeadline ? ($validated['deadline_at'] ?? $memo->deadline_at) : ($validated['scheduled_send_at'] ?? $memo->scheduled_send_at);

                // Update existing calendar event
                $existingEvent->update([
                    'title' => ($isDeadline ? "[Deadline] " : "[Scheduled] ") . ($validated['subject'] ?? $memo->subject),
                    'description' => $validated['message'] ?? $memo->message,
                    'start' => $eventDate,
                    'end' => $validated['schedule_end_at'] ?? $eventDate,
                    'all_day' => $validated['all_day_event'] ?? $existingEvent->all_day,
                    'category' => $isDeadline ? 'deadline' : (isset($validated['priority']) ? $this->mapPriorityToCategory($validated['priority']) : $existingEvent->category),
                    'source' => $isDeadline ? 'DEADLINE' : 'MEMO'
                ]);
            } else {
                // Create new calendar event
                $this->createCalendarEventForMemo($memo, $validated, $request->user()->id);
            }
        }

        // Log Rollback info if significant change
        // Log Rollback info if significant change
        RollbackLog::create([
            'operation_id' => (string) \Illuminate\Support\Str::uuid(),
            'operation_type' => 'memo_update',
            'before_state' => $beforeState,
            'after_state' => $memo->toArray(),
            'performed_by' => $request->user()->id,
            'status' => 'completed'
        ]);

        $this->activityLogger->logUserAction($request->user(), 'update_memo', $memo, $this->activityLogger->extractRequestInfo($request));

        // Invalidate dashboard cache for involved parties
        \Illuminate\Support\Facades\Cache::forget("dashboard_data_user_{$user->id}_v1_page_1_per_10");
        if ($memo->recipient_id) {
            \Illuminate\Support\Facades\Cache::forget("dashboard_data_user_{$memo->recipient_id}_v1_page_1_per_10");
        }
        if (!empty($memo->recipient_ids)) {
            foreach ($memo->recipient_ids as $rId) {
                \Illuminate\Support\Facades\Cache::forget("dashboard_data_user_{$rId}_v1_page_1_per_10");
            }
        }

        return response()->json($memo->load('calendarEvents'));
    }

    public function destroy(Request $request, $id)
    {
        $memo = Memo::findOrFail($id);
        $user = $request->user();
        
        // Normalize IDs for comparison
        $userId = $this->normalizeUserId($user->id);
        $createdById = $this->normalizeUserId($memo->created_by);
        $recipientId = $this->normalizeUserId($memo->recipient_id);

        // Permission check
        if (!$user->hasPermissionTo('memo.archive')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        // Load relationships to ensure payload is rich with data for preview
        $memo->load(['sender', 'acknowledgments.recipient', 'departmentModel']);
        
        // Ownership or Admin or Recipient
        \App\Models\Archive::create([
            'item_id' => (string) $memo->id,
            'item_type' => 'memo',
            'archived_by' => (string) $user->id,
            'archived_at' => now(),
            'sender_id' => (string) $memo->sender_id,
            'recipient_id' => (string)$memo->recipient_id,
            'created_by' => (string) $memo->created_by,
            'payload' => $memo->toArray()
        ]);
        
        $memo->delete();
        
        $this->activityLogger->logUserAction($user, 'delete_memo', "Deleted memo {$id}", $this->activityLogger->extractRequestInfo($request));

        return response()->json(['message' => 'Memo deleted successfully']);
    }

    public function acknowledge(Request $request, $id)
    {
        $memo = Memo::findOrFail($id);
        $user = $request->user();
        
        // Normalize IDs for comparison
        $userId = $this->normalizeUserId($user->id);
        $senderId = $this->normalizeUserId($memo->sender_id);
        $createdById = $this->normalizeUserId($memo->created_by);
        $recipientId = $this->normalizeUserId($memo->recipient_id);
        $recipientIds = array_map(fn($rid) => $this->normalizeUserId($rid), $memo->recipient_ids ?? []);

        // Common sense: Sender/Creator shouldn't acknowledge their own memo
        if ($userId == $senderId || $userId == $createdById) {
            return response()->json([
                'message' => 'Sender viewing own memo, acknowledgment skipped.', 
                'memo' => $memo
            ], 200);
        }

        // Check if user is a designated recipient
        $isRecipient = ($userId == $recipientId) || in_array($userId, $recipientIds);

        if (!$isRecipient) {
            return response()->json(['message' => 'Unauthorized to acknowledge this memo'], 403);
        }

        // Update memo status to acknowledged
        $memo->update(['status' => 'acknowledged']);

        // Update or create acknowledgment record
        $acknowledgment = \App\Models\MemoAcknowledgment::where('memo_id', $memo->id)
            ->where('recipient_id', $user->id)
            ->first();
        
        if ($acknowledgment) {
            $acknowledgment->update([
                'is_acknowledged' => true,
                'acknowledged_at' => now()
            ]);
        } else {
            \App\Models\MemoAcknowledgment::create([
                'memo_id' => $memo->id,
                'recipient_id' => $user->id,
                'is_acknowledged' => true,
                'acknowledged_at' => now(),
                'sent_at' => now()
            ]);
        }

        // Notify the memo sender (secretary) about acknowledgment
        $sender = \App\Models\User::find($memo->sender_id);
        if ($sender && $sender->id !== $user->id) {
            $this->notificationService->notifyMemoAcknowledged($user, $sender, $memo);
        }

        $this->activityLogger->logUserAction($user, 'acknowledge_memo', $memo, $this->activityLogger->extractRequestInfo($request));

        // Invalidate dashboard cache for recipient and sender
        \Illuminate\Support\Facades\Cache::forget("dashboard_data_user_{$user->id}_v1_page_1_per_10");
        \Illuminate\Support\Facades\Cache::forget("dashboard_data_user_{$memo->sender_id}_v1_page_1_per_10");

        return response()->json([
            'message' => 'Memo acknowledged successfully', 
            'memo' => $memo->fresh()
        ]);
    }

    /**
     * Send reminder for acknowledgment
     */
    public function sendReminder(Request $request, $id)
    {
        $user = $request->user();
        $memo = Memo::with(['sender'])->findOrFail($id);

        // Ownership check: only sender or creator can send reminders
        $senderId = $this->normalizeUserId($memo->sender_id);
        $createdById = $this->normalizeUserId($memo->created_by);
        $userId = $this->normalizeUserId($user->id);

        $isOwner = ($senderId == $userId) || ($createdById == $userId);
        
        // Admin also can send reminders if they have permission
        if (!$isOwner && !$user->hasPermissionTo('memo.approve') && !$user->hasPermissionTo('memo.approve_all')) {
            return response()->json(['message' => 'Unauthorized to send reminders'], 403);
        }

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
}
