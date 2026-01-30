<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserInvitationMail;

class UserController extends Controller
{
    protected $activityLogger;

    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }

    public function index(Request $request)
    {
        $query = User::query();

        // Search
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'like', "%{$request->search}%")
                  ->orWhere('last_name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        // Filter by Role
        if ($request->role) {
            $query->where('role', $request->role);
        }

        // Filter by Department
        if ($request->department) {
            $query->where('department', $request->department);
        }

        // Filter by Status
        if ($request->status !== null) {
            $isActive = $request->status === 'active';
            $query->where('is_active', $isActive);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(50);

        return response()->json($users);
    }

    public function store(Request $request)
    {
        // Admin creates user directly
        $validated = $request->validate([
            'email' => 'required|email|unique:users',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'role' => 'required|string',
            'department' => 'nullable|string',
            'password' => 'required|string|min:8'
        ]);

        $user = User::create([
            'email' => $validated['email'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'role' => $validated['role'],
            'department' => $validated['department'],
            'password' => bcrypt($validated['password']),
            'is_active' => true
        ]);

        $this->activityLogger->logUserAction($request->user(), 'create_user', $user, $this->activityLogger->extractRequestInfo($request));

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user
        ], 201);
    }

    public function show($id)
    {
        $user = User::findOrFail($id);
        return response()->json($user);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'sometimes|string',
            'last_name' => 'sometimes|string',
            'role' => 'sometimes|string',
            'department' => 'nullable|string',
            'email' => 'sometimes|email|unique:users,email,' . $id
        ]);

        $original = $user->getOriginal();
        $user->update($validated);

        $this->activityLogger->logUserAction($request->user(), 'update_user', $user, [
            'changes' => $user->getChanges(),
            'original' => $original,
            ...$this->activityLogger->extractRequestInfo($request)
        ]);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user
        ]);
    }

    public function toggleActive(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        // Prevent deactivating self
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Cannot deactivate your own account'], 403);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        $action = $user->is_active ? 'activate_user' : 'deactivate_user';
        $this->activityLogger->logUserAction($request->user(), $action, $user, $this->activityLogger->extractRequestInfo($request));

        return response()->json([
            'message' => 'User status updated',
            'user' => $user
        ]);
    }

    public function inviteUser(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:users',
            'role' => 'required|string',
            'department' => 'nullable|string'
        ]);

        // Check if pending invitation exists
        $existingInvite = UserInvitation::where('email', $validated['email'])
            ->where('used', false)
            ->first();

        $token = Str::random(32);

        if ($existingInvite) {
            // Update existing invite
            $existingInvite->update([
                'token' => $token,
                'expires_at' => now()->addDays(7),
                'role' => $validated['role'],
                'department' => $validated['department'],
                'invited_by' => $request->user()->id
            ]);
            $invitation = $existingInvite;
        } else {
            // Create new invite
            $invitation = UserInvitation::create([
                'email' => $validated['email'],
                'role' => $validated['role'],
                'department' => $validated['department'],
                'token' => $token,
                'expires_at' => now()->addDays(7),
                'invited_by' => $request->user()->id
            ]);
        }

        // Send Email
        Mail::to($invitation->email)->send(new UserInvitationMail($invitation));

        // Using activity log to "simulate" email sending for now if mailer not set up
        \Log::info("Invitation Link: " . config('app.frontend_url') . "/setup-password?token=" . $token);

        $this->activityLogger->logUserAction($request->user(), 'invite_user', "Invited " . $validated['email'], [
            'role' => $validated['role'],
            'department' => $validated['department'],
            ...$this->activityLogger->extractRequestInfo($request)
        ]);

        return response()->json([
            'message' => 'Invitation sent successfully',
            'invitation' => $invitation,
            'debug_link' => config('app.frontend_url') . "/setup-password?token=" . $token // Remove in production
        ]);
    }

    public function restoreAll(Request $request)
    {
        $archivedUsers = User::where('is_active', false)->get();
        $count = $archivedUsers->count();
        
        if ($count === 0) {
            return response()->json(['message' => 'No archived users to restore'], 200);
        }
        
        User::where('is_active', false)->update(['is_active' => true]);
        
        $this->activityLogger->logUserAction(
            $request->user(), 
            'bulk_restore_users', 
            "Restored {$count} archived users",
            $this->activityLogger->extractRequestInfo($request)
        );
        
        return response()->json([
            'message' => "{$count} users restored successfully",
            'count' => $count
        ]);
    }
}
