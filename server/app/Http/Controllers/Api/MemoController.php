<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Memo;
use App\Models\RollbackLog;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
     * Display a listing of memos with pagination and eager loading.
     * 
     * PERFORMANCE: Uses paginate() with eager loading to prevent N+1 queries.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = min((int) $request->get('per_page', 15), 50);
        $query = Memo::with(['sender:_id,id,first_name,last_name,email', 'recipient:_id,id,first_name,last_name,email', 'department:_id,id,name']);

        // Use string ID for comparison to be safe with MongoDB driver
        $userId = (string) $user->id;

        // Scope: Sent, Received, or Drafts
        if ($request->scope === 'sent') {
            $query->where('sender_id', $userId)->where('is_draft', false);
        } elseif ($request->scope === 'drafts') {
            $query->where('created_by', $userId)->where('is_draft', true);
        } else {
            // Default: All (Received + Sent + Drafts)
            $query->where(function ($q) use ($userId) {
                // Received
                $q->where('recipient_id', $userId)
                  ->where('is_draft', false);
                
                // Sent + Drafts
                $q->orWhere('sender_id', $userId);
            });
        }

        // Search
        if ($request->search) {
            $query->where('subject', 'like', "%{$request->search}%");
        }

        // Sort
        $query->orderBy('created_at', 'desc');

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
            'priority' => 'required|in:urgent,high,normal,low',
            'attachments' => 'nullable|array',
            'is_draft' => 'boolean',
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

        if ($validated['is_draft'] ?? false) {
            // SINGLE record for drafts
            $memo = Memo::create([
                'created_by' => $userId,
                'sender_id' => $userId,
                'recipient_id' => (count($userIds) === 1) ? (string) $userIds[0] : null,
                'recipient_ids' => array_map('strval', $userIds), // Store all recipients in the draft as strings
                'department_id' => $request->department_id ?? null,
                'signature_id' => $request->signature_id ?? null,
                'signature_ids' => $request->signature_ids ?? null,
                'signature_positions' => $request->signature_positions ?? null,
                'subject' => $validated['subject'],
                'message' => $validated['message'],
                'priority' => $validated['priority'],
                'attachments' => $validated['attachments'] ?? [],
                'status' => 'draft',
                'is_draft' => true,
                'version' => 1,
                'attachment_path' => $validated['attachment_path'] ?? null
            ]);
            $memos[] = $memo;
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
                    'is_draft' => false,
                    'version' => 1,
                    'scheduled_send_at' => $validated['scheduled_send_at'] ?? null,
                    'attachment_path' => $validated['attachment_path'] ?? null
                ]);

                // Create calendar event if memo is scheduled
                if (!empty($validated['scheduled_send_at']) && !$memo->is_draft) {
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

        $action = ($validated['is_draft'] ?? false) ? 'create_draft_memo' : 'create_memo';
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
            'urgent' => 'urgent',
            'high' => 'high',
            'normal' => 'standard',
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

        // Admin check OR ownership/recipient check
        if (!$user->hasPermissionTo('memo.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->role !== 'admin' && $memo->sender_id !== $user->id && $memo->recipient_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized access to this memo.'], 403);
        }

        return response()->json($memo);
    }

    public function update(Request $request, $id)
    {
        $memo = Memo::findOrFail($id);
        $user = $request->user();
        
        // Permission check
        if (!$user->hasPermissionTo('memo.edit') && !$user->hasPermissionTo('memo.create')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Ownership: Only creator can update, or Admin
        if ($memo->created_by !== $user->id && $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized to edit this memo.'], 403);
        }

        // Capture state for rollback (if not draft)
        $beforeState = $memo->toArray();

        $validated = $request->validate([
            'subject' => 'sometimes|string',
            'message' => 'sometimes|string',
            'priority' => 'sometimes|in:urgent,high,normal,low',
            'status' => 'sometimes|string',
            'is_draft' => 'sometimes|boolean',
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
            } else if (!$memo->is_draft) {
                // Create new calendar event
                $this->createCalendarEventForMemo($memo, $validated, $request->user()->id);
            }
        }

        // Log Rollback info if significant change
        if (!$memo->is_draft) {
            RollbackLog::create([
                'operation_id' => (string) \Illuminate\Support\Str::uuid(),
                'operation_type' => 'memo_update',
                'before_state' => $beforeState,
                'after_state' => $memo->toArray(),
                'performed_by' => $request->user()->id,
                'status' => 'completed'
            ]);
        }

        $this->activityLogger->logUserAction($request->user(), 'update_memo', $memo, $this->activityLogger->extractRequestInfo($request));

        return response()->json($memo->load('calendarEvents'));
    }

    public function destroy(Request $request, $id)
    {
        $memo = Memo::findOrFail($id);
        $user = $request->user();

        // Permission check
        if (!$user->hasPermissionTo('memo.archive')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        // Ownership or Admin or Recipient
        if ($memo->created_by !== $user->id && $memo->recipient_id !== $user->id && $user->role !== 'admin') {
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

        if ($memo->recipient_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized to acknowledge this memo'], 403);
        }

        $memo->update(['status' => 'read']);

        $this->activityLogger->logUserAction($user, 'acknowledge_memo', $memo, $this->activityLogger->extractRequestInfo($request));

        return response()->json(['message' => 'Memo acknowledged successfully', 'memo' => $memo]);
    }
}
