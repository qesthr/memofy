<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite; // Ensure Socialite is installed or use this placeholder
use App\Models\User;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Handle user login
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // 1. Validate email & password
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        // 2. Attempt to authenticate the user
        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // 3. Get the authenticated user
        $user = Auth::user();

        // 4. Create a Sanctum token
        $token = $user->createToken('auth-token')->plainTextToken;



        // 5. Return user + role + token to Vue
        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->user_id,
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'username' => $user->username,
                ],
                'role' => $user->role,
                'token' => $token,
            ]
        ], 200);
    }

    /**
     * Handle user logout
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // Revoke the current access token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ], 200);
    }

    /**
     * Get the authenticated user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->user_id,
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'username' => $user->username,
                ],
                'role' => $user->role,
            ]
        ], 200);
    }

    /**
     * Redirect to Google
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * Handle Google Callback
     */
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            
            // Generate unique username from email
            $emailUsername = explode('@', $googleUser->getEmail())[0];
            $username = strtolower($emailUsername);
            
            // Ensure username is unique
            $originalUsername = $username;
            $counter = 1;
            while (User::where('username', $username)->exists()) {
                $username = $originalUsername . $counter;
                $counter++;
            }
            
            // Find or create user
            $user = User::updateOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'full_name' => $googleUser->getName(),
                    'username' => $username,
                    'role' => 'faculty', // Default role for new users
                    'password_hash' => bcrypt(Str::random(16)),
                    'is_active' => true,
                    'department' => '', // Admin check will clear this anyway if role is admin
                ]
            );

            // Create Sanctum token
            $token = $user->createToken('auth-token')->plainTextToken;

            // Return HTML with script to communicate with opener
            return view('auth.google-callback', [
                'token' => $token,
                'user' => [
                    'id' => $user->user_id,
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'username' => $user->username,
                ],
                'role' => $user->role
            ]);

        } catch (\Exception $e) {
            \Log::error('Google Login Error: ' . $e->getMessage());
            return view('auth.google-error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Verify invitation token
     */
    public function verifyInvitationToken($token)
    {
        $invitation = DB::table('user_invitations')
            ->where('token', $token)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$invitation) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid or expired token'
            ], 404);
        }

        return response()->json([
            'valid' => true,
            'data' => [
                'name' => $invitation->name,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'department' => $invitation->department
            ]
        ]);
    }

    /**
     * Setup password for invited user
     */
    public function setupPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed'
        ]);

        try {
            // Verify invitation
            $invitation = DB::table('user_invitations')
                ->where('token', $request->token)
                ->where('used', false)
                ->where('expires_at', '>', now())
                ->first();

            if (!$invitation) {
                return response()->json([
                    'message' => 'Invalid or expired invitation token'
                ], 404);
            }

            // Find the existing user by email
            $user = User::where('email', $invitation->email)->first();

            if (!$user) {
                return response()->json([
                    'message' => 'User not found. Please contact administrator.'
                ], 404);
            }

            // Check if user already has a password set
            if ($user->password_hash) {
                return response()->json([
                    'message' => 'Password already set for this account'
                ], 409);
            }

            // Update user with password and activate account
            $user->password_hash = bcrypt($request->password);
            $user->is_active = true;
            $user->save();

            // Mark invitation as used
            DB::table('user_invitations')
                ->where('token', $request->token)
                ->update(['used' => true]);

            // Create token for auto-login
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'message' => 'Password set successfully',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $user->user_id,
                        'name' => $user->full_name,
                        'email' => $user->email,
                        'username' => $user->username,
                    ],
                    'role' => $user->role
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Password setup error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to setup account',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}