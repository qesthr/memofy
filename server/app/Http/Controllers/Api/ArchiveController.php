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
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermissionTo('archive.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $type = $request->get('type', 'all');
        $search = $request->get('search', '');
        $perPage = $request->get('per_page', 20);

        $results = [];

        if ($type === 'all' || $type === 'users') {
            $usersQuery = User::where('is_active', false)
                ->whereNotNull('password')
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                          ->orWhere('last_name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%");
                    });
                });

            if ($type === 'users') {
                $results['users'] = $usersQuery->orderBy('updated_at', 'desc')->paginate($perPage);
            } else {
                $results['users'] = $usersQuery->orderBy('updated_at', 'desc')->get();
            }
        }

        if ($type === 'all' || $type === 'memos') {
            $memosQuery = Memo::onlyTrashed()
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('subject', 'like', "%{$search}%")
                          ->orWhere('message', 'like', "%{$search}%");
                    });
                });

            if ($type === 'memos') {
                $results['memos'] = $memosQuery->orderBy('deleted_at', 'desc')->paginate($perPage);
            } else {
                $results['memos'] = $memosQuery->orderBy('deleted_at', 'desc')->get();
            }
        }

        if ($type === 'all' || $type === 'events') {
            $eventsQuery = CalendarEvent::onlyTrashed()
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('title', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%");
                    });
                });

            if ($type === 'events') {
                $results['events'] = $eventsQuery->orderBy('deleted_at', 'desc')->paginate($perPage);
            } else {
                $results['events'] = $eventsQuery->orderBy('deleted_at', 'desc')->get();
            }
        }

        if ($type !== 'all') {
            return response()->json($results);
        }

        $allItems = collect();

        foreach ($results['users'] as $user) {
            $fullName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            
            $allItems->push([
                'id' => (string) $user->_id,
                'type' => 'user',
                'title' => $fullName ?: $user->email,
                'subtitle' => $user->email,
                'description' => 'Role: ' . ($user->role ?? 'N/A') . ' | Department: ' . ($user->department ?? 'N/A'),
                'status' => 'archived',
                'deleted_at' => $user->updated_at,
                'deleted_by' => null,
                'data' => $user
            ]);
        }

        foreach ($results['memos'] as $memo) {
            $senderName = 'Unknown';
            $recipientName = 'Unknown';
            
            if ($memo->sender) {
                $senderFullName = trim(($memo->sender->first_name ?? '') . ' ' . ($memo->sender->last_name ?? ''));
                $senderName = $senderFullName ?: $memo->sender->email;
            }
            
            if ($memo->recipient) {
                $recipientFullName = trim(($memo->recipient->first_name ?? '') . ' ' . ($memo->recipient->last_name ?? ''));
                $recipientName = $recipientFullName ?: $memo->recipient->email;
            }
            
            $allItems->push([
                'id' => (string) $memo->_id,
                'type' => 'memo',
                'title' => $memo->subject,
                'subtitle' => 'From: ' . $senderName . ' | To: ' . $recipientName,
                'description' => 'Priority: ' . ($memo->priority ?? 'N/A') . ' | Status: ' . ($memo->status ?? 'N/A'),
                'status' => $memo->status ?? 'archived',
                'deleted_at' => $memo->deleted_at,
                'deleted_by' => null,
                'data' => $memo
            ]);
        }

        foreach ($results['events'] as $event) {
            $allItems->push([
                'id' => (string) $event->_id,
                'type' => 'event',
                'title' => $event->title,
                'subtitle' => 'Start: ' . ($event->start ?? 'N/A'),
                'description' => 'Category: ' . ($event->category ?? 'N/A') . ' | Status: ' . ($event->status ?? 'N/A'),
                'status' => $event->status ?? 'archived',
                'deleted_at' => $event->deleted_at,
                'deleted_by' => null,
                'data' => $event
            ]);
        }

        $allItems = $allItems->sortByDesc('deleted_at');

        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);
        $paginated = $allItems->forPage($page, $perPage);
        $pagination = [
            'current_page' => $page,
            'last_page' => ceil($allItems->count() / $perPage),
            'total' => $allItems->count(),
            'per_page' => $perPage
        ];

        return response()->json([
            'data' => $paginated->values(),
            'pagination' => $pagination,
            'counts' => [
                'users' => User::where('is_active', false)->whereNotNull('password')->count(),
                'memos' => Memo::onlyTrashed()->count(),
                'events' => CalendarEvent::onlyTrashed()->count(),
                'total' => User::where('is_active', false)->whereNotNull('password')->count() 
                        + Memo::onlyTrashed()->count() 
                        + CalendarEvent::onlyTrashed()->count()
            ]
        ]);
    }

    public function restoreUser($id)
    {
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
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'User permanently deleted']);
    }

    public function forceDeleteMemo($id)
    {
        $memo = Memo::withTrashed()->findOrFail($id);
        $memo->forceDelete();

        return response()->json(['message' => 'Memo permanently deleted']);
    }

    public function forceDeleteEvent($id)
    {
        $event = CalendarEvent::withTrashed()->findOrFail($id);
        $event->forceDelete();

        return response()->json(['message' => 'Event permanently deleted']);
    }
}
