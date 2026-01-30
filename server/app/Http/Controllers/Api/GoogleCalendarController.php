<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendarService;
use Carbon\Carbon;

class GoogleCalendarController extends Controller
{
    public function connect(Request $request)
    {
        $user = $request->user();
        $state = Crypt::encrypt($user->id);

        $config = config('services.google_calendar');
        
        $url = Socialite::buildProvider(\Laravel\Socialite\Two\GoogleProvider::class, $config)
            ->scopes([
                'https://www.googleapis.com/auth/calendar.readonly',
                'https://www.googleapis.com/auth/calendar.events'
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

            // Store Tokens
            $user->update([
                'google_calendar_token' => $googleUser->token,
                'google_calendar_refresh_token' => $googleUser->refreshToken,
                // expiresIn is in seconds
                'google_calendar_token_expires_at' => now()->addSeconds($googleUser->expiresIn),
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
        ]);

        return response()->json(['message' => 'Disconnected successfully']);
    }

    public function listEvents(Request $request)
    {
        $user = $request->user();
        
        if (!$user->google_calendar_token) {
            return response()->json([]);
        }

        \Log::debug('Google Calendar: Starting listEvents', [
            'user_id' => $user->id,
            'extensions' => [
                'bcmath' => extension_loaded('bcmath'),
                'gmp' => extension_loaded('gmp'),
                'openssl' => extension_loaded('openssl'),
            ]
        ]);

        $client = new GoogleClient();
        \Log::debug('Google Calendar: Client initialized');
        $client->setClientId(config('services.google_calendar.client_id'));
        $client->setClientSecret(config('services.google_calendar.client_secret'));
        $client->setAccessToken($user->google_calendar_token);
        \Log::debug('Google Calendar: Token set');
        
        // Refresh logic
        if ($user->google_calendar_token_expires_at && now()->gte($user->google_calendar_token_expires_at)) {
            \Log::debug('Google Calendar: Token expired, attempting refresh');
            if ($user->google_calendar_refresh_token) {
                $payload = $client->fetchAccessTokenWithRefreshToken($user->google_calendar_refresh_token);
                
                if (isset($payload['error'])) {
                     \Log::error('Google Calendar: Refresh failed', ['error' => $payload]);
                     // Token revoked? Disconnect.
                     $this->disconnect($request);
                     return response()->json([]);
                }
                
                $user->update([
                    'google_calendar_token' => $payload['access_token'],
                    'google_calendar_token_expires_at' => now()->addSeconds($payload['expires_in']),
                    // Refresh token might not be returned again
                    'google_calendar_refresh_token' => $payload['refresh_token'] ?? $user->google_calendar_refresh_token
                ]);
                $client->setAccessToken($payload['access_token']);
                \Log::debug('Google Calendar: Token refreshed successfully');
            } else {
                \Log::debug('Google Calendar: No refresh token available');
                return response()->json([]);
            }
        }

        $service = new GoogleCalendarService($client);
        \Log::debug('Google Calendar: Service initialized');
        $calendarId = 'primary';
        $optParams = [
            'maxResults' => 100, // Limit
            'orderBy' => 'startTime',
            'singleEvents' => true,
        ];
        
        // Handle optional date range from request (or default to current month)
        // Frontend typically sends 'start' and 'end'
        if ($request->start) {
            // Need parse JS ISO string?
            // Carbon::parse handles common formats.
            $optParams['timeMin'] = Carbon::parse($request->start)->toRfc3339String();
        } else {
            $optParams['timeMin'] = now()->startOfMonth()->toRfc3339String();
        }

        if ($request->end) {
             $optParams['timeMax'] = Carbon::parse($request->end)->toRfc3339String();
        } else {
             $optParams['timeMax'] = now()->endOfMonth()->toRfc3339String();
        }

        try {
            $results = $service->events->listEvents($calendarId, $optParams);
            $events = [];
            
            foreach ($results->getItems() as $event) {
                // Skip cancelled
                if ($event->status === 'cancelled') continue;

                $start = $event->start->dateTime ?? $event->start->date;
                $end = $event->end->dateTime ?? $event->end->date;
                
                // Determine if allDay (date only)
                $allDay = !isset($event->start->dateTime);
                
                $events[] = [
                    'id' => $event->id,
                    'title' => $event->summary,
                    'start' => $start,
                    'end' => $end,
                    'is_google' => true,
                    'allDay' => $allDay,
                    'color' => '#4285F4', // Google Blue
                    'editable' => false,
                    'description' => $event->description
                ];
            }
            
            return response()->json($events);

        } catch (\Throwable $e) {
            \Log::error('Google Calendar Fatal Error: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Google Calendar Error: ' . $e->getMessage()], 500);
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
}
