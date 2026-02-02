<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendarService;
use Carbon\Carbon;

class GoogleCalendarController extends Controller
{
    public function connect(Request $request)
    {
        $user = $request->user();
        
        // Use state to store the user ID so we can retrieve it in the callback
        $state = Crypt::encrypt($user->id);

        $config = config('services.google_calendar');
        
        $url = Socialite::buildProvider(\Laravel\Socialite\Two\GoogleProvider::class, $config)
            ->scopes([
                'https://www.googleapis.com/auth/calendar',
                'https://www.googleapis.com/auth/userinfo.email',
                'https://www.googleapis.com/auth/userinfo.profile',
            ])
            ->with([
                'access_type' => 'offline', 
                'prompt' => 'consent select_account', 
                'state' => $state
            ])
            ->stateless()
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    public function callback(Request $request)
    {
        try {
            $state = $request->input('state');
            if (!$state) {
                 return $this->returnPopupError('Missing state parameter.');
            }
            
            try {
                $userId = Crypt::decrypt($state);
            } catch (\Exception $e) {
                return $this->returnPopupError('Invalid state parameter.');
            }
            
            $user = User::find($userId);
            if (!$user) {
                return $this->returnPopupError('User not found.');
            }

            $config = config('services.google_calendar');
            $driver = Socialite::buildProvider(\Laravel\Socialite\Two\GoogleProvider::class, $config);
            $googleUser = $driver->stateless()->user();
            $googleEmail = $googleUser->getEmail();

            // Check if this Google account is already used by another user
            $existingUser = User::where('google_calendar_email', $googleEmail)
                ->where('id', '!=', $user->id)
                ->first();

            if ($existingUser) {
                return $this->returnPopupError("This Google account ($googleEmail) is already linked to another Memofy user.");
            }

            // Store Tokens
            $user->update([
                'google_calendar_token' => $googleUser->token,
                'google_calendar_refresh_token' => $googleUser->refreshToken,
                // expiresIn is in seconds
                'google_calendar_token_expires_at' => now()->addSeconds($googleUser->expiresIn),
                'google_calendar_email' => $googleEmail,
            ]);

            return $this->returnPopupSuccess();

        } catch (\Exception $e) {
            return $this->returnPopupError('Connection failed: ' . $e->getMessage());
        }
    }

    public function disconnect(Request $request)
    {
        $request->user()->update([
            'google_calendar_token' => null,
            'google_calendar_refresh_token' => null,
            'google_calendar_token_expires_at' => null,
            'google_calendar_email' => null,
        ]);

        return response()->json(['message' => 'Disconnected successfully']);
    }

    public function listEvents(Request $request)
    {
        $user = $request->user();
        
        if (!$user->google_calendar_token) {
            return response()->json([]);
        }

        try {
            $client = $this->getClient();
            $client->setAccessToken($user->google_calendar_token);

            if ($client->isAccessTokenExpired()) {
                if ($user->google_calendar_refresh_token) {
                    $payload = $client->fetchAccessTokenWithRefreshToken($user->google_calendar_refresh_token);
                    
                    if (isset($payload['error'])) {
                        $this->disconnect($request);
                        return response()->json([]);
                    }

                    $user->update([
                        'google_calendar_token' => $payload['access_token'],
                        'google_calendar_token_expires_at' => now()->addSeconds($payload['expires_in'])
                    ]);
                    $client->setAccessToken($payload['access_token']);
                } else {
                    return response()->json([]);
                }
            }

            $service = new \Google\Service\Calendar($client);
            
            $optParams = [
                'maxResults' => 100,
                'orderBy' => 'startTime',
                'singleEvents' => true,
            ];

            if ($request->start) {
                $optParams['timeMin'] = \Carbon\Carbon::parse($request->start)->toRfc3339String();
            } else {
                $optParams['timeMin'] = now()->startOfMonth()->toRfc3339String();
            }

            if ($request->end) {
                 $optParams['timeMax'] = \Carbon\Carbon::parse($request->end)->toRfc3339String();
            } else {
                 $optParams['timeMax'] = now()->addMonths(2)->endOfMonth()->toRfc3339String();
            }

            $results = $service->events->listEvents('primary', $optParams);
            $events = [];
            
            foreach ($results->getItems() as $event) {
                if ($event->status === 'cancelled') continue;

                $startDateTime = $event->start->dateTime ?? $event->start->date;
                $endDateTime = $event->end->dateTime ?? $event->end->date;
                $allDay = !isset($event->start->dateTime);
                
                // Enforce GMT+08 (Asia/Manila)
                $start = \Carbon\Carbon::parse($startDateTime)->setTimezone('Asia/Manila')->toIso8601String();
                $end = \Carbon\Carbon::parse($endDateTime)->setTimezone('Asia/Manila')->toIso8601String();
                
                $events[] = [
                    'id' => $event->id,
                    'title' => $event->summary,
                    'start' => $start,
                    'end' => $end,
                    'all_day' => $allDay,
                    'source' => 'GOOGLE',
                    'is_google' => true, // Frontend legacy support
                    'color' => '#4285F4',
                    'is_editable' => false,
                    'description' => $event->description
                ];
            }
            
            return response()->json($events);
        } catch (\Throwable $e) {
            \Log::error('Google Calendar Fatal Error: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => $user->id
            ]);
            return response()->json(['error' => 'Google Calendar Error: ' . $e->getMessage()], 500);
        }
    }

