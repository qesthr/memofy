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
            'recipient_id' => 'required|exists:users,id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'priority' => 'required|in:urgent,high,normal,low',
            'attachments' => 'nullable|array',
            'is_draft' => 'boolean'
        ]);

        $memo = Memo::create([
            'created_by' => $request->user()->id,
            'sender_id' => $request->user()->id,
            'recipient_id' => $validated['recipient_id'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'priority' => $validated['priority'],
            'attachments' => $validated['attachments'] ?? [],
            'status' => $validated['is_draft'] ? 'draft' : 'sent',
            'is_draft' => $validated['is_draft'] ?? false,
            'version' => 1
        ]);

        $action = $memo->is_draft ? 'create_draft_memo' : 'create_memo';
        $this->activityLogger->logUserAction($request->user(), $action, $memo, $this->activityLogger->extractRequestInfo($request));

        return response()->json($memo, 201);
    }

    public function show($id)
    {
        // TODO: Add policy authorization
        $memo = Memo::with(['sender', 'recipient'])->findOrFail($id);
        return response()->json($memo);
    }

    public function update(Request $request, $id)
    {
        $memo = Memo::findOrFail($id);
        
        // Authorization: Only creator can update
        if ($memo->created_by !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Capture state for rollback (if not draft)
        $beforeState = $memo->toArray();

        $validated = $request->validate([
            'subject' => 'sometimes|string',
            'message' => 'sometimes|string',
            'priority' => 'sometimes|in:urgent,high,normal,low',
            'status' => 'sometimes|string', // Allow status updates?
            'is_draft' => 'sometimes|boolean'
        ]);

        $memo->update(array_merge($validated, ['version' => $memo->version + 1]));

        // Log Rollback info if significant change
        if (!$memo->is_draft) {
            RollbackLog::create([
                'operation_id' => (string) Str::uuid(),
                'operation_type' => 'memo_update',
                'before_state' => $beforeState,
                'after_state' => $memo->toArray(),
                'performed_by' => $request->user()->id,
                'status' => 'completed'
            ]);
        }

        $this->activityLogger->logUserAction($request->user(), 'update_memo', $memo, $this->activityLogger->extractRequestInfo($request));

        return response()->json($memo);
    }

    public function destroy(Request $request, $id)
    {
        $memo = Memo::findOrFail($id);
        // Soft delete or hard delete? Assuming hard for now or just status=deleted
        $memo->delete();
        
        $this->activityLogger->logUserAction($request->user(), 'delete_memo', "Deleted memo {$id}", $this->activityLogger->extractRequestInfo($request));

        return response()->json(['message' => 'Memo deleted']);
    }
}
