<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use App\Services\ActivityLogger;

class RoleController extends Controller
{
    protected $activityLogger;

    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }

    public function index()
    {
        // Admin only check for this controller
        if (auth()->user()->getAttribute('role') !== 'admin' && 
            (!auth()->user()->assignedRole || auth()->user()->assignedRole->name !== 'admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $roles = Role::all();
        return response()->json($roles);
    }

    public function permissions()
    {
        if (auth()->user()->getAttribute('role') !== 'admin' && 
            (!auth()->user()->assignedRole || auth()->user()->assignedRole->name !== 'admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $permissions = Permission::all();
        
        // Group permissions by category (prefix before the dot)
        $grouped = [];
        foreach ($permissions as $permission) {
            $parts = explode('.', $permission->name);
            $category = count($parts) > 1 ? $parts[0] : 'general';
            
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            
            $grouped[$category][] = $permission;
        }

        return response()->json($grouped);
    }

    public function updatePermissions(Request $request, $id)
    {
        if (auth()->user()->getAttribute('role') !== 'admin' && 
            (!auth()->user()->assignedRole || auth()->user()->assignedRole->name !== 'admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $role = Role::findOrFail($id);
        
        $validated = $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'string'
        ]);

        $originalPermissions = $role->permission_ids;
        $role->update([
            'permission_ids' => $validated['permission_ids']
        ]);

        $this->activityLogger->logUserAction(auth()->user(), 'update_role_permissions', "Updated permissions for role: {$role->name}", [
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
    
    public function show($id)
    {
        if (auth()->user()->getAttribute('role') !== 'admin' && 
            (!auth()->user()->assignedRole || auth()->user()->assignedRole->name !== 'admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $role = Role::findOrFail($id);
        return response()->json($role);
    }
}
