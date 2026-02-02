<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\ActivityLogger;
use Google\Client as GoogleClient; // Requires google/apiclient, assuming installed or we use curl

class AuthController extends Controller
{
    protected $activityLogger;

    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }

    /**
     * Local Login with Lockout Logic
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            // 'recaptcha_token' => 'required' // Uncomment when ready
        ]);

        $user = User::where('email', $request->email)->first();

        // 1. Check existence
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'This account has not been added by an administrator.'
            ], 401);
        }

        // 2. Check active status
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated.'
            ], 401);
        }

        // 3. Check lockout
        if ($user->lock_until && $user->lock_until->isFuture()) {
            return response()->json([
                'success' => false,
                'message' => 'Account is temporarily locked. Try again in ' . $user->lock_until->diffInMinutes() . ' minutes.',
                'lock_time_remaining' => $user->lock_until->diffInMinutes()
            ], 423);
        }

        // 4. Verify password
        if (!$user->password || !Hash::check($request->password, $user->password)) {
            $user->incrementLoginAttempts();
            
            $attemptsRemaining = 5 - $user->login_attempts;
            $message = "Invalid credentials.";
            
            if ($attemptsRemaining > 0) {
                $message .= " {$attemptsRemaining} attempts remaining.";
            } else {
                $message .= " Account locked for 5 minutes.";
            }

            $this->activityLogger->logAuthAction($user, 'login_failed', 'Failed login attempt', $this->activityLogger->extractRequestInfo($request));

            return response()->json([
                'success' => false,
                'message' => $message,
                'attempts_remaining' => max(0, $attemptsRemaining)
            ], 401);
        }

        // 5. Success
        $user->resetLoginAttempts();
        $user->update(['last_login' => now()]);
        
        $token = $user->createToken('auth_token')->plainTextToken;
        
        $this->activityLogger->logAuthAction($user, 'login_success', 'User logged in', $this->activityLogger->extractRequestInfo($request));

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token
        ]);
    }

    /**
     * Google Token Login (ID Token Verification)
     */
    public function googleTokenLogin(Request $request)
    {
        $request->validate([
            'credential' => 'required_without:accessToken', // ID Token
            // 'accessToken' => 'required_without:credential' // Alternative
        ]);

        try {
            $payload = null;

            // Simplified verification using Socialite or manual CURL if library missing
            // Ideally we use Google\Client library. Assuming Socialite is setup for stateless
            // But usually frontend sends ID Token (JWT).
            
            // For now, let's use Socialite if we can, OR standard ID token decoding.
            // Since we are migrating legacy which used google-auth-library, we should check if we can simply trust the email 
            // IF and ONLY IF we verify the signature. 
            // In Laravel, Socialite with 'google' driver expects code exchange, NOT ID token verification directly generally.
            // So we might need to use a specific package or just verify via Google API endpoint.

            $idToken = $request->credential;
            
            // Verify with Google API
            $client = new \GuzzleHttp\Client();
            $response = $client->get('https://oauth2.googleapis.com/tokeninfo', [
                'query' => ['id_token' => $idToken]
            ]);
            
            $payload = json_decode($response->getBody()->getContents(), true);
            
            if (!$payload || !isset($payload['email'])) {
                 throw new \Exception('Invalid Token');
            }

            $email = $payload['email'];
            $googleId = $payload['sub'];
            $picture = $payload['picture'] ?? null;
            $name = $payload['name'] ?? null;
            
            // Find user
            $user = User::where('email', $email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account not found. Contact administrator.'
                ], 403);
            }

            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account deactivated.'
                ], 403);
            }

            // Update user info
            $user->google_id = $googleId;
            $user->profile_picture = $picture ?? $user->profile_picture;
            $user->last_login = now();
            if (empty($user->first_name) && $name) {
                // Try to split name if missing
                $parts = explode(' ', $name, 2);
                $user->first_name = $parts[0];
                $user->last_name = $parts[1] ?? '';
            }
            $user->save();

            $token = $user->createToken('google_auth_token')->plainTextToken;

            $this->activityLogger->logAuthAction($user, 'google_login', 'Logged in via Google', $this->activityLogger->extractRequestInfo($request));

            return response()->json([
                'success' => true,
                'message' => 'Google login successful',
                'user' => $user,
                'token' => $token
            ]);

        } catch (\Exception $e) {
            \Log::error('Google Auth Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Google authentication failed: ' . $e->getMessage()
            ], 401);
        }
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->currentAccessToken()->delete();
            $this->activityLogger->logAuthAction($user, 'logout', 'User logged out', $this->activityLogger->extractRequestInfo($request));
        }

        return response()->json(['success' => true, 'message' => 'Logged out']);
    }

    public function getCurrentUser(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user()
        ]);
    }

    public function verifyInvitationToken($token)
    {
        $invitation = UserInvitation::where('token', $token)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired invitation token.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $invitation
        ]);
    }

    public function setupPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'name' => 'required|string',
            'password' => 'required|min:8|confirmed'
        ]);

        $invitation = UserInvitation::where('token', $request->token)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$invitation) {
            return response()->json(['message' => 'Invalid or expired token'], 404);
        }

        // Split name
        $parts = explode(' ', trim($request->name), 2);
        $firstName = $parts[0];
        $lastName = $parts[1] ?? '';

        // Create User
        $user = User::create([
            'email' => $invitation->email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'password' => Hash::make($request->password),
            'role' => $invitation->role,
            'department' => $invitation->department,
            'is_active' => true,
        ]);
        
        // Mark used
        $invitation->update(['used' => true]);
        
        // Login
        $token = $user->createToken('auth_token')->plainTextToken;
        
        $this->activityLogger->logAuthAction($user, 'account_setup', 'User setup account via invitation', $this->activityLogger->extractRequestInfo($request));

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully',
            'data' => [
                'user' => $user,
                'token' => $token,
                'role' => $user->role
            ]
        ]);
    }

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            
            $user = User::where('email', $googleUser->getEmail())->first();

            if (!$user) {
                return $this->returnPopupError("Access Denied: No account found for {$googleUser->getEmail()}. Please ask an admin to invite you.");
            }

            if (!$user->is_active) {
                return $this->returnPopupError("Access Denied: Your account has been deactivated.");
            }

            // Generate Token
            $token = $user->createToken('auth_token')->plainTextToken;
            
            // Log
            $this->activityLogger->logAuthAction($user, 'login', "Logged in via Google", request()->all());

            return $this->returnPopupSuccess($token, $user);

        } catch (\Exception $e) {
            return $this->returnPopupError("Google Login Failed: " . $e->getMessage());
        }
    }

    private function returnPopupSuccess($token, $user)
    {
        $payload = [
            'type' => 'GOOGLE_LOGIN_SUCCESS',
            'payload' => [
                'token' => $token,
                'user' => $user,
                'role' => $user->role
            ]
        ];

        return $this->renderPopupHtml($payload);
    }

    private function returnPopupError($message)
    {
        $payload = [
            'type' => 'GOOGLE_LOGIN_FAILURE',
            'error' => $message
        ];

        return $this->renderPopupHtml($payload);
    }
    
    private function renderPopupHtml($data)
    {
        $json = json_encode($data);
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head><title>Authentication</title></head>
<body>
<script>
    window.opener.postMessage($json, '*');
    window.close();
</script>
</body>
</html>
HTML;
        return response($html);
    }
}