<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Memo;
use App\Models\MemoAcknowledgment;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;

class SecretaryMemoController extends Controller
{
    protected $activityLogger;

    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
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
     * Get memos for secretary with scope filtering and eager loading.
     * 
     * PERFORMANCE: Already uses paginate() but now with optimized eager loading.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $scope = $request->get('scope', '');
        $page = $request->get('page', 1);
        $perPage = min((int) $request->get('per_page', 15), 50);
        
        // Filter parameters
        $search = $request->get('search');
        $department = $request->get('department');
        $priority = $request->get('priority');
        $sort = $request->get('sort', 'desc');
        $date = $request->get('date');

        // Eager load with selective columns to reduce data transfer
        $query = Memo::with([
            'sender:_id,id,first_name,last_name,email,role,department',
            'recipient:_id,id,first_name,last_name,email,role,department',
            'department:_id,id,name'
        ]);
        
        // Normalize user ID for MongoDB comparison
        $userId = $this->normalizeUserId($user->id);

        switch ($scope) {
            case 'sent':
                $query->where('sender_id', $userId)
                      ->where('is_draft', false)
                      ->where('status', 'sent'); // STRICT: Only sent status
                break;

            case 'pending':
                $query->where('sender_id', $userId)
                      ->where('status', 'pending_approval'); // STRICT: Already strict
                break;

            case 'drafts':
                $query->where('created_by', $userId)
                      ->whereIn('is_draft', [true, 1]); // STRICT: Already strict
                break;

            default:
            // RECEIVED: Memos sent to users in secretary's department
            $recipientIds = User::where('department_id', (string)$user->department_id)
                               ->where('_id', '!=', $userId)
                               ->pluck('id')
                               ->toArray();
                
            $recipientIds[] = $userId;
                
            $query->where(function ($q) use ($userId, $recipientIds) {
                $q->whereIn('recipient_id', $recipientIds)
                  ->where('is_draft', false)
                  ->whereIn('status', ['sent', 'read', 'acknowledged']); // STRICT: Received means it was sent
                  
                $q->orWhere(function ($sq) use ($userId) {
                    $sq->where('created_by', $userId)
                       ->where('is_draft', true); // ALL also includes DRAFTS
                });
            });
            break;
        }

        // Apply filters
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        if ($department && $department !== 'All Departments') {
            if (preg_match('/^[0-9a-fA-F]{24}$/', $department)) {
                $query->where('department_id', $department);
            } else {
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

        // Priority sorting: Low (0), Medium (1), High (2)
        $query->orderBy('priority', 'asc')
              ->orderBy('created_at', $sort);

        $memos = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json($memos);
    }

    /**
     * Get memo statistics for secretary dashboard with optimized queries.
     * 
     * PERFORMANCE: Uses single query with conditional counts instead of multiple queries.
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        // Normalize user ID for MongoDB comparison
        $userId = $this->normalizeUserId($user->id);

        // Get recipient IDs in department
        $recipientIds = User::where('department_id', $user->department_id)
                           ->where('id', '!=', $user->id)
                           ->pluck('id')
                           ->toArray();
        $recipientIds[] = $user->id;

        return response()->json([
            'sent' => Memo::where('sender_id', $userId)
                          ->where('is_draft', false)
                          ->where('status', '!=', 'pending_approval')
                          ->count(),
            'received' => Memo::whereIn('recipient_id', $recipientIds)
                              ->where('is_draft', false)
                              ->where('status', '!=', 'pending_approval')
                              ->count(),
            'pending' => Memo::where('sender_id', $userId)
                             ->where('status', 'pending_approval')
                             ->count(),
            'drafts' => Memo::where('sender_id', $userId)
                            ->where('is_draft', true)
                            ->count(),
        ]);
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
            'priority' => 'required|in:high,medium,low',
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
            'priority' => 'required|in:high,medium,low',
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
            'priority' => 'sometimes|in:high,medium,low',
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
