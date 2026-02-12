<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Draft;
use App\Models\Memo;
use App\Models\User;
use App\Models\Department;
use App\Models\UserSignature;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use MongoDB\BSON\ObjectId;

/**
 * Draft Controller - Handles CRUD operations for memo drafts
 * 
 * SECURITY PRINCIPLE: All queries are STRICTLY filtered by creatorId
 * to ensure users can ONLY access their own draft entries.
 */
class DraftController extends Controller
{
    protected $activityLogger;

    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }

    /**
     * Convert user ID to consistent format for MongoDB comparison
     * 
     * @param mixed $userId
     * @return ObjectId|string
     */
    protected function normalizeUserId($userId)
    {
        if ($userId instanceof ObjectId) {
            return $userId;
        }
        
        // Handle string ObjectId (24 character hex)
        if (is_string($userId) && strlen($userId) === 24 && ctype_xdigit($userId)) {
            try {
                return new ObjectId($userId);
            } catch (\Exception $e) {
                return (string) $userId;
            }
        }
        
        return (string) $userId;
    }

    /**
     * Get all drafts for the authenticated user
     * 
     * STRICT FILTERING: Only returns drafts where creatorId matches authenticated user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $creatorId = $this->normalizeUserId($user->id);
        
        // Pagination parameters
        $perPage = min((int) $request->get('per_page', 15), 50);
        $page = $request->get('page', 1);
        
        // Filter parameters
        $status = $request->get('status');
        $search = $request->get('search');
        $priority = $request->get('priority');
        $sort = $request->get('sort', 'desc');
        
        // STRICT QUERY: Always filter by creatorId first. Eager load for UI efficiency.
        $query = Draft::with(['sender', 'department'])
                      ->where('creatorId', $creatorId);
        
        // Apply optional filters
        if ($status) {
            $query->where('status', $status);
        }
        
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }
        
        if ($priority) {
            $query->where('priority', $priority);
        }
        
        // Sort by most recently updated
        $query->orderBy('updatedAt', $sort);
        
        // Paginate results
        $drafts = $query->paginate($perPage, ['*'], 'page', $page);
        
        // Load related data
        $drafts->getCollection()->transform(function ($draft) {
            return $this->formatDraftResponse($draft);
        });
        
        return response()->json($drafts);
    }

    /**
     * Get a single draft by ID
     * 
     * SECURITY: Verifies that the draft belongs to the authenticated user
     * 
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
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
            'data' => $this->formatDraftResponse($draft)
        ]);
    }

    /**
     * Create a new draft
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'subject' => 'sometimes|string|max:255',
            'message' => 'sometimes|string',
            'priority' => 'nullable|in:high,medium,low',
            'recipientIds' => 'nullable|array',
            'recipientIds.*' => 'string',
            'departmentId' => 'nullable|string',
            'attachments' => 'nullable|array',
            'signatureId' => 'nullable|string',
            'attachmentPath' => 'nullable|string',
            'scheduledSendAt' => 'nullable|date',
            'scheduleEndAt' => 'nullable|date',
            'allDayEvent' => 'nullable|boolean',
            'status' => 'nullable|in:draft,auto_saved',
            'metadata' => 'nullable|array',
        ]);
        
        // Create draft with creatorId from authenticated user
        $draft = Draft::create([
            'creatorId' => $this->normalizeUserId($user->id),  // Set from authenticated user
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
            'status' => $validated['status'] ?? 'draft',
            'metadata' => array_merge(
                $validated['metadata'] ?? [],
                ['editCount' => 0, 'lastEditedAt' => now()->toIso8601String()]
            ),
        ]);
        
        // Log the action
        $this->activityLogger->logUserAction(
            $user,
            'create_draft',
            "Created draft: {$draft->subject}",
            $this->activityLogger->extractRequestInfo($request)
        );
        
        return response()->json([
            'status' => 'success',
            'message' => 'Draft saved successfully',
            'data' => $this->formatDraftResponse($draft)
        ], 201);
    }

    /**
     * Update an existing draft
     * 
     * SECURITY: Verifies that the draft belongs to the authenticated user
     * 
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
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
            'recipientIds.*' => 'string',
            'departmentId' => 'nullable|string',
            'attachments' => 'nullable|array',
            'signatureId' => 'nullable|string',
            'attachmentPath' => 'nullable|string',
            'scheduledSendAt' => 'nullable|date',
            'scheduleEndAt' => 'nullable|date',
            'allDayEvent' => 'nullable|boolean',
            'status' => 'nullable|in:draft,auto_saved',
        ]);
        
        // Update the draft
        $draft->fill($validated);
        $draft->touchMetadata();
        $draft->save();
        
        // Log the action
        $this->activityLogger->logUserAction(
            $user,
            'update_draft',
            "Updated draft: {$draft->subject}",
            $this->activityLogger->extractRequestInfo($request)
        );
        
        return response()->json([
            'status' => 'success',
            'message' => 'Draft updated successfully',
            'data' => $this->formatDraftResponse($draft)
        ]);
    }

    /**
     * Auto-save draft (lighter validation for frequent saves)
     * 
     * SECURITY: Verifies that the draft belongs to the authenticated user
     * 
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function autoSave(Request $request, $id)
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
            'clientVersion' => 'nullable|string',
            'deviceInfo' => 'nullable|string',
        ]);
        
        // Extract metadata fields
        $clientVersion = $validated['clientVersion'] ?? null;
        $deviceInfo = $validated['deviceInfo'] ?? null;
        unset($validated['clientVersion'], $validated['deviceInfo']);
        
        // Update the draft
        $draft->fill($validated);
        $draft->markAutoSaved($clientVersion, $deviceInfo);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Draft auto-saved',
            'data' => [
                'id' => (string) $draft->_id,
                'updatedAt' => $draft->updatedAt,
                'autoSavedAt' => $draft->metadata['autoSavedAt'] ?? null,
            ]
        ]);
    }

    /**
     * Delete a draft
     * 
     * SECURITY: Verifies that the draft belongs to the authenticated user
     * 
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $id)
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
        
        // Soft delete the draft
        $draft->delete();
        
        // Log the action
        $this->activityLogger->logUserAction(
            $user,
            'delete_draft',
            "Deleted draft: {$subject}",
            $this->activityLogger->extractRequestInfo($request)
        );
        
        return response()->json([
            'status' => 'success',
            'message' => 'Draft deleted successfully'
        ]);
    }

    /**
     * Bulk delete drafts
     * 
     * SECURITY: Only deletes drafts belonging to the authenticated user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDestroy(Request $request)
    {
        $user = $request->user();
        $creatorId = $this->normalizeUserId($user->id);
        
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'string'
        ]);
        
        $ids = $validated['ids'];
        
        // STRICT QUERY: Only delete drafts belonging to the user
        $deleted = Draft::whereIn('_id', $ids)
                        ->where('creatorId', $creatorId)
                        ->delete();
        
        // Log the action
        $this->activityLogger->logUserAction(
            $user,
            'bulk_delete_drafts',
            "Bulk deleted {$deleted} draft(s)",
            ['deleted_count' => $deleted, 'requested_ids' => $ids]
        );
        
        return response()->json([
            'status' => 'success',
            'message' => "Deleted {$deleted} draft(s)",
            'data' => [
                'deleted_count' => $deleted
            ]
        ]);
    }

    /**
     * Convert draft to memo and submit
     * 
     * SECURITY: Verifies that the draft belongs to the authenticated user
     * 
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function convertToMemo(Request $request, $id)
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
        
        // Validate memo submission data
        $validated = $request->validate([
            'recipient_ids' => 'required_without:department_id|array',
            'recipient_ids.*' => 'exists:users,id',
            'department_id' => 'required_without:recipient_ids|exists:departments,id',
            'submit_for_approval' => 'boolean',
        ]);
        
        // Get memo data from draft
        $memoData = $draft->toMemoData();
        $memoData['created_by'] = $user->id;
        $memoData['sender_id'] = $user->id;
        $memoData['is_draft'] = false;
        
        // Set status based on user role and submission type
        if ($user->role === 'admin' && !($validated['submit_for_approval'] ?? false)) {
            $memoData['status'] = 'sent';
        } else {
            $memoData['status'] = 'pending_approval';
        }
        
        // Override with request data if provided
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
        
        // Delete the draft after successful conversion
        $draft->delete();
        
        // Log the action
        $this->activityLogger->logUserAction(
            $user,
            'convert_draft_to_memo',
            "Converted draft to memo: {$memo->subject}",
            $this->activityLogger->extractRequestInfo($request)
        );
        
        return response()->json([
            'status' => 'success',
            'message' => 'Draft converted to memo successfully',
            'data' => $memo
        ], 201);
    }

    /**
     * Get draft statistics for the authenticated user
     * 
     * STRICT FILTERING: Only counts drafts where creatorId matches authenticated user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats(Request $request)
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

    /**
     * Format draft response with related data
     * 
     * @param Draft $draft
     * @return array
     */
    protected function formatDraftResponse($draft)
    {
        $response = $draft->toArray();
        
        // Add creator info
        $creator = User::find($draft->creatorId);
        if ($creator) {
            $response['creator'] = [
                'id' => (string) $creator->_id,
                'name' => $creator->first_name . ' ' . $creator->last_name,
                'email' => $creator->email,
            ];
        }
        
        // Add department info
        if ($draft->departmentId) {
            $department = \App\Models\Department::find($draft->departmentId);
            if ($department) {
                $response['department'] = [
                    'id' => (string) $department->_id,
                    'name' => $department->name,
                ];
            }
        }
        
        // Add recipient info
        if (!empty($draft->recipientIds)) {
            $recipients = User::whereIn('_id', $draft->recipientIds)->get();
            $response['recipients'] = $recipients->map(function ($recipient) {
                return [
                    'id' => (string) $recipient->_id,
                    'name' => $recipient->first_name . ' ' . $recipient->last_name,
                    'email' => $recipient->email,
                ];
            });
        }
        
        return $response;
    }
}
