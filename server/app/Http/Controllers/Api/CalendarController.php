<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\CalendarEventParticipant;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Api\GoogleCalendarController;
use MongoDB\BSON\ObjectId;

class CalendarController extends Controller
{
    protected $activityLogger;
    protected $notificationService;

    public function __construct(ActivityLogger $activityLogger, NotificationService $notificationService)
    {
        $this->activityLogger = $activityLogger;
        $this->notificationService = $notificationService;
    }
    /**
     * Display a listing of the resource with pagination.
     * 
     * PERFORMANCE CRITICAL: Previously fetched ALL events without pagination,
     * causing slow loading times. Now uses cursor-based pagination.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $start = $request->input('start');
        $end = $request->input('end');
        $perPage = min((int) $request->get('per_page', 50), 200); // Cap at 200
        $page = (int) $request->get('page', 1);
        
        // For calendar views, we typically need all events in a date range
        // Convert to Carbon objects for MongoDB type matching
        $startDate = $start ? \Carbon\Carbon::parse($start)->startOfDay() : null;
        $endDate = $end ? \Carbon\Carbon::parse($end)->endOfDay() : null;

        $offset = ($page - 1) * $perPage;

        // Normalize user ID for MongoDB comparison
        $userId = $this->normalizeUserId($user->id);

        // 1. Get Memofy Events with pagination
        $memofyEventsQuery = CalendarEvent::where(function ($query) use ($userId, $user) {
                $query->whereIn('created_by', [$userId, (string)$user->id])
                      ->orWhereHas('participants', function ($pQuery) use ($userId, $user) {
                          $pQuery->whereIn('user_id', [$userId, (string)$user->id]);
                      });
            })
            ->with(['creator', 'participants.user'])
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->where(function($q) use ($startDate, $endDate) {
                    $q->whereBetween('start', [$startDate, $endDate])
                      ->orWhereBetween('end', [$startDate, $endDate])
                      ->orWhere(function($inner) use ($startDate, $endDate) {
                          $inner->where('start', '<', $startDate)
                                ->where('end', '>', $endDate);
                      });
                });
            });
        
        // Get total count for pagination info
        $totalMemofyEvents = $memofyEventsQuery->count();
        
        // Apply pagination
        $memofyEvents = $memofyEventsQuery->orderBy('start', 'asc')
            ->skip($offset)
            ->take($perPage)
            ->get();

        // 2. Fetch "Discovery" events (Directly from Memos)
        // These are not CalendarEvent records yet, but should appear on the calendar
        $discoveryEvents = [];
        
        // A. For Admins/Secretaries: Pending Approval Memos
        if ($user->role === 'admin' || $user->role === 'secretary') {
            $pendingMemosQuery = \App\Models\Memo::where('status', 'pending_approval');
            
            // Filter by date range if provided
            if ($startDate && $endDate) {
                // Pending memos are shown based on their creation date
                $pendingMemosQuery->whereBetween('created_at', [$startDate, $endDate]);
            }

            // Secretaries only see pending from their department
            if ($user->role === 'secretary') {
                $pendingMemosQuery->where('department_id', $user->department_id);
            }

            $pendingMemos = $pendingMemosQuery->get();
            foreach ($pendingMemos as $memo) {
                $discoveryEvents[] = [
                    'id' => 'discovery-pending-' . $memo->id,
                    'title' => "[Pending Approval] " . $memo->subject,
                    'start' => $memo->created_at->format('Y-m-d\TH:i:s'),
                    'end' => $memo->created_at->addHour()->format('Y-m-d\TH:i:s'),
                    'allDay' => false,
                    'color' => $this->getEventColor('pending'), // Or use priority if available
                    'description' => $memo->message,
                    'source' => 'DISCOVERY',
                    'category' => 'pending',
                    'priority' => $memo->priority ?? 'medium',
                    'memo_id' => $memo->id,
                    'is_editable' => false,
                    'type' => 'memo'
                ];
            }
        }

        // B. For all users: Deadlines (Memos they need to acknowledge)
        $awaitingAcks = \App\Models\MemoAcknowledgment::where('recipient_id', (string)$user->id)
                                                      ->where('is_acknowledged', false)
                                                      ->whereHas('memo', function($q) use ($startDate, $endDate) {
                                                          if ($startDate && $endDate) {
                                                              $q->whereBetween('deadline_at', [$startDate, $endDate]);
                                                          }
                                                      })
                                                      ->with('memo')
                                                      ->get();
        
        foreach ($awaitingAcks as $ack) {
            $memo = $ack->memo;
            if ($memo && $memo->deadline_at) {
                $discoveryEvents[] = [
                    'id' => 'discovery-deadline-' . $memo->id,
                    'title' => "[Deadline] " . $memo->subject,
                    'start' => $memo->deadline_at->format('Y-m-d\TH:i:s'),
                    'end' => $memo->deadline_at->format('Y-m-d\TH:i:s'),
                    'allDay' => true,
                    'color' => $this->getEventColor('deadline'),
                    'description' => $memo->message,
                    'source' => 'DISCOVERY',
                    'category' => 'deadline',
                    'priority' => $memo->priority ?? 'high', // Deadlines usually high
                    'memo_id' => $memo->id,
                    'is_editable' => false,
                    'type' => 'memo'
                ];
            }
        }

        // 3. Format Memofy Events
        $formattedMemofyEvents = $memofyEvents->map(function ($event) use ($user) {
                return $this->formatCalendarEvent($event, $user);
            })->toArray();

        // 4. Get Google Events if connected
        $googleEvents = [];
        if ($user->google_calendar_token) {
            $cacheKey = "google_calendar_events_{$user->id}_" . md5($start . $end);
            
            $googleEvents = Cache::remember($cacheKey, 300, function () use ($user, $start, $end) {
                try {
                    $googleController = new GoogleCalendarController();
                    $googleRequest = new Request([
                        'start' => $start,
                        'end' => $end
                    ]);
                    $googleRequest->setUserResolver(fn() => $user);
                    
                    $response = $googleController->listEvents($googleRequest);
                    return json_decode($response->getContent(), true) ?: [];
                } catch (\Exception $e) {
                    \Log::error("Google Calendar Sync Error: " . $e->getMessage());
                    return [];
                }
            });

            foreach ($googleEvents as &$ge) {
                $ge['source'] = 'GOOGLE';
                $ge['is_editable'] = false;
            }
        }

        // 5. Merge results
        $allEvents = array_merge($formattedMemofyEvents, $discoveryEvents, $googleEvents);
        
        // Sort by start date
        usort($allEvents, function ($a, $b) {
            return strtotime($a['start']) - strtotime($b['start']);
        });

        return response()->json([
            'events' => $allEvents,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_memofy_events' => $totalMemofyEvents,
                'total_google_events' => count($googleEvents),
                'has_more' => $offset + $perPage < $totalMemofyEvents
            ]
        ]);
    }

    /**
     * Format calendar event for response.
     */
    protected function formatCalendarEvent($event, $user)
    {
        $event->source = 'MEMOFY';
        $event->is_editable = $event->created_by === $user->id;
        
        if (!$event->is_editable) {
            $participant = $event->participants->where('user_id', $user->id)->first();
            $event->invitation_status = $participant ? $participant->status : null;
        }
        
        $creatorData = null;
        if ($event->creator) {
            $creatorData = [
                'id' => $event->creator->id,
                'first_name' => $event->creator->first_name,
                'last_name' => $event->creator->last_name,
            ];
        }
        
        $participantsData = $event->participants->map(function ($p) {
            $userData = null;
            if ($p->user) {
                $userData = [
                    'id' => $p->user->id,
                    'first_name' => $p->user->first_name,
                    'last_name' => $p->user->last_name,
                ];
            }
            return [
                'id' => $p->id,
                'user_id' => $p->user_id,
                'status' => $p->status,
                'user' => $userData,
            ];
        });
        
        return [
            'id' => $event->id,
            'title' => $event->title,
            'start' => $event->start,
            'end' => $event->end,
            'allDay' => $event->all_day,
            'color' => $this->getEventColor($event->category, $event->priority),
            'description' => $event->description ?? '',
            'source' => $event->source ?? 'MEMOFY',
            'is_editable' => $event->is_editable,
            'invitation_status' => $event->invitation_status ?? null,
            'category' => $event->category,
            'priority' => $event->priority,
            'memo_id' => $event->memo_id,
            'creator' => $creatorData,
            'participants' => $participantsData,
        ];
    }

