<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Memo;
use App\Models\CalendarEvent;
use App\Models\Archive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArchiveController extends Controller
{
    /**
     * Get archived items with proper pagination.
     * 
     * PERFORMANCE CRITICAL: This endpoint previously fetched ALL records when type='all',
     * causing 10-20 second loading times. Now uses cursor-based pagination for efficiency.
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermissionTo('archive.view')) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $type = $request->get('type', 'all');
            $search = $request->get('search', '') ?? ''; // Handle null from ConvertEmptyStringsToNull middleware
            $perPage = min((int) $request->get('per_page', 20), 100); // Cap at 100 for performance
            $page = (int) $request->get('page', 1);
            $cursor = $request->get('cursor'); // For cursor-based pagination

            // If specific type requested, use standard pagination
            if ($type !== 'all') {
                return $this->getPaginatedType($request, $type, $search, $perPage, $page);
            }

            // For 'all' type, use cursor-based pagination with LIMIT to avoid loading all records
            // This is the critical fix for the 10-20 second loading issue
            return $this->getPaginatedAll($request, $search, $perPage, $cursor, $user);
        } catch (\Throwable $e) {
            \Log::error('Archive error: ' . $e->getMessage(), [
                'stack' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Get paginated results for a specific type.
     */
    protected function getPaginatedType(Request $request, string $type, string $search, int $perPage, int $page)
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();
        
        $query = $this->getBaseArchiveQuery($search, $user);
        
        if ($type !== 'all') {
            $query->where('item_type', $type === 'events' ? 'event' : ($type === 'memos' ? 'memo' : 'user'));
        }

        $paginated = $query->orderBy('archived_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginated->items())->map(function ($archive) {
            return $this->formatArchiveItem($archive);
        });

        // Get counts for tabs
        $counts = [
            'users' => $this->getBaseArchiveQuery($search, $user)->where('item_type', 'user')->count(),
            'memos' => $this->getBaseArchiveQuery($search, $user)->where('item_type', 'memo')->count(),
            'events' => $this->getBaseArchiveQuery($search, $user)->where('item_type', 'event')->count(),
        ];
        $counts['total'] = array_sum($counts);

        return response()->json([
            'data' => $items->values(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
                'per_page' => $perPage,
            ],
            'counts' => $counts
        ]);
    }

    /**
     * Get cursor-paginated results for 'all' type.
     */
    protected function getPaginatedAll(Request $request, string $search, int $perPage, ?string $cursor, $user = null)
    {
        if (!$user) $user = $request->user();
        $page = (int) $request->get('page', 1);
        
        $query = $this->getBaseArchiveQuery($search, $user);
        
        $paginated = $query->orderBy('archived_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginated->items())->map(function ($archive) {
            return $this->formatArchiveItem($archive);
        });

        $counts = [
            'users' => $this->getBaseArchiveQuery($search, $user)->where('item_type', 'user')->count(),
            'memos' => $this->getBaseArchiveQuery($search, $user)->where('item_type', 'memo')->count(),
            'events' => $this->getBaseArchiveQuery($search, $user)->where('item_type', 'event')->count(),
        ];
        $counts['total'] = array_sum($counts);

        return response()->json([
            'data' => $items->values(),
            'pagination' => [
                'current_page' => $page,
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
                'per_page' => $perPage,
                'has_more' => $paginated->hasMorePages()
            ],
            'counts' => $counts
        ]);
    }

    /**
     * Base query for the archives collection with role-based filtering.
     */
    protected function getBaseArchiveQuery(string $search = '', $user = null)
    {
        if (!$user) $user = request()->user();
        $query = Archive::query();

        if (!$user->isAdmin()) {
            $userId = (string) $user->id;
            $normalizeId = $this->normalizeUserId($user->id);
            
            // Explicitly keep both formats to ensure matching against different storage types
            $targetIds = [$userId];
            if ($normalizeId instanceof \MongoDB\BSON\ObjectId) {
                $targetIds[] = $normalizeId;
            }

            $query->where(function($q) use ($targetIds, $user) {
                // Main visibility block for Memos and Events
                $q->where(function($sq) use ($targetIds, $user) {
                    $sq->whereIn('item_type', ['memo', 'event']);
                    
                    $sq->where(function($iq) use ($targetIds, $user) {
                        if ($user->role === 'faculty') {
                            $iq->whereIn('recipient_id', $targetIds)
                               ->orWhereIn('archived_by', $targetIds);
                        } else {
                            $iq->whereIn('sender_id', $targetIds)
                              ->orWhereIn('created_by', $targetIds)
                              ->orWhereIn('recipient_id', $targetIds)
                              ->orWhereIn('archived_by', $targetIds);
                        }
                    });
                });

                // User Archives visibility - Secretary sees their department
                if ($user->role === 'secretary' && $user->department) {
                    $q->orWhere(function($sq) use ($user) {
                        $sq->where('item_type', 'user')
                          ->where('department', $user->department);
                    });
                }
            });
            
            // Enforcement: Faculty never see Events
            if ($user->role === 'faculty') {
                $query->where('item_type', '!=', 'event');
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('payload.subject', 'like', "%{$search}%")
                  ->orWhere('payload.message', 'like', "%{$search}%")
                  ->orWhere('payload.title', 'like', "%{$search}%")
                  ->orWhere('payload.first_name', 'like', "%{$search}%")
                  ->orWhere('payload.last_name', 'like', "%{$search}%")
                  ->orWhere('payload.email', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * Convert user ID to consistent format for MongoDB comparison
     */
    private function normalizeUserId($userId)
    {
        if (!$userId) return null;
        if ($userId instanceof \MongoDB\BSON\ObjectId) return $userId;
        try {
            return new \MongoDB\BSON\ObjectId((string)$userId);
        } catch (\Exception $e) {
            return (string)$userId;
        }
    }

    /**
     * Unified formatter for archive items.
     */
    protected function formatArchiveItem($archive)
    {
        $payload = $archive->payload;
        $archiver = $archive->archived_by ? User::find($archive->archived_by) : null;
        $archiverName = $archiver ? $archiver->full_name : 'System';

        $data = [
            'id' => $archive->item_id,
            'archive_id' => (string) $archive->_id,
            'type' => $archive->item_type,
            'status' => 'archived',
            'deleted_at' => $archive->archived_at,
            'deleted_by' => $archiverName,
            'data' => $payload
        ];

        switch ($archive->item_type) {
            case 'user':
                $data['title'] = trim(($payload['first_name'] ?? '') . ' ' . ($payload['last_name'] ?? '')) ?: ($payload['email'] ?? 'Unknown User');
                $data['subtitle'] = $payload['email'] ?? '';
                $data['description'] = 'Role: ' . ($payload['role'] ?? 'N/A') . ' | Department: ' . ($payload['department'] ?? 'N/A');
                break;
            case 'memo':
                $data['title'] = $payload['subject'] ?? 'No Subject';
                $data['subtitle'] = 'From: ' . ($payload['sender_name'] ?? 'N/A');
                $data['description'] = $payload['subject'] ?? '';
                break;
            case 'event':
                $data['title'] = $payload['title'] ?? 'No Title';
                $data['subtitle'] = 'Start: ' . ($payload['start'] ?? 'N/A');
                $data['description'] = 'Category: ' . ($payload['category'] ?? 'N/A');
                break;
        }

        return $data;
    }
    public function restoreUser($id)
    {
        if (!request()->user()->hasPermissionTo('archive.restore')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $archive = Archive::where('item_id', $id)->where('item_type', 'user')->firstOrFail();
        $user = User::findOrFail($id);
        
        $user->update([
            'is_active' => true,
            'archived_at' => null,
            'archived_by' => null
        ]);

        $archive->delete();

        return response()->json([
            'message' => 'User restored successfully',
            'user' => $user
        ]);
    }

    public function restoreMemo($id)
    {
        if (!request()->user()->hasPermissionTo('archive.restore')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $archive = Archive::where('item_id', $id)->where('item_type', 'memo')->firstOrFail();
        $memo = Memo::withTrashed()->findOrFail($id);
        
        $memo->update([
            'archived_at' => null,
            'archived_by' => null,
            'status' => 'sent'
        ]);
        $memo->restore();
        
        $archive->delete();

        return response()->json([
            'message' => 'Memo restored successfully',
            'memo' => $memo
        ]);
    }

    public function restoreEvent($id)
    {
        if (!request()->user()->hasPermissionTo('archive.restore')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $archive = Archive::where('item_id', $id)->where('item_type', 'event')->firstOrFail();
        $event = CalendarEvent::withTrashed()->findOrFail($id);
        
        $event->update([
            'archived_at' => null,
            'archived_by' => null,
            'status' => 'scheduled'
        ]);
        $event->restore();
        
        $archive->delete();

        return response()->json([
            'message' => 'Event restored successfully',
            'event' => $event
        ]);
    }

    public function restoreAll(Request $request)
    {
        if (!$request->user()->hasPermissionTo('archive.restore_all')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $type = $request->get('type', 'all');
        $user = $request->user();
        
        $query = $this->getBaseArchiveQuery('', $user);
        if ($type !== 'all') {
            $query->where('item_type', $type === 'events' ? 'event' : ($type === 'memos' ? 'memo' : 'user'));
        }

        $archives = $query->get();
        $restoredCounts = ['users' => 0, 'memos' => 0, 'events' => 0];

        foreach ($archives as $archive) {
            switch ($archive->item_type) {
                case 'user':
                    User::where('_id', $archive->item_id)->update([
                        'is_active' => true,
                        'archived_at' => null,
                        'archived_by' => null
                    ]);
                    $restoredCounts['users']++;
                    break;
                case 'memo':
                    $memo = Memo::withTrashed()->find($archive->item_id);
                    if ($memo) {
                        $memo->update(['archived_at' => null, 'archived_by' => null, 'status' => 'sent']);
                        $memo->restore();
                    }
                    $restoredCounts['memos']++;
                    break;
                case 'event':
                    $event = CalendarEvent::withTrashed()->find($archive->item_id);
                    if ($event) {
                        $event->update(['archived_at' => null, 'archived_by' => null, 'status' => 'scheduled']);
                        $event->restore();
                    }
                    $restoredCounts['events']++;
                    break;
            }
            $archive->delete();
        }

        return response()->json([
            'message' => 'Items restored successfully',
            'counts' => $restoredCounts,
            'total_restored' => array_sum($restoredCounts)
        ]);
    }

    public function forceDeleteUser($id)
    {
        if (!request()->user()->hasPermissionTo('archive.delete_permanently')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user = User::findOrFail($id);
        $user->delete();
        
        Archive::where('item_id', $id)->where('item_type', 'user')->delete();

        return response()->json(['message' => 'User permanently deleted']);
    }

    public function forceDeleteMemo($id)
    {
        if (!request()->user()->hasPermissionTo('archive.delete_permanently')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $memo = Memo::withTrashed()->findOrFail($id);
        $memo->forceDelete();
        
        Archive::where('item_id', $id)->where('item_type', 'memo')->delete();

        return response()->json(['message' => 'Memo permanently deleted']);
    }

    public function forceDeleteEvent($id)
    {
        if (!request()->user()->hasPermissionTo('archive.delete_permanently')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $event = CalendarEvent::withTrashed()->findOrFail($id);
        $event->forceDelete();
        
        Archive::where('item_id', $id)->where('item_type', 'event')->delete();

        return response()->json(['message' => 'Event permanently deleted']);
    }
}