    public function syncEvent(User $user, $memofyEvent, $action = 'create')
    {
        if (!$user->google_calendar_token) return null;

        try {
            $client = $this->getClient();
            $client->setAccessToken($user->google_calendar_token);

            if ($client->isAccessTokenExpired()) {
                if ($user->google_calendar_refresh_token) {
                    $payload = $client->fetchAccessTokenWithRefreshToken($user->google_calendar_refresh_token);
                    if (isset($payload['error'])) return null;
                    $user->update([
                        'google_calendar_token' => $payload['access_token'],
                        'google_calendar_token_expires_at' => now()->addSeconds($payload['expires_in'])
                    ]);
                    $client->setAccessToken($payload['access_token']);
                } else {
                    return null;
                }
            }

            $service = new \Google\Service\Calendar($client);
            
            $eventData = [
                'summary' => $memofyEvent->title,
                'description' => $memofyEvent->description,
                'start' => [
                    'dateTime' => \Carbon\Carbon::parse($memofyEvent->start)->toRfc3339String(),
                    'timeZone' => 'Asia/Manila',
                ],
                'end' => [
                    'dateTime' => \Carbon\Carbon::parse($memofyEvent->end)->toRfc3339String(),
                    'timeZone' => 'Asia/Manila',
                ],
            ];

            $gEvent = new \Google\Service\Calendar\Event($eventData);
            
            $googleEventIds = $memofyEvent->google_calendar_event_ids ?: [];
            $existingId = $googleEventIds[$user->id] ?? null;

            if ($action === 'delete' && $existingId) {
                try {
                    $service->events->delete('primary', $existingId);
                } catch (\Exception $e) {}
                unset($googleEventIds[$user->id]);
                $memofyEvent->update(['google_calendar_event_ids' => $googleEventIds]);
                return true;
            }

            if ($existingId) {
                try {
                    $updatedEvent = $service->events->update('primary', $existingId, $gEvent);
                    return $updatedEvent->id;
                } catch (\Exception $e) {
                    // If event was deleted in Google, create new one
                    $createdEvent = $service->events->insert('primary', $gEvent);
                    $googleEventIds[$user->id] = $createdEvent->id;
                    $memofyEvent->update(['google_calendar_event_ids' => $googleEventIds]);
                    return $createdEvent->id;
                }
            } else {
                $createdEvent = $service->events->insert('primary', $gEvent);
                $googleEventIds[$user->id] = $createdEvent->id;
                $memofyEvent->update(['google_calendar_event_ids' => $googleEventIds]);
                return $createdEvent->id;
            }

        } catch (\Exception $e) {
            \Log::error("Google Calendar Sync Error for user {$user->id}: " . $e->getMessage());
            return null;
        }
    }

    public function syncEventToParticipants($memofyEvent, $action = 'create')
    {
        // 1. Sync for creator
        $creator = User::find($memofyEvent->created_by);
        if ($creator) {
            $this->syncEvent($creator, $memofyEvent, $action);
        }

        // 2. Sync for participants
        $memofyEvent->load('participants.user');
        foreach ($memofyEvent->participants as $participant) {
            if ($participant->user && $participant->user->google_calendar_token) {
                $this->syncEvent($participant->user, $memofyEvent, $action);
            }
        }
    }

    // Helpers
    private function returnPopupSuccess()
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<body>
<script>
    window.opener.postMessage({ type: 'GOOGLE_CALENDAR_CONNECTED' }, '*');
    window.close();
</script>
</body>
</html>
HTML;
        return response($html);
    }

    private function returnPopupError($message)
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<body>
<script>
    window.opener.postMessage({ type: 'GOOGLE_CALENDAR_FAILURE', error: "$message" }, '*');
    window.close();
</script>
</body>
</html>
HTML;
        return response($html);
    }

    private function getClient()
    {
        $client = new \Google\Client();
        $client->setClientId(config('services.google_calendar.client_id'));
        $client->setClientSecret(config('services.google_calendar.client_secret'));
        $client->setRedirectUri(config('services.google_calendar.redirect'));
        $client->addScope(\Google\Service\Calendar::CALENDAR);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        return $client;
    }
}
