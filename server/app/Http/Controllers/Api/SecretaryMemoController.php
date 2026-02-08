<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Memo;
use App\Models\MemoAcknowledgment;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SecretaryMemoController extends Controller
{
    protected $activityLogger;

    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }

    /**
     * Get memos for secretary with scope filtering
     * Received: memos sent to users in secretary's department
     * Sent: memos sent by this secretary
     * Pending: memos submitted for approval
     * Drafts: draft memos
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $scope = $request->get('scope', '');
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 15);
        
        // Filter parameters
        $search = $request->get('search');
        $department = $request->get('department');
        $priority = $request->get('priority');
        $sort = $request->get('sort', 'desc');
        $date = $request->get('date');

        $query = Memo::with(['sender', 'recipient', 'department']);
        
        \Illuminate\Support\Facades\Log::info("SecretaryMemo Index Debug", [
            'user' => $user->id,
            'scope' => $scope,
            'search' => $search,
            'department' => $department,
            'priority' => $priority,
            'date' => $date
        ]);

        switch ($scope) {
            case 'sent':
                // Memos sent by this secretary (not drafts)
                $query->where('sender_id', $user->id)
                      ->where('is_draft', false)
                      ->where('status', '!=', 'pending_approval');
                break;

            case 'pending':
                // Memos submitted for approval by this secretary
                $query->where('sender_id', $user->id)
                      ->where('status', 'pending_approval');
                break;

            case 'drafts':
                // Draft memos
                $query->where('created_by', (string)$user->id)
                      ->whereIn('is_draft', [true, 1]);
                // For drafts, we might want to load the recipients too
                // But since recipients is not a standard Eloquent relation for eager loading easily in MongoDB like this, 
                // we'll handle it in the response or just let the frontend handle it if common users are already fetched.
                break;

            default:
            // RECEIVED: Memos sent to users in secretary's department
            $recipientIds = User::where('department_id', $user->department_id)
                               ->where('id', '!=', $user->id)
                               ->pluck('id')
                               ->toArray();
                
            // Also include memos sent directly to the secretary
            $recipientIds[] = $user->id;
                
            $query->where(function ($q) use ($user, $recipientIds) {
                // Received memos
                $q->whereIn('recipient_id', $recipientIds)
                  ->where('is_draft', false)
                  ->where('status', '!=', 'pending_approval');
                  
                // OR Memos sent/created by this secretary that are drafts or pending approval
                $q->orWhere(function ($sq) use ($user) {
                    $sq->where('created_by', (string)$user->id)
                       ->where(function ($ssq) {
                           $ssq->whereIn('is_draft', [true, 1])
                               ->orWhere('status', 'pending_approval');
                       });
                });
            });
            break;
        }

        // Apply filters
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%")
                  ->orWhereHas('sender', function ($sq) use ($search) {
                      $sq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        if ($department && $department !== 'All Departments') {
            // Check if $department is an ID (MongoID or similar) or a name
            if (preg_match('/^[0-9a-fA-F]{24}$/', $department)) {
                $query->where('department_id', $department);
            } else {
                // Filter by department name via relationship
                $query->whereHas('department', function ($q) use ($department) {
                    $q->where('name', $department);
                });
            }
        }

        if ($priority) {
            $query->where('priority', $priority);
        }

        if ($date) {
            $query->whereDate('created_at', $date);
        }

        // Apply sorting
        $query->orderBy('created_at', $sort);

        $memos = $query->paginate($perPage, ['*'], 'page', $page);
        
        \Illuminate\Support\Facades\Log::info("SecretaryMemo Result Debug", [
            'count' => $memos->count(),
            'total' => $memos->total()
        ]);

        return response()->json($memos);
    }

    /**
     * Get memo statistics for secretary dashboard
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        // Get recipient IDs in department
        $recipientIds = User::where('department_id', $user->department_id)
                           ->where('id', '!=', $user->id)
                           ->pluck('id')
                           ->toArray();
        $recipientIds[] = $user->id;

        $stats = [
            // Received memos (sent to department)
            'received' => Memo::whereIn('recipient_id', $recipientIds)
                            ->where('is_draft', false)
                            ->where('status', '!=', 'pending_approval')
                            ->count(),
            
            // Sent memos (approved and sent)
            'sent' => Memo::where('sender_id', $user->id)
                         ->where('is_draft', false)
                         ->where('status', '!=', 'pending_approval')
                         ->count(),
            
            // Pending approval
            'pending' => Memo::where('sender_id', $user->id)
                            ->where('status', 'pending_approval')
                            ->count(),
            
            // Drafts
            'drafts' => Memo::where('sender_id', $user->id)
                           ->where('is_draft', true)
                           ->count()
        ];

        return response()->json($stats);
    }

    /**
     * Submit a memo for admin approval (Secretary workflow)
     */
    public function submitForApproval(Request $request)
    {
        $validated = $request->validate([
            'recipient_ids' => 'required_without:department_id|array',
            'recipient_ids.*' => 'exists:users,id',
            'department_id' => 'required_without:recipient_ids|exists:departments,id',
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

        $user = $request->user();

        // Secretary can only send to their own department
        if ($request->department_id) {
            $department = \App\Models\Department::find($request->department_id);
            if (!$department || $department->id !== $user->department_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only send memos to your own department.'
                ], 422);
            }
            $userIds = User::where('department_id', $request->department_id)->pluck('id')->toArray();
        } else {
            $userIds = $validated['recipient_ids'];
            $invalidUsers = User::whereIn('id', $userIds)
                               ->where('department_id', '!=', $user->department_id)
                               ->count();
            
            if ($invalidUsers > 0) {
                 return response()->json([
                    'success' => false,
                    'message' => 'You can only send memos to users in your department.'
                ], 422);
            }
        }

        if (empty($userIds)) {
            return response()->json(['message' => 'No users found in this department'], 422);
        }

        $memos = [];
        foreach ($userIds as $recipientId) {
            $memo = Memo::create([
                'created_by' => $user->id,
                'sender_id' => $user->id,
                'recipient_id' => $recipientId,
                'department_id' => $request->department_id ?? null,
                'signature_id' => $request->signature_id ?? null,
                'subject' => $validated['subject'],
                'message' => $validated['message'],
                'priority' => $validated['priority'],
                'attachments' => $validated['attachments'] ?? [],
                'status' => 'pending_approval', // Set to pending approval
                'is_draft' => $validated['is_draft'] ?? false,
                'version' => 1,
                'scheduled_send_at' => $validated['scheduled_send_at'] ?? null,
                'attachment_path' => $validated['attachment_path'] ?? null
            ]);
            $memos[] = $memo;
        }

        $action = ($validated['is_draft'] ?? false) ? 'create_draft_memo' : 'submit_memo_for_approval';
        $this->activityLogger->logUserAction(
            $user, 
            $action, 
            count($memos) . " memos submitted for approval", 
            $this->activityLogger->extractRequestInfo($request)
        );

        return response()->json([
            'status' => 'success',
            'message' => count($memos) . ' memo(s) submitted for Admin approval',
            'data' => $memos[0]
        ], 201);
    }

    /**
     * Get acknowledgment status for a memo (Secretary view)
     */
    public function acknowledgmentStatus(Request $request, $memoId)
    {
        $user = $request->user();
        
        $memo = Memo::with(['sender', 'recipient'])->findOrFail($memoId);

        // Only sender (secretary) or admin can view acknowledgment status
        if ($memo->sender_id !== $user->id && $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get all acknowledgments for department-wide memos
        $acknowledgments = MemoAcknowledgment::where('memo_id', $memoId)->get();

        // Get department members if this was a department-wide memo
        $recipientIds = User::where('department_id', $user->department_id)
                          ->where('id', '!=', $user->id)
                          ->pluck('id')
                          ->toArray();

        $totalRecipients = count($recipientIds);
        $acknowledgedCount = $acknowledgments->where('is_acknowledged', true)->count();
        $pendingCount = $totalRecipients - $acknowledgedCount;

        return response()->json([
            'memo' => $memo,
            'acknowledgments' => $acknowledgments,
            'summary' => [
                'total_recipients' => $totalRecipients,
                'acknowledged' => $acknowledgedCount,
                'pending' => $pendingCount,
                'percentage' => $totalRecipients > 0 ? round(($acknowledgedCount / $totalRecipients) * 100) : 0
            ]
        ]);
    }

    /**
     * Save memo as draft
     */
    public function storeDraft(Request $request)
    {
        $validated = $request->validate([
            'recipient_ids' => 'nullable|array',
            'recipient_ids.*' => 'exists:users,id',
            'department_id' => 'nullable|exists:departments,id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'priority' => 'required|in:urgent,high,normal,low',
            'attachments' => 'nullable|array',
            'signature_id' => 'nullable|exists:user_signatures,id',
            'attachment_path' => 'nullable|string'
        ]);

        $user = $request->user();

        $memo = Memo::create([
            'created_by' => $user->id,
            'sender_id' => $user->id,
            'recipient_id' => (isset($validated['recipient_ids']) && count($validated['recipient_ids']) === 1) ? $validated['recipient_ids'][0] : null,
            'recipient_ids' => $validated['recipient_ids'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'signature_id' => $validated['signature_id'] ?? null,
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'priority' => $validated['priority'],
            'attachments' => $validated['attachments'] ?? [],
            'status' => 'draft',
            'is_draft' => true,
            'version' => 1,
            'attachment_path' => $validated['attachment_path'] ?? null
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Draft saved successfully',
            'data' => $memo
        ], 201);
    }

    /**
     * Delete a memo (Secretary can delete their own drafts)
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $memo = Memo::findOrFail($id);

        // Only allow deletion of own drafts or pending memos
        if ($memo->sender_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized to delete this memo'], 403);
        }

        if (!$memo->is_draft && $memo->status !== 'pending_approval') {
            return response()->json(['message' => 'Only drafts or pending memos can be deleted'], 422);
        }

        $memo->delete();

        $this->activityLogger->logUserAction(
            $user,
            'delete_memo',
            "Deleted memo {$id}",
            $this->activityLogger->extractRequestInfo($request)
        );

        return response()->json(['message' => 'Memo deleted successfully']);
    }

    /**
     * Update a draft memo
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $memo = Memo::findOrFail($id);

        // Only allow update of own drafts
        if ($memo->sender_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized to update this memo'], 403);
        }

        if (!$memo->is_draft) {
            return response()->json(['message' => 'Only drafts can be updated'], 422);
        }

        $validated = $request->validate([
            'recipient_ids' => 'nullable|array',
            'recipient_ids.*' => 'exists:users,id',
            'department_id' => 'nullable|exists:departments,id',
            'subject' => 'sometimes|string|max:255',
            'message' => 'sometimes|string',
            'priority' => 'sometimes|in:urgent,high,normal,low',
            'attachments' => 'nullable|array',
            'signature_id' => 'nullable|exists:user_signatures,id',
            'attachment_path' => 'nullable|string'
        ]);

        if (isset($validated['recipient_ids'])) {
            $validated['recipient_id'] = count($validated['recipient_ids']) === 1 ? $validated['recipient_ids'][0] : null;
        }

        $memo->update($validated);

        $this->activityLogger->logUserAction(
            $user,
            'update_draft_memo',
            "Updated draft memo {$id}",
            $this->activityLogger->extractRequestInfo($request)
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Draft updated successfully',
            'data' => $memo
        ]);
    }

    /**
     * Bulk delete memos (Secretary can delete their own drafts/pending)
     */
    public function bulkDestroy(Request $request)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:memos,id'
        ]);
        
        $ids = $validated['ids'];
        
        // Get user's memos that are drafts or pending
        $memos = Memo::whereIn('id', $ids)
                    ->where('sender_id', $user->id)
                    ->where(function ($query) {
                        $query->where('is_draft', true)
                              ->orWhere('status', 'pending_approval');
                    })
                    ->get();
        
        $deleted = [];
        $skipped = [];
        
        foreach ($ids as $id) {
            $memo = $memos->firstWhere('id', $id);
            if ($memo) {
                $memo->delete();
                $deleted[] = $id;
            } else {
                $skipped[] = $id;
            }
        }
        
        $this->activityLogger->logUserAction(
            $user,
            'bulk_delete_memos',
            "Bulk deleted " . count($deleted) . " memos",
            ['deleted' => $deleted, 'skipped' => $skipped]
        );
        
        return response()->json([
            'status' => 'success',
            'message' => "Deleted " . count($deleted) . " memo(s)",
            'data' => [
                'deleted' => $deleted,
                'skipped' => $skipped
            ]
        ]);
    }

    /**
     * Bulk submit memos for approval
     */
    public function bulkSubmitForApproval(Request $request)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:memos,id'
        ]);
        
        $ids = $validated['ids'];
        
        // Get user's draft memos
        $drafts = Memo::whereIn('id', $ids)
                     ->where('sender_id', $user->id)
                     ->where('is_draft', true)
                     ->get();
        
        $submitted = [];
        $skipped = [];
        
        foreach ($ids as $id) {
            $memo = $drafts->firstWhere('id', $id);
            if ($memo) {
                $memo->update([
                    'status' => 'pending_approval',
                    'is_draft' => false
                ]);
                $submitted[] = $id;
            } else {
                $skipped[] = $id;
            }
        }
        
        $this->activityLogger->logUserAction(
            $user,
            'bulk_submit_memos',
            "Bulk submitted " . count($submitted) . " memos for approval",
            ['submitted' => $submitted, 'skipped' => $skipped]
        );
        
        return response()->json([
            'status' => 'success',
            'message' => "Submitted " . count($submitted) . " memo(s) for approval",
            'data' => [
                'submitted' => $submitted,
                'skipped' => $skipped
            ]
        ]);
    }
}
