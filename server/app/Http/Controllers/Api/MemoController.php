<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Memo;
use App\Models\RollbackLog;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemoController extends Controller
{
    protected $activityLogger;

    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Memo::query();

        // Scope: Sent, Received, or Drafts
        if ($request->scope === 'sent') {
            $query->where('sender_id', $user->id)->where('is_draft', false);
        } elseif ($request->scope === 'drafts') {
            $query->where('created_by', $user->id)->where('is_draft', true);
        } else {
            // Default: Received
            $query->where('recipient_id', $user->id)->where('is_draft', false);
        }

        // Search
        if ($request->search) {
            $query->where('subject', 'like', "%{$request->search}%");
        }

        // Sort
        $query->orderBy('created_at', 'desc');

        return response()->json($query->paginate(15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'recipient_id' => 'required_without:department_id|exists:users,id',
            'department_id' => 'required_without:recipient_id|exists:departments,id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'priority' => 'required|in:urgent,high,normal,low',
            'attachments' => 'nullable|array',
            'is_draft' => 'boolean',
            'scheduled_send_at' => 'nullable|date',
            'schedule_end_at' => 'nullable|date',
            'all_day_event' => 'nullable|boolean',
            'signature_id' => 'nullable|exists:user_signatures,id',
            'attachment_path' => 'nullable|string'
        ]);

        $memos = [];
        $userIds = [];

        if ($request->department_id) {
            $userIds = \App\Models\User::where('department_id', $request->department_id)->pluck('id')->toArray();
            if (empty($userIds)) {
                return response()->json(['message' => 'No users found in this department'], 422);
            }
        } else {
            $userIds = [$validated['recipient_id']];
        }

        foreach ($userIds as $recipientId) {
            $memo = Memo::create([
                'created_by' => $request->user()->id,
                'sender_id' => $request->user()->id,
                'recipient_id' => $recipientId,
                'department_id' => $request->department_id ?? null,
                'signature_id' => $request->signature_id ?? null,
                'subject' => $validated['subject'],
                'message' => $validated['message'],
                'priority' => $validated['priority'],
                'attachments' => $validated['attachments'] ?? [],
                'status' => $validated['is_draft'] ? 'draft' : 'sent',
                'is_draft' => $validated['is_draft'] ?? false,
                'version' => 1,
                'scheduled_send_at' => $validated['scheduled_send_at'] ?? null,
                'attachment_path' => $validated['attachment_path'] ?? null
            ]);

            // Create calendar event if memo is scheduled
            if (!empty($validated['scheduled_send_at']) && !$memo->is_draft) {
                $this->createCalendarEventForMemo($memo, $validated, $request->user()->id);
            }

            $memos[] = $memo;
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

    public function show($id)
    {
        $memo = Memo::with(['sender', 'recipient'])->findOrFail($id);
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

        // Ownership or Admin
        if ($memo->created_by !== $user->id && $user->role !== 'admin') {
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
