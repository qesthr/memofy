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
use App\Http\Controllers\Api\GoogleCalendarController;

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
        // But for very large datasets, we should paginate
        $offset = ($page - 1) * $perPage;

        // 1. Get Memofy Events with pagination
        $memofyEventsQuery = CalendarEvent::where(function ($query) use ($user) {
                $query->where('created_by', $user->id)
                      ->orWhereHas('participants', function ($pQuery) use ($user) {
                          $pQuery->where('user_id', $user->id);
                      });
            })
            ->with(['creator', 'participants.user'])
            ->when($start && $end, function ($query) use ($start, $end) {
                $query->where(function($q) use ($start, $end) {
                    $q->whereBetween('start', [$start . ' 00:00:00', $end . ' 23:59:59'])
                      ->orWhereBetween('end', [$start . ' 00:00:00', $end . ' 23:59:59'])
                      ->orWhere(function($inner) use ($start, $end) {
                          $inner->where('start', '<', $start . ' 00:00:00')
                                ->where('end', '>', $end . ' 23:59:59');
                      });
                });
            });
        
        // Get total count for pagination info
        $totalMemofyEvents = $memofyEventsQuery->count();
        
        // Apply pagination
        $memofyEvents = $memofyEventsQuery->orderBy('start', 'asc')
            ->skip($offset)
            ->take($perPage)
            ->get()
            ->map(function ($event) use ($user) {
                return $this->formatCalendarEvent($event, $user);
            });

        // 2. Get Google Events if connected (no pagination for Google as API handles it)
        $googleEvents = [];
        if ($user->google_calendar_token) {
            try {
                $googleController = new GoogleCalendarController();
                $googleRequest = new Request([
                    'start' => $start,
                    'end' => $end
                ]);
                $googleRequest->setUserResolver(fn() => $user);
                
                $response = $googleController->listEvents($googleRequest);
                $googleEvents = json_decode($response->getContent(), true) ?: [];
                
                foreach ($googleEvents as &$ge) {
                    $ge['source'] = 'GOOGLE';
                    $ge['is_editable'] = false;
                }
            } catch (\Exception $e) {
                \Log::error("Google Calendar Sync Error: " . $e->getMessage());
            }
        }

        // 3. Merge results
        $allEvents = array_merge($memofyEvents->toArray(), $googleEvents);
        
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
            'color' => $this->getEventColor($event->category),
            'description' => $event->description ?? '',
            'source' => 'MEMOFY',
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
    protected function getEventColor($category)
    {
        $colors = [
            'urgent' => '#EF4444',    // Red
            'high' => '#F97316',       // Orange
            'meeting' => '#3B82F6',    // Blue
            'deadline' => '#EF4444',   // Red
            'reminder' => '#8B5CF6',   // Purple
            'standard' => '#10B981',   // Green
            'low' => '#6B7280',        // Gray
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
        if ($event->created_by !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->activityLogger->logUserAction($request->user(), 'delete_calendar_event', $event, $this->activityLogger->extractRequestInfo($request));

        // Sync to Google (Delete)
        try {
            $googleController = new GoogleCalendarController();
            $googleController->syncEventToParticipants($event, 'delete');
        } catch (\Exception $e) {
            \Log::error("Immediate Google Sync Error (Delete): " . $e->getMessage());
        }

        $event->delete();

        return response()->json(['message' => 'Event deleted successfully']);
    }

    /**
     * Respond to an invitation.
     */
    public function respond(Request $request, CalendarEvent $event)
    {
        $validated = $request->validate([
            'status' => 'required|in:accepted,declined'
        ]);

        $participant = CalendarEventParticipant::where('calendar_event_id', $event->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

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
}
