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

        $query = Memo::with(['sender', 'recipient']);

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
                $query->where('sender_id', $user->id)
                      ->where('is_draft', true);
                break;

            default:
                // Received: memos sent to users in secretary's department
                $recipientIds = User::where('department', $user->department)
                                   ->where('id', '!=', $user->id)
                                   ->pluck('id')
                                   ->toArray();
                
                // Also include memos sent directly to the secretary
                $recipientIds[] = $user->id;
                
                $query->whereIn('recipient_id', $recipientIds)
                      ->where('is_draft', false)
                      ->where('status', '!=', 'pending_approval');
                break;
        }

        $memos = $query->orderBy('created_at', 'desc')
                       ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($memos);
    }

    /**
     * Get memo statistics for secretary dashboard
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        // Get recipient IDs in department
        $recipientIds = User::where('department', $user->department)
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
            $recipient = User::find($validated['recipient_id']);
            if (!$recipient || $recipient->department_id !== $user->department_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only send memos to users in your department.'
                ], 422);
            }
            $userIds = [$validated['recipient_id']];
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
        $recipientIds = User::where('department', $user->department)
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
            'recipient_id' => 'nullable|exists:users,id',
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
            'recipient_id' => $validated['recipient_id'] ?? null,
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
}
