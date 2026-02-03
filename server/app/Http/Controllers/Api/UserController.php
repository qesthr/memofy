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
        $currentUser = $request->user();
        
        // Robust determines if currentUser is admin
        $isAdmin = (
            strtolower($currentUser->getAttribute('role') ?? '') === 'admin' || 
            ($currentUser->assignedRole && strtolower($currentUser->assignedRole->name ?? '') === 'admin')
        );

        // Check if user has permission to view ALL users (Admin usually)
        if (!$isAdmin && !$currentUser->hasPermissionTo('faculty.view_all')) {
            // If they can only view faculty (Secretary), enforce department scoping
            if ($currentUser->hasPermissionTo('faculty.view')) {
                $query->where('role', 'faculty');
                
                $dept = $currentUser->department ?: ($currentUser->assignedRole && $currentUser->assignedRole->department ? $currentUser->assignedRole->department : null);

                if ($dept) {
                    $query->where('department', $dept);
                } else {
                    $query->where('_id', 'none');
                }
            } else {
                // If not admin and no specific view permission, show only self
                $query->where('_id', $currentUser->id);
            }
        }

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
        if ($request->status === 'active') {
            // Show Active users OR Pending users (inactive but no password yet)
            $query->where(function($q) {
                $q->where('is_active', true)
                  ->orWhere(function($sq) {
                      $sq->where('is_active', false)
                        ->whereNull('password');
                  });
            });
        } elseif ($request->status === 'archived') {
            // Only show users who were manually deactivated (have password but inactive)
            $query->where('is_active', false)
                  ->whereNotNull('password');
        } else {
            $isActive = $request->status === 'active';
            $query->where('is_active', $isActive);
        }
    }

    $users = $query->orderBy('created_at', 'desc')->paginate(50);

    // Transform to include status helper
    $users->getCollection()->transform(function ($user) {
        if ($user->is_active) {
            $user->display_status = 'active';
        } elseif (!$user->password) {
            $user->display_status = 'pending';
        } else {
            $user->display_status = 'archived';
        }
        return $user;
    });

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
        $currentUser = auth()->user();

        // Check permission
        if (!$currentUser->hasPermissionTo('faculty.view') && $currentUser->id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        // Scope check for non-admins
        if ($currentUser->role !== 'admin' && $currentUser->id !== $user->id) {
            if ($user->department !== $currentUser->department || $user->role !== 'faculty') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }
        }

        return response()->json($user);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $currentUser = $request->user();
        $isAdmin = $currentUser->role === 'admin';

        // Check permission
        if (!$currentUser->hasPermissionTo('faculty.edit') && $currentUser->id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        // Scope check for non-admins editing others
        if (!$isAdmin && $currentUser->id !== $user->id) {
            if ($user->role !== 'faculty' || $user->department !== $currentUser->department) {
                return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
            }
        }

        $rules = [
            'first_name' => 'sometimes|string',
            'last_name' => 'sometimes|string',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'is_active' => 'sometimes|boolean'
        ];

        // Admin specific updates
        if ($isAdmin) {
            $rules['role'] = 'sometimes|string|in:admin,secretary,faculty';
            $rules['department'] = 'sometimes|string|exists:departments,name';
        }

        $validated = $request->validate($rules);

        // One Secretary Logic
        $requestedRole = $validated['role'] ?? $user->role;
        $requestedDept = $validated['department'] ?? $user->department;
        $requestedActive = $validated['is_active'] ?? $user->is_active;

        if ($isAdmin && $requestedRole === 'secretary' && $requestedActive) {
            $existingSecretary = User::where('role', 'secretary')
                ->where('department', $requestedDept)
                ->where(function($q) {
                    $q->where('is_active', true)
                      ->orWhereNull('password');
                })
                ->where('_id', '!=', $user->id)
                ->first();

            if ($existingSecretary) {
                $statusStr = $existingSecretary->is_active ? 'active' : 'pending invitation';
                return response()->json([
                    'success' => false,
                    'message' => "This department already has a secretary ({$existingSecretary->full_name}, status: {$statusStr}). Only one active or pending secretary is allowed per department."
                ], 422);
            }
        }

        // Synchronize department_id if department string is updated
        if (isset($validated['department'])) {
            $deptRecord = \App\Models\Department::where('name', $validated['department'])->first();
            if ($deptRecord) {
                $validated['department_id'] = $deptRecord->id;
            }
        }

        // Handle single 'name' field if provided from frontend
        if ($request->has('name')) {
            $parts = explode(' ', trim($request->name), 2);
            $validated['first_name'] = $parts[0];
            $validated['last_name'] = $parts[1] ?? '';
            unset($validated['name']);
        }

        $original = $user->getOriginal();
        $user->update($validated);

        $this->activityLogger->logUserAction($request->user(), 'update_user', $user, [
            'changes' => $user->getChanges(),
            'original' => $original,
            ...$this->activityLogger->extractRequestInfo($request)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'user' => $user
        ]);
    }

    public function toggleActive(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $currentUser = $request->user();
        
        // Prevent action on self
        if ($user->id === $currentUser->id) {
            return response()->json(['message' => 'Cannot modify your own account status'], 403);
        }

        // Check permission based on action
        $permission = $user->is_active ? 'faculty.archive' : 'faculty.unarchive';
        if (!$currentUser->hasPermissionTo($permission)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        // Scope check for non-admins
        if ($currentUser->role !== 'admin') {
            if ($user->role !== 'faculty' || $user->department !== $currentUser->department) {
                return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
            }
        }

        $user->is_active = !$user->is_active;
        $user->save();

        $action = $user->is_active ? 'activate_user' : 'deactivate_user';
        $this->activityLogger->logUserAction($currentUser, $action, $user, $this->activityLogger->extractRequestInfo($request));

        return response()->json([
            'message' => 'User status updated',
            'user' => $user
        ]);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $currentUser = auth()->user();
        
        // Prevent deleting self
        if ($user->id === $currentUser->id) {
            return response()->json(['message' => 'Cannot delete your own account'], 403);
        }

        // Check permission
        if (!$currentUser->hasPermissionTo('faculty.remove_permanently')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        // Scope check for non-admins
        if ($currentUser->role !== 'admin') {
            if ($user->role !== 'faculty' || $user->department !== $currentUser->department) {
                return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
            }
        }

        // Delete associated invitations
        UserInvitation::where('email', $user->email)->delete();
        
        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    public function inviteUser(Request $request)
    {
        $currentUser = $request->user();

        // Check permission
        if (!$currentUser->hasPermissionTo('faculty.add')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $isSecretary = $currentUser->role === 'secretary';
        $isAdmin = $currentUser->role === 'admin';

        // 1. Strict Validation
        $rules = [
            'name' => 'required|string|min:2',
            'email' => [
                'required',
                'email',
                'unique:users,email',
                function ($attribute, $value, $fail) {
                    if (!preg_match('/^[\w\.\-]+@(student\.)?buksu\.edu\.ph$/i', $value)) {
                        $fail('Only @buksu.edu.ph and @student.buksu.edu.ph email addresses are allowed.');
                    }
                },
            ],
        ];

        // Only Admin can set role and department
        if (!$isSecretary) {
            $rules['role'] = 'required|string|in:admin,secretary,faculty';
            $rules['department'] = 'required|string|exists:departments,name';
        }

        $request->validate($rules, [
            'email.unique' => 'An account with this email already exists.',
            'department.exists' => 'Invalid department selection.',
            'role.in' => 'Invalid role selection.',
        ]);

        try {
            // 2. Determine Role and Department (Enforce Secretary Scope)
            $role = $isSecretary ? 'faculty' : $request->role;
            $department = $isSecretary ? $currentUser->department : $request->department;

            // 3. One Secretary Constraint for Admin
            if (!$isSecretary && $role === 'secretary') {
                $existingSecretary = User::where('role', 'secretary')
                    ->where('department', $department)
                    ->where(function($q) {
                        $q->where('is_active', true)
                          ->orWhereNull('password');
                    })
                    ->first();

                if ($existingSecretary) {
                    $statusStr = $existingSecretary->is_active ? 'active' : 'pending invitation';
                    return response()->json([
                        'success' => false,
                        'message' => "This department already has a secretary ({$existingSecretary->full_name}, status: {$statusStr}). Only one active or pending secretary is allowed per department."
                    ], 422);
                }
            }

            // 4. Split Name
            $parts = explode(' ', trim($request->name), 2);
            $firstName = $parts[0];
            $lastName = $parts[1] ?? '';

            // 5. Create User record (inactive, no password)
            $userData = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $request->email,
                'role' => $role,
                'department' => $department,
                'is_active' => false, 
                'password' => null
            ];

            // Sync department_id
            $deptRecord = \App\Models\Department::where('name', $department)->first();
            if ($deptRecord) {
                $userData['department_id'] = $deptRecord->id;
            }

            $user = User::create($userData);

            // 5. Generate Token
            $token = Str::random(64);

            // 6. Create Invitation
            $invitation = UserInvitation::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'department' => $user->department,
                'token' => $token,
                'expires_at' => now()->addHours(48),
                'invited_by' => $currentUser->id,
                'status' => 'pending'
            ]);

            // 7. Send Email
            try {
                Mail::to($invitation->email)->send(new UserInvitationMail($invitation));
            } catch (\Exception $e) {
                \Log::error('Invitation email failed: ' . $e->getMessage());
                return response()->json([
                    'success' => true,
                    'message' => 'Faculty account was created, but the invitation email could not be sent.',
                    'user' => $user
                ], 201);
            }

            $this->activityLogger->logUserAction($currentUser, 'invite_user', "Invited " . $user->email, [
                'role' => $user->role,
                'department' => $user->department,
                ...$this->activityLogger->extractRequestInfo($request)
            ]);

            return response()->json([
                'success' => true,
                'message' => $isSecretary ? 'Faculty successfully invited and assigned to your department.' : 'User successfully added. An invitation email has been sent.',
                'user' => $user
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Invite user failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.'
            ], 500);
        }
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
