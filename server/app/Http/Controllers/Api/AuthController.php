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
            
            // Find or create user
            $user = User::firstOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'full_name' => $googleUser->getName(),
                    'username' => strtolower(str_replace(' ', '.', $googleUser->getName())), // temporary username logic
                    'role' => 'faculty', // Default role for new users
                    'password_hash' => bcrypt(Str::random(16)), // Random password for file uploads/etc if needed
                    'is_active' => true,
                ]
            );

            // Login user
            Auth::login($user);
            $token = $user->createToken('auth-token')->plainTextToken;

            // Return HTML with script to communicate with opener
            // This is crucial for the "Popup" experience requested
            return view('auth.google-callback', [
                'token' => $token,
                'user' => $user,
                'role' => $user->role
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Google Login Failed'], 500);
        }
    }
}