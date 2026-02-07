<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\CalendarEventParticipant;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Api\GoogleCalendarController;

class CalendarController extends Controller
{
    protected $activityLogger;

    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $start = $request->input('start');
        $end = $request->input('end');

        // 1. Get Memofy Events (Created by user OR invited to)
        $memofyEvents = CalendarEvent::where(function ($query) use ($user) {
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
            })
            ->get()
            ->map(function ($event) use ($user) {
                $event->source = 'MEMOFY';
                $event->is_editable = $event->created_by === $user->id;
                
                // Get invitation status for current user if not creator
                if (!$event->is_editable) {
                    $participant = $event->participants->where('user_id', $user->id)->first();
                    $event->invitation_status = $participant ? $participant->status : null;
                }
                
                return $event;
            });

        // 2. Get Google Events if connected
        $googleEvents = [];
        if ($user->google_calendar_token) {
            try {
                $googleController = new GoogleCalendarController();
                // We wrap the request to simulate what listEvents expects
                $googleRequest = new Request([
                    'start' => $start,
                    'end' => $end
                ]);
                $googleRequest->setUserResolver(fn() => $user);
                
                $response = $googleController->listEvents($googleRequest);
                $googleEvents = json_decode($response->getContent(), true) ?: [];
                
                // Tag as GOOGLE source
                foreach ($googleEvents as &$ge) {
                    $ge['source'] = 'GOOGLE';
                    $ge['is_editable'] = false;
                }
            } catch (\Exception $e) {
                // Silently fail or log for Google sync errors
                \Log::error("Google Calendar Sync Error: " . $e->getMessage());
            }
        }

        // 3. Merge and Return
        return response()->json([
            'events' => array_merge($memofyEvents->toArray(), $googleEvents)
        ]);
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

            // Add participants
            if (!empty($validated['invited_users'])) {
                foreach ($validated['invited_users'] as $userId) {
                    if ($userId != $request->user()->id) { // Don't invite self
                        CalendarEventParticipant::create([
                            'calendar_event_id' => $event->id,
                            'user_id' => $userId,
                            'status' => 'pending'
                        ]);
                    }
                }
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

                // Add new ones
                foreach ($validated['invited_users'] as $userId) {
                    if ($userId != $request->user()->id) {
                        CalendarEventParticipant::firstOrCreate([
                            'calendar_event_id' => $event->id,
                            'user_id' => $userId
                        ], [
                            'status' => 'pending'
                        ]);
                    }
                }
            }

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

        return response()->json([
            'message' => 'Invitation ' . $validated['status'],
            'status' => $validated['status']
        ]);
    }
}
