<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\ActivityLogger;

class RoleController extends Controller
{
    protected $activityLogger;

    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        
        if (!$this->isAdmin($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $roles = Role::orderBy('name', 'asc')->get();
            return response()->json($roles);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to retrieve roles: ' . $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $user = $request->user();
        
        if (!$this->isAdmin($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|unique:roles',
            'label' => 'required|string',
            'description' => 'nullable|string',
            'permission_ids' => 'nullable|array',
            'department' => 'nullable|string',
        ]);

        $role = Role::create($validated);

        $this->activityLogger->logUserAction($user, 'create_role', "Created new role: {$role->label}", [
            'role_id' => $role->id,
            'role_name' => $role->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'role' => $role
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        
        if (!$this->isAdmin($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $role = Role::findOrFail($id);
        $role->permissions = $role->permission_ids;
        
        return response()->json($role);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        
        if (!$this->isAdmin($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'label' => 'sometimes|string',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
            'permission_ids' => 'nullable|array',
            'department' => 'nullable|string',
        ]);

        $original = $role->toArray();
        $role->update($validated);

        $this->activityLogger->logUserAction($user, 'update_role', "Updated role: {$role->label}", [
            'role_id' => $role->id,
            'original' => $original,
            'changes' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'role' => $role
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        
        if (!$this->isAdmin($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $role = Role::findOrFail($id);

        if (in_array($role->name, ['admin', 'secretary', 'faculty'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete default system roles'
            ], 422);
        }

        if (User::where('role', $role->name)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete role with assigned users'
            ], 422);
        }

        $roleName = $role->label;
        $role->delete();

        $this->activityLogger->logUserAction($user, 'delete_role', "Deleted role: {$roleName}", [
            'role_id' => $id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully'
        ]);
    }

    public function permissions(Request $request)
    {
        $user = $request->user();
        
        if (!$this->isAdmin($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Fetch all and sort in PHP to be safe with MongoDB mixed fields
        $permissions = Permission::all()->sortBy(function($p) {
            return ($p->category ?? 'general') . '.' . $p->name;
        });
        
        $grouped = [];
        foreach ($permissions as $permission) {
            $category = $permission->category ?? 'general';
            if (empty($category)) $category = 'general';
            
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            
            $grouped[$category][] = [
                'id' => $permission->name,
                'name' => $permission->label ?? $permission->name,
                'description' => $permission->description ?? '',
            ];
        }

        // Sort keys to ensure 'general' or others are in a predictable order if desired
        ksort($grouped);

        return response()->json($grouped);
    }

    public function updatePermissions(Request $request, $id)
    {
        $user = $request->user();
        
        if (!$this->isAdmin($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'permission_ids' => 'required|array',
        ]);

        $originalPermissions = $role->permission_ids ?? [];
        $role->update([
            'permission_ids' => $validated['permission_ids']
        ]);

        $this->activityLogger->logUserAction($user, 'update_role_permissions', "Updated permissions for role: {$role->label}", [
            'role_id' => $role->id,
            'original' => $originalPermissions,
            'new' => $validated['permission_ids']
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permissions updated successfully',
            'role' => $role
        ]);
    }

    public function getRolePermissions(Request $request, $id)
    {
        $user = $request->user();
        
        if (!$this->isAdmin($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $role = Role::findOrFail($id);
        
        return response()->json([
            'role' => $role,
            'permissions' => $role->permission_ids ?? []
        ]);
    }

    public function assignRole(Request $request)
    {
        $user = $request->user();
        
        if (!$this->isAdmin($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_name' => 'required|string|exists:roles,name',
        ]);

        $targetUser = User::findOrFail($validated['user_id']);
        $oldRole = $targetUser->role;
        $newRole = Role::where('name', $validated['role_name'])->first();

        $targetUser->update([
            'role' => $validated['role_name']
        ]);

        $this->activityLogger->logUserAction($user, 'assign_role', "Assigned role {$newRole->label} to {$targetUser->email}", [
            'user_id' => $targetUser->id,
            'old_role' => $oldRole,
            'new_role' => $validated['role_name'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role assigned successfully',
            'user' => $targetUser
        ]);
    }

    private function isAdmin($user)
    {
        $roleField = strtolower($user->role ?? '');
        
        if ($roleField === 'admin' || $roleField === 'super_admin') {
            return true;
        }
        
        if ($user->role_id) {
            try {
                $roleModel = $user->assignedRole;
                if ($roleModel && strtolower($roleModel->name ?? '') === 'admin') {
                    return true;
                }
            } catch (\Exception $e) {
                return false;
            }
        }
        
        return false;
    }
}
