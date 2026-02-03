<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\PasswordResetMail;
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
            'recaptcha_token' => 'required'
        ]);

        // reCAPTCHA Validation
        $response = \Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => env('RECAPTCHA_SECRET'),
            'response' => $request->recaptcha_token,
            'remoteip' => $request->ip(),
        ]);

        if (!$response->json('success')) {
            return response()->json([
                'success' => false,
                'message' => 'reCAPTCHA verification failed. Please try again.'
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        // 1. Check existence or verification (treated as same for security UX)
        if (!$user || !$user->password || !Hash::check($request->password, $user->password)) {
            if ($user) {
                $user->incrementLoginAttempts();
                $this->activityLogger->logAuthAction($user, 'login_failed', 'Failed login attempt', $this->activityLogger->extractRequestInfo($request));
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Incorrect username or password.'
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

        // 4. Success
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

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'recaptcha_token' => 'required'
        ]);

        // reCAPTCHA Validation
        $response = \Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => env('RECAPTCHA_SECRET'),
            'response' => $request->recaptcha_token,
            'remoteip' => $request->ip(),
        ]);

        if (!$response->json('success')) {
            return response()->json([
                'success' => false,
                'message' => 'reCAPTCHA verification failed. Please try again.'
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No account is associated with this email address.'
            ], 404);
        }

        // Check if it's a Google account
        if ($user->google_id) {
            return response()->json([
                'success' => false,
                'message' => 'This account uses Google Sign-In. Password reset is not available.'
            ], 422);
        }

        // Generate 6-digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Update User
        $user->update([
            'reset_code' => $code,
            'reset_code_expires_at' => now()->addMinutes(15)
        ]);

        try {
            Mail::to($user->email)->send(new PasswordResetMail($user, $code));
            
            $this->activityLogger->logAuthAction($user, 'forgot_password_request', 'Password reset code requested', $this->activityLogger->extractRequestInfo($request));

            return response()->json([
                'success' => true,
                'message' => 'Password reset code has been sent to your email.'
            ]);
        } catch (\Exception $e) {
            \Log::error('Password reset email failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send reset code. Please try again later.'
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'password' => 'required|min:8|confirmed',
            'recaptcha_token' => 'required'
        ]);

        // reCAPTCHA Validation
        $response = \Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => env('RECAPTCHA_SECRET'),
            'response' => $request->recaptcha_token,
            'remoteip' => $request->ip(),
        ]);

        if (!$response->json('success')) {
            return response()->json([
                'success' => false,
                'message' => 'reCAPTCHA verification failed. Please try again.'
            ], 422);
        }

        $user = User::where('email', $request->email)
                    ->where('reset_code', $request->code)
                    ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'The reset code is invalid or has expired.'
            ], 422);
        }

        // Check expiration
        if (!$user->reset_code_expires_at || $user->reset_code_expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'The reset code is invalid or has expired.'
            ], 422);
        }

        try {
            // Update Password
            $user->update([
                'password' => Hash::make($request->password),
                'reset_code' => null,
                'reset_code_expires_at' => null
            ]);

            $this->activityLogger->logAuthAction($user, 'password_reset_success', 'Password successfully reset', $this->activityLogger->extractRequestInfo($request));

            return response()->json([
                'success' => true,
                'message' => 'Your password has been reset successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to reset password. Please try again.'
            ], 500);
        }
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
            ->where('used', '!=', true)
            ->where('expires_at', '>', now())
            ->first();

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'This invitation link is invalid or has expired.'
            ], 422);
        }

        $user = User::find($invitation->user_id);
        
        return response()->json([
            'success' => true,
            'data' => [
                'invitation' => $invitation,
                'user_name' => $user ? $user->full_name : ''
            ]
        ]);
    }

    public function setupPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'name' => 'required|string|min:2',
            'password' => 'required|min:8|confirmed',
            'recaptcha_token' => 'required'
        ]);

        // reCAPTCHA Validation
        $response = \Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => env('RECAPTCHA_SECRET'),
            'response' => $request->recaptcha_token,
            'remoteip' => $request->ip(),
        ]);

        if (!$response->json('success')) {
            return response()->json([
                'success' => false,
                'message' => 'reCAPTCHA verification failed. Please try again.'
            ], 422);
        }

        $invitation = UserInvitation::where('token', $request->token)
            ->where('used', '!=', true)
            ->where('expires_at', '>', now())
            ->first();

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'This invitation link is invalid or has expired.'
            ], 422);
        }

        // Find user by linked ID or fallback to email
        $user = User::find($invitation->user_id);
        
        if (!$user) {
            $user = User::where('email', $invitation->email)->first();
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'The associated user account could not be found.'
            ], 404);
        }

        // 1. Split name if provided (user might want to confirm/update their name)
        $parts = explode(' ', trim($request->name), 2);
        $firstName = $parts[0];
        $lastName = $parts[1] ?? '';

        try {
            // 2. Update User
            $user->update([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'password' => Hash::make($request->password),
                'is_active' => true,
            ]);
            
            // 3. Mark invitation used
            $invitation->update(['used' => true, 'status' => 'accepted']);
            
            // 4. Create Login Token
            $token = $user->createToken('auth_token')->plainTextToken;
            
            $this->activityLogger->logAuthAction($user, 'account_setup', 'User activated account via invitation', $this->activityLogger->extractRequestInfo($request));

            return response()->json([
                'success' => true,
                'message' => 'Your account has been activated successfully.',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'role' => $user->role
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Setup password error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to activate account. Please try again later.'
            ], 500);
        }
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
    
    public function updateTheme(Request $request)
    {
        $request->validate([
            'theme' => 'required|string'
        ]);

        $user = $request->user();
        $user->update(['theme' => $request->theme]);

        return response()->json([
            'success' => true,
            'message' => 'Theme updated successfully',
            'theme' => $user->theme
        ]);
    }

    public function updateMe(Request $request)
    {
        $user = $request->user();
        $user->update($request->only(['first_name', 'last_name']));
        return response()->json(['success' => true, 'user' => $user]);
    }

    public function uploadMyProfilePicture(Request $request)
    {
        // Placeholder for future logic
        return response()->json(['success' => true, 'message' => 'Profile picture upload logic placeholder']);
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