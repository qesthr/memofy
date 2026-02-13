<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Memo;
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
            'sender:_id,id,first_name,last_name,email,role',
            'recipient:_id,id,first_name,last_name,email,role',
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
                $query->whereIn('recipient_id', $targetIds);
            }
            $query->whereIn('status', ['sent', 'read', 'acknowledged', 'archived']);

        } elseif ($request->scope === 'pending') {
            if (!$isAdmin) {
                $query->whereIn('sender_id', $targetIds);
            }
            $query->where('status', 'pending_approval');
        } else {
            // Default: All (Received + Sent + Drafts) - EXCLUDING ONLY PENDING
            $query->where(function ($q) use ($targetIds, $isAdmin) {
                if ($isAdmin) {
                    $q->where('status', '!=', 'pending_approval');
                } else {
                    // Regular users: restricted to self
                    // Received/Sent memos (not pending)
                    $q->where(function ($sq) use ($targetIds) {
                        $sq->where('status', '!=', 'pending_approval')
                           ->where(function ($ssq) use ($targetIds) {
                               $ssq->whereIn('recipient_id', $targetIds)
                                   ->orWhereIn('sender_id', $targetIds);
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
            'recipient_id' => 'nullable|exists:users,id',
            'recipient_ids' => 'nullable|array',
            'recipient_ids.*' => 'exists:users,id',
            'department_id' => 'nullable|exists:departments,id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'priority' => 'required|in:high,medium,low',
            'attachments' => 'nullable|array',

            'scheduled_send_at' => 'nullable|date',
            'schedule_end_at' => 'nullable|date',
            'all_day_event' => 'nullable|boolean',
            'signature_id' => 'nullable|exists:user_signatures,id',
            'signature_ids' => 'nullable|array',
            'signature_positions' => 'nullable|array',
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
            // MULTIPLE records for sent memos (one per recipient)
            $userId = (string) $request->user()->id;
            
            foreach ($userIds as $recipientId) {
                $memo = Memo::create([
                    'created_by' => $userId,
                    'sender_id' => $userId,
                    'recipient_id' => (string) $recipientId,
                    'department_id' => $request->department_id ?? null,
                    'signature_id' => $request->signature_id ?? null,
                    'signature_ids' => $request->signature_ids ?? null,
                    'signature_positions' => $request->signature_positions ?? null,
                    'subject' => $validated['subject'],
                    'message' => $validated['message'],
                    'priority' => $validated['priority'],
                    'attachments' => $validated['attachments'] ?? [],
                    'status' => 'sent',
                    'version' => 1,
                    'scheduled_send_at' => $validated['scheduled_send_at'] ?? null,
                    'attachment_path' => $validated['attachment_path'] ?? null
                ]);

                // Create calendar event if memo is scheduled
                if (!empty($validated['scheduled_send_at'])) {
                    $this->createCalendarEventForMemo($memo, $validated, $request->user()->id);
                }

                // Create acknowledgment record
                \App\Models\MemoAcknowledgment::create([
                    'memo_id' => $memo->id,
                    'recipient_id' => $recipientId,
                    'is_acknowledged' => false,
                    'sent_at' => now()
                ]);

                // Notify this specific recipient
                $recipient = \App\Models\User::find($recipientId);
                if ($recipient) {
                    $this->notificationService->notifyMemoReceived($request->user(), $recipient, $memo);
                }

                $memos[] = $memo;
            }
        }

        $action = 'create_memo';
        $this->activityLogger->logUserAction($request->user(), $action, count($memos) . " memos created", $this->activityLogger->extractRequestInfo($request));

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
        $calendarEvent = \App\Models\CalendarEvent::create([
            'title' => $memo->subject,
            'description' => $memo->message,
            'start' => $scheduleData['scheduled_send_at'],
            'end' => $scheduleData['schedule_end_at'] ?? $scheduleData['scheduled_send_at'],
            'all_day' => $scheduleData['all_day_event'] ?? false,
            'category' => $this->mapPriorityToCategory($memo->priority),
            'memo_id' => $memo->id,
            'created_by' => $userId,
            'status' => 'scheduled',
            'source' => 'MEMO'
        ]);

        // Add participants: sender and recipient
        \App\Models\CalendarEventParticipant::create([
            'calendar_event_id' => $calendarEvent->id,
            'user_id' => $memo->sender_id,
            'status' => 'accepted'
        ]);

        \App\Models\CalendarEventParticipant::create([
            'calendar_event_id' => $calendarEvent->id,
            'user_id' => $memo->recipient_id,
            'status' => 'pending'
        ]);

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
        $memo = Memo::with(['sender:id,first_name,last_name,email,role,department', 
                           'recipient:id,first_name,last_name,email,role,department',
                           'department:id,name',
                           'signature:id,signature_data,user_id',
                           'acknowledgments',
                           'calendarEvents'])
                    ->findOrFail($id);
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
            'all_day_event' => 'nullable|boolean'
        ]);

        $memo->update(array_merge($validated, ['version' => $memo->version + 1]));

        // Update or create calendar event if schedule changed
        if (isset($validated['scheduled_send_at'])) {
            $existingEvent = $memo->calendarEvents()->first();
            
            if ($existingEvent) {
                // Update existing calendar event
                $existingEvent->update([
                    'title' => $validated['subject'] ?? $memo->subject,
                    'description' => $validated['message'] ?? $memo->message,
                    'start' => $validated['scheduled_send_at'],
                    'end' => $validated['schedule_end_at'] ?? $validated['scheduled_send_at'],
                    'all_day' => $validated['all_day_event'] ?? $existingEvent->all_day,
                    'category' => isset($validated['priority']) ? $this->mapPriorityToCategory($validated['priority']) : $existingEvent->category
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

        // Ownership or Admin or Recipient
        if ($createdById != $userId && $recipientId != $userId && $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized to delete this memo.'], 403);
        }

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
        $recipientId = $this->normalizeUserId($memo->recipient_id);

        if ($recipientId != $userId) {
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

        return response()->json([
            'message' => 'Memo acknowledged successfully', 
            'memo' => $memo->fresh()
        ]);
    }
}