    /**
     * Get event color based on category.
     */
    protected function getEventColor($category, $priority = null)
    {
        // Priority-based colors (Match sidebar)
        if ($priority) {
            switch ($priority) {
                case 'high': return '#F44336';   // Red
                case 'medium': return '#FF9800'; // Orange
                case 'low': return '#4CAF50';    // Green
            }
        }

        $colors = [
            'urgent' => '#F44336',    // Red
            'high' => '#F44336',      // Red
            'meeting' => '#3B82F6',    // Blue
            'deadline' => '#F44336',   // Red
            'reminder' => '#8B5CF6',   // Purple
            'standard' => '#FF9800',   // Orange (Medium default)
            'low' => '#4CAF50',        // Green
            'pending' => '#FF9800',    // Orange (Pending/Medium)
        ];
        
        return $colors[$category] ?? '#3B82F6'; // Default blue
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
            'all_day' => 'boolean',
            'category' => 'nullable|string',
            'priority' => 'nullable|string|in:low,medium,high',
            'description' => 'nullable|string',
            'invited_users' => 'nullable|array',
            'invited_users.*' => 'exists:users,id',
            'memo_id' => 'nullable|exists:memos,id',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $event = CalendarEvent::create([
                'title' => $validated['title'],
                'start' => $validated['start'],
                'end' => $validated['end'],
                'all_day' => $validated['all_day'] ?? false,
                'category' => $validated['category'] ?? 'standard',
                'priority' => $validated['priority'] ?? 'medium',
                'description' => $validated['description'] ?? null,
                'memo_id' => $validated['memo_id'] ?? null,
                'created_by' => $request->user()->id,
                'status' => 'scheduled',
                'source' => 'MEMOFY'
            ]);

            $this->activityLogger->logUserAction($request->user(), 'create_calendar_event', $event, $this->activityLogger->extractRequestInfo($request));

            // Add participants and send notifications
            if (!empty($validated['invited_users'])) {
                $invitedUsers = [];
                foreach ($validated['invited_users'] as $userId) {
                    if ($userId != $request->user()->id) { // Don't invite self
                        CalendarEventParticipant::create([
                            'calendar_event_id' => $event->id,
                            'user_id' => $userId,
                            'status' => 'pending'
                        ]);
                        $invitedUsers[] = User::find($userId);
                    }
                }
                
                // Send calendar invitation notifications
                $this->notificationService->notifyCalendarInvitation($request->user(), $event, $invitedUsers);
            }

            // Sync to Google
            try {
                $googleController = new GoogleCalendarController();
                $googleController->syncEventToParticipants($event, 'create');
            } catch (\Exception $e) {
                \Log::error("Immediate Google Sync Error: " . $e->getMessage());
            }

            return response()->json([
                'message' => 'Event created successfully',
                'event' => $event->load(['creator', 'participants.user'])
            ], 201);
        });
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CalendarEvent $event)
    {
        if ($event->created_by !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'start' => 'sometimes|required|date',
            'end' => 'sometimes|required|date|after_or_equal:start',
            'all_day' => 'boolean',
            'category' => 'nullable|string',
            'priority' => 'nullable|string|in:low,medium,high',
            'description' => 'nullable|string',
            'invited_users' => 'nullable|array',
            'invited_users.*' => 'exists:users,id',
            'memo_id' => 'nullable|exists:memos,id',
        ]);

        return DB::transaction(function () use ($validated, $event, $request) {
            $event->update($validated);

            $this->activityLogger->logUserAction($request->user(), 'update_calendar_event', $event, $this->activityLogger->extractRequestInfo($request));

            // Sync participants if provided
            if (isset($validated['invited_users'])) {
                // Delete removed participants
                CalendarEventParticipant::where('calendar_event_id', $event->id)
                    ->whereNotIn('user_id', $validated['invited_users'])
                    ->delete();

                // Add new ones and collect for notification
                $newInvitedUsers = [];
                foreach ($validated['invited_users'] as $userId) {
                    if ($userId != $request->user()->id) {
                        $existing = CalendarEventParticipant::where('calendar_event_id', $event->id)
                            ->where('user_id', $userId)
                            ->first();
                        
                        if (!$existing) {
                            CalendarEventParticipant::create([
                                'calendar_event_id' => $event->id,
                                'user_id' => $userId,
                                'status' => 'pending'
                            ]);
                            $newInvitedUsers[] = User::find($userId);
                        }
                    }
                }
                
                // Send notifications to newly invited users
                if (!empty($newInvitedUsers)) {
                    $this->notificationService->notifyCalendarInvitation($request->user(), $event, $newInvitedUsers);
                }
            }
            
            // Notify existing participants about event update
            $this->notificationService->notifyCalendarUpdated($request->user(), $event);

            // Sync to Google
            try {
                $googleController = new GoogleCalendarController();
                $googleController->syncEventToParticipants($event, 'update');
            } catch (\Exception $e) {
                \Log::error("Immediate Google Sync Error (Update): " . $e->getMessage());
            }

            return response()->json([
                'message' => 'Event updated successfully',
                'event' => $event->load(['creator', 'participants.user'])
            ]);
        });
    }

    /**
     * Remove the specified resource in storage.
     */
    public function destroy(Request $request, CalendarEvent $event)
    {
        $userId = $this->normalizeUserId($request->user()->id);
        $userStrId = (string)$request->user()->id;

        // If user is a participant but not the creator/admin
        if ($event->created_by !== $userStrId && $request->user()->role !== 'admin') {
            $participant = CalendarEventParticipant::where('calendar_event_id', $event->id)
                ->whereIn('user_id', [$userId, $userStrId])
                ->first();

            if ($participant) {
                // Participant is "archiving" the event for themselves
                $participant->delete();
                return response()->json(['message' => 'Event removed from your calendar']);
            }

            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return DB::transaction(function () use ($event, $request) {
            // Set archive metadata in centralized archives collection
            \App\Models\Archive::create([
                'item_id' => (string) $event->id,
                'item_type' => 'event',
                'archived_by' => (string) $request->user()->id,
                'archived_at' => now(),
                'created_by' => (string) $event->created_by,
                'payload' => $event->toArray()
            ]);

            // Sync to Google
            try {
                $googleController = new GoogleCalendarController();
                $googleController->syncEventToParticipants($event, 'delete');
            } catch (\Exception $e) {
                \Log::error("Immediate Google Sync Error (Delete): " . $e->getMessage());
            }

            $this->activityLogger->logUserAction($request->user(), 'delete_calendar_event', $event, $this->activityLogger->extractRequestInfo($request));
            
            $event->delete();

            return response()->json(['message' => 'Event archived successfully']);
        });
    }

    /**
     * Respond to an invitation.
     */
    public function respond(Request $request, CalendarEvent $event)
    {
        $validated = $request->validate([
            'status' => 'required|in:accepted,declined'
        ]);

        $userId = $this->normalizeUserId($request->user()->id);

        $participant = CalendarEventParticipant::where('calendar_event_id', $event->id)
            ->whereIn('user_id', [$userId, (string)$request->user()->id])
            ->firstOrFail();

        if ($participant->status !== 'pending' && $participant->status !== null) {
            return response()->json([
                'message' => 'You have already responded to this invitation.'
            ], 422);
        }

        $participant->update([
            'status' => $validated['status']
        ]);

        $this->activityLogger->logUserAction($request->user(), 'respond_calendar_invitation', $event, [
            'response' => $validated['status'],
            ...$this->activityLogger->extractRequestInfo($request)
        ]);

        // Notify event creator about the response
        $creator = User::find($event->created_by);
        if ($creator && $creator->id !== $request->user()->id) {
            $this->notificationService->notifyCalendarResponse($creator, $event, $request->user(), $validated['status']);
        }

        return response()->json([
            'message' => 'Invitation ' . $validated['status'],
            'status' => $validated['status']
        ]);
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
}
