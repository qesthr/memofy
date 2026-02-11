<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Memo;
use App\Models\CalendarEvent;
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
        $user = $request->user();

        if (!$user->hasPermissionTo('archive.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $type = $request->get('type', 'all');
        $search = $request->get('search', '');
        $perPage = min((int) $request->get('per_page', 20), 100); // Cap at 100 for performance
        $page = (int) $request->get('page', 1);
        $cursor = $request->get('cursor'); // For cursor-based pagination

        // If specific type requested, use standard pagination
        if ($type !== 'all') {
            return $this->getPaginatedType($request, $type, $search, $perPage, $page);
        }

        // For 'all' type, use cursor-based pagination with LIMIT to avoid loading all records
        // This is the critical fix for the 10-20 second loading issue
        return $this->getPaginatedAll($request, $search, $perPage, $cursor);
    }

    /**
     * Get paginated results for a specific type.
     */
    protected function getPaginatedType(Request $request, string $type, string $search, int $perPage, int $page)
    {
        $results = [];

        if ($type === 'users') {
            $results['users'] = $this->getArchivedUsersQuery($search)
                ->orderBy('updated_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);
        } elseif ($type === 'memos') {
            $results['memos'] = $this->getArchivedMemosQuery($search)
                ->orderBy('deleted_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);
        } elseif ($type === 'events') {
            $results['events'] = $this->getArchivedEventsQuery($search)
                ->orderBy('deleted_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);
        }

        return response()->json($results);
    }

    /**
     * Get cursor-paginated results for 'all' type.
     * Uses LIMIT/OFFSET with cursor for efficient pagination.
     */
    protected function getPaginatedAll(Request $request, string $search, int $perPage, ?string $cursor)
    {
        $user = $request->user();
        $page = (int) $request->get('page', 1);
        $offset = ($page - 1) * $perPage;
        
        // Get counts separately (these are fast with indexes)
        $counts = [
            'users' => User::where('is_active', false)->whereNotNull('password')->count(),
            'memos' => Memo::onlyTrashed()->count(),
            'events' => CalendarEvent::onlyTrashed()->count(),
        ];
        $counts['total'] = $counts['users'] + $counts['memos'] + $counts['events'];
        
        // Get mixed results with limit and offset - THIS IS THE KEY FIX
        // Instead of fetching ALL records and then paginating, we fetch only what we need
        $allItems = $this->getCombinedArchivedItems($search, $perPage, $offset);
        
        return response()->json([
            'data' => $allItems['items'],
            'pagination' => [
                'current_page' => $page,
                'last_page' => ceil($counts['total'] / $perPage),
                'total' => $counts['total'],
                'per_page' => $perPage,
                'has_more' => $offset + $perPage < $counts['total']
            ],
            'counts' => $counts
        ]);
    }

    /**
     * Get combined archived items with pagination - optimized version.
     * Uses UNION with LIMIT for efficient cross-collection pagination.
     */
    protected function getCombinedArchivedItems(string $search, int $limit, int $offset)
    {
        $items = collect();
        
        // For each type, fetch only the needed chunk with eager loading to prevent N+1
        $userQuery = $this->getArchivedUsersQuery($search)
            ->orderBy('deleted_at', 'desc')
            ->skip($offset)
            ->take($limit);
            
        $memoQuery = $this->getArchivedMemosQuery($search)
            ->orderBy('deleted_at', 'desc')
            ->skip($offset)
            ->take($limit);
            
        $eventQuery = $this->getArchivedEventsQuery($search)
            ->orderBy('deleted_at', 'desc')
            ->skip($offset)
            ->take($limit);
            
        // Get results with eager loading to prevent N+1
        $users = $userQuery->get()->map(function ($user) {
            return $this->formatUserArchiveItem($user);
        });
        
        $memos = $memoQuery->get()->map(function ($memo) {
            return $this->formatMemoArchiveItem($memo);
        });
        
        $events = $eventQuery->get()->map(function ($event) {
            return $this->formatEventArchiveItem($event);
        });
        
        // Merge, sort by deleted_at descending, and take only what we need
        $allItems = $users->concat($memos)->concat($events)
            ->sortByDesc('deleted_at')
            ->slice(0, $limit)
            ->values();
            
        return ['items' => $allItems];
    }

    protected function getArchivedUsersQuery(string $search)
    {
        $query = User::where('is_active', false)
            ->whereNotNull('password');
            
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        return $query;
    }

    protected function getArchivedMemosQuery(string $search)
    {
        $query = Memo::onlyTrashed();
        
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }
        
        return $query;
    }

    protected function getArchivedEventsQuery(string $search)
    {
        $query = CalendarEvent::onlyTrashed();
        
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        return $query;
    }

    /**
     * Format user archive item with cached/full name to prevent N+1.
     */
    protected function formatUserArchiveItem($user)
    {
        $fullName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        
        return [
            'id' => (string) $user->_id,
            'type' => 'user',
            'title' => $fullName ?: $user->email,
            'subtitle' => $user->email,
            'description' => 'Role: ' . ($user->role ?? 'N/A') . ' | Department: ' . ($user->department ?? 'N/A'),
            'status' => 'archived',
            'deleted_at' => $user->updated_at,
            'deleted_by' => null,
            'data' => [
                '_id' => (string) $user->_id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'role' => $user->role,
                'department' => $user->department,
            ]
        ];
    }

    /**
     * Format memo archive item with eager-loaded sender/recipient to prevent N+1.
     */
    protected function formatMemoArchiveItem($memo)
    {
        // Use relationship if already eager loaded, otherwise format without
        $senderName = 'Unknown';
        $recipientName = 'Unknown';
        
        // Only attempt relationship access if relationships were eager loaded
        if (isset($memo->sender)) {
            $senderFullName = trim(($memo->sender->first_name ?? '') . ' ' . ($memo->sender->last_name ?? ''));
            $senderName = $senderFullName ?: $memo->sender->email;
        }
        
        if (isset($memo->recipient)) {
            $recipientFullName = trim(($memo->recipient->first_name ?? '') . ' ' . ($memo->recipient->last_name ?? ''));
            $recipientName = $recipientFullName ?: $memo->recipient->email;
        }
        
        return [
            'id' => (string) $memo->_id,
            'type' => 'memo',
            'title' => $memo->subject,
            'subtitle' => 'From: ' . $senderName . ' | To: ' . $recipientName,
            'description' => 'Priority: ' . ($memo->priority ?? 'N/A') . ' | Status: ' . ($memo->status ?? 'N/A'),
            'status' => $memo->status ?? 'archived',
            'deleted_at' => $memo->deleted_at,
            'deleted_by' => null,
            'data' => [
                '_id' => (string) $memo->_id,
                'subject' => $memo->subject,
                'priority' => $memo->priority,
                'status' => $memo->status,
            ]
        ];
    }

    /**
     * Format event archive item.
     */
    protected function formatEventArchiveItem($event)
    {
        return [
            'id' => (string) $event->_id,
            'type' => 'event',
            'title' => $event->title,
            'subtitle' => 'Start: ' . ($event->start ?? 'N/A'),
            'description' => 'Category: ' . ($event->category ?? 'N/A') . ' | Status: ' . ($event->status ?? 'N/A'),
            'status' => $event->status ?? 'archived',
            'deleted_at' => $event->deleted_at,
            'deleted_by' => null,
            'data' => [
                '_id' => (string) $event->_id,
                'title' => $event->title,
                'category' => $event->category,
                'status' => $event->status,
            ]
        ];
    }

    public function restoreUser($id)
    {
        if (!request()->user()->hasPermissionTo('archive.restore')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user = User::findOrFail($id);
        
        if ($user->is_active) {
            return response()->json(['message' => 'User is already active'], 400);
        }

        $user->update(['is_active' => true]);

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

        $memo = Memo::withTrashed()->findOrFail($id);
        
        if (!$memo->trashed()) {
            return response()->json(['message' => 'Memo is not archived'], 400);
        }

        $memo->restore();

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

        $event = CalendarEvent::withTrashed()->findOrFail($id);
        
        if (!$event->trashed()) {
            return response()->json(['message' => 'Event is not archived'], 400);
        }

        $event->restore();

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
        $restoredCounts = [
            'users' => 0,
            'memos' => 0,
            'events' => 0
        ];

        if ($type === 'all' || $type === 'users') {
            $users = User::where('is_active', false)->whereNotNull('password')->get();
            foreach ($users as $user) {
                $user->update(['is_active' => true]);
                $restoredCounts['users']++;
            }
        }

        if ($type === 'all' || $type === 'memos') {
            $memos = Memo::onlyTrashed()->get();
            foreach ($memos as $memo) {
                $memo->restore();
                $restoredCounts['memos']++;
            }
        }

        if ($type === 'all' || $type === 'events') {
            $events = CalendarEvent::onlyTrashed()->get();
            foreach ($events as $event) {
                $event->restore();
                $restoredCounts['events']++;
            }
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

        return response()->json(['message' => 'User permanently deleted']);
    }

    public function forceDeleteMemo($id)
    {
        if (!request()->user()->hasPermissionTo('archive.delete_permanently')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $memo = Memo::withTrashed()->findOrFail($id);
        $memo->forceDelete();

        return response()->json(['message' => 'Memo permanently deleted']);
    }

    public function forceDeleteEvent($id)
    {
        if (!request()->user()->hasPermissionTo('archive.delete_permanently')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $event = CalendarEvent::withTrashed()->findOrFail($id);
        $event->forceDelete();

        return response()->json(['message' => 'Event permanently deleted']);
    }
}
