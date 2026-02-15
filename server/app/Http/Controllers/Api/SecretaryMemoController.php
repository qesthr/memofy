<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Memo;
use App\Models\MemoAcknowledgment;
use App\Models\User;

use App\Models\Department;
use App\Models\UserSignature;
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
        
        \Illuminate\Support\Facades\Log::info('SecretaryMemoController Request', [
            'userId' => $user->id,
            'scope' => $scope,
            'params' => $request->all()
        ]);
        
        // Filter parameters
        $search = $request->get('search');
        $department = $request->get('department');
        $priority = $request->get('priority');
        $sort = $request->get('sort', 'desc');
        $date = $request->get('date');

        // Eager load with selective columns to reduce data transfer
        $query = Memo::with([
            'sender:_id,id,first_name,last_name,email,role,department,department_id',
            'recipient:_id,id,first_name,last_name,email,role,department,department_id',
            'department:_id,id,name'
        ]);
        
        // Normalize user ID for MongoDB comparison
        $userId = $this->normalizeUserId($user->id);

        switch ($scope) {
            case 'sent':
                $query->whereIn('sender_id', [$userId, (string)$user->id])
                      ->whereIn('status', ['sent', 'archived']); // Include archived
                break;

            case 'pending':
                $query->whereIn('sender_id', [$userId, (string)$user->id])
                      ->where('status', 'pending_approval'); // STRICT: Already strict
                break;

            case 'received':
                // RECEIVED: Memos where this secretary is a recipient
                // Check both direct recipient_id AND MemoAcknowledgment records
                $memoIdsFromAcknowledgments = MemoAcknowledgment::whereIn('recipient_id', [$userId, (string)$user->id])
                                                                 ->pluck('memo_id')
                                                                 ->toArray();
                
                $query->where(function ($q) use ($userId, $memoIdsFromAcknowledgments, $user) {
                    $q->whereIn('recipient_id', [$userId, (string)$user->id])  // Direct recipient
                      ->orWhereIn('_id', $memoIdsFromAcknowledgments); // Via acknowledgment record
                })
                ->where('sender_id', '!=', $userId) // Exclude memos sent by this user
                ->where('sender_id', '!=', (string)$user->id); // Exclude via string ID too
                
                $query->whereIn('status', ['sent', 'read', 'acknowledged', 'archived']);
                break;

            default:
            // ALL: Combination of sent and received memos
            $recipientIds = User::whereIn('department_id', [$user->department_id, $this->normalizeUserId($user->department_id)])
                               ->where('_id', '!=', $userId)
                               ->pluck('id')
                               ->toArray();
                
            $recipientIds[] = $user->id;
            
            // Also get memo IDs from acknowledgments
            $memoIdsFromAcknowledgments = MemoAcknowledgment::whereIn('recipient_id', [$userId, (string)$user->id])
                                                             ->pluck('memo_id')
                                                             ->toArray();
                
            $query->where(function ($q) use ($userId, $recipientIds, $memoIdsFromAcknowledgments, $user) {
                // Memos sent by this user
                $q->whereIn('sender_id', [$userId, (string)$user->id])
                  // Or memos sent to this user or their department
                  ->orWhereIn('recipient_id', $recipientIds)
                  // Or memos where user has an acknowledgment record
                  ->orWhereIn('_id', $memoIdsFromAcknowledgments);
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
        $deptId = $user->department_id;
        $normDeptId = $this->normalizeUserId($deptId);

        $recipientIds = User::whereIn('department_id', [$deptId, $normDeptId])
                           ->where('id', '!=', $user->id)
                           ->pluck('id')
                           ->toArray();
        $recipientIds[] = $user->id;

        return response()->json([
            'sent' => Memo::whereIn('sender_id', [$userId, (string)$user->id])
                          ->where('status', '!=', 'pending_approval')
                          ->count(),
            'received' => Memo::whereIn('recipient_id', $recipientIds)
                              ->where('status', '!=', 'pending_approval')
                              ->count(),
            'pending' => Memo::whereIn('sender_id', [$userId, (string)$user->id])
                             ->where('status', 'pending_approval')
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

            'scheduled_send_at' => 'nullable|date',
            'schedule_end_at' => 'nullable|date',
            'all_day_event' => 'nullable|boolean',
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

        $memo = Memo::create([
        'created_by' => $user->id,
        'sender_id' => $user->id,
        'recipient_id' => null, // Multi-recipient
        'recipient_ids' => $userIds,
        'department_id' => $request->department_id ?? null,
        'subject' => $validated['subject'],
        'message' => $validated['message'],
        'priority' => $validated['priority'],
        'attachments' => $validated['attachments'] ?? [],
        'status' => 'pending_approval', // Set to pending approval

        'version' => 1,
        'scheduled_send_at' => $validated['scheduled_send_at'] ?? null,
        'schedule_end_at' => $validated['schedule_end_at'] ?? null,
        'all_day_event' => $validated['all_day_event'] ?? false,
        'attachment_path' => $validated['attachment_path'] ?? null
    ]);

    // Create calendar event if scheduled (for creator's calendar reminder)
    if ($memo->scheduled_send_at) {
        $this->createCalendarEventForMemo($memo, $user->id);
    }

    $action = 'submit_memo_for_approval';
    $this->activityLogger->logUserAction(
        $user, 
        $action, 
        "Memo '{$memo->subject}' submitted for approval", 
        $this->activityLogger->extractRequestInfo($request)
    );

    return response()->json([
        'status' => 'success',
        'message' => 'Memo submitted for Admin approval',
        'data' => $memo
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

        // Get total recipient IDs based on memo configuration
        if ($memo->recipient_ids && is_array($memo->recipient_ids)) {
            $recipientIds = $memo->recipient_ids;
        } elseif ($memo->department_id) {
            $normDeptId = $this->normalizeUserId($memo->department_id);
            $recipientIds = User::whereIn('department_id', [$memo->department_id, $normDeptId])
                              ->where('id', '!=', $memo->sender_id)
                              ->pluck('id')
                              ->toArray();
        } elseif ($memo->recipient_id) {
            $recipientIds = [$memo->recipient_id];
        } else {
            $recipientIds = [];
        }

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

        if ($memo->status !== 'pending_approval') {
            return response()->json(['message' => 'Only pending memos can be deleted'], 422);
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
                        $query->where('status', 'pending_approval');
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
     * Create a calendar event for a scheduled memo
     */
    private function createCalendarEventForMemo($memo, $userId)
    {
        $calendarEvent = \App\Models\CalendarEvent::updateOrCreate(
            ['memo_id' => $memo->id],
            [
                'title' => "[Scheduled] " . $memo->subject,
                'description' => $memo->message,
                'start' => $memo->scheduled_send_at,
                'end' => $memo->schedule_end_at ?? $memo->scheduled_send_at,
                'all_day' => $memo->all_day_event ?? false,
                'category' => $this->mapPriorityToCategory($memo->priority),
                'created_by' => $userId,
                'status' => 'scheduled',
                'source' => 'MEMO'
            ]
        );

        // Clear and add participants: only the creator for now as it's pending approval
        \App\Models\CalendarEventParticipant::where('calendar_event_id', $calendarEvent->id)->delete();
        
        \App\Models\CalendarEventParticipant::create([
            'calendar_event_id' => $calendarEvent->id,
            'user_id' => $userId,
            'status' => 'accepted'
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



        

}
