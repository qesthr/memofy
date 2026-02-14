<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use MongoDB\BSON\ObjectId;

class DepartmentController extends Controller
{
    public function index()
    {
        return response()->json([
            'status' => 'success',
            'data' => Department::all()
        ]);
    }

    /**
     * Get members of a specific department or the authenticated user's department.
     */
    public function members(Request $request, $departmentId = null)
    {
        $user = $request->user();
        
        // If no department ID provided, use the user's department
        if (!$departmentId) {
            $departmentId = $user->department_id;
        }
        
        if (!$departmentId) {
            return response()->json([
                'status' => 'success',
                'data' => []
            ]);
        }
        
        // Normalize department ID for MongoDB comparison
        $normalizedDeptId = $departmentId;
        if (is_string($departmentId) && strlen($departmentId) === 24) {
            try {
                $normalizedDeptId = new ObjectId($departmentId);
            } catch (\Exception $e) {
                // Keep as string if conversion fails
            }
        }
        
        // Normalize user ID for exclusion
        $userId = $user->id;
        $normalizedUserId = $userId;
        if (is_string($userId) && strlen($userId) === 24) {
            try {
                $normalizedUserId = new ObjectId($userId);
            } catch (\Exception $e) {
                // Keep as string if conversion fails
            }
        }
        
        // Get members excluding the current user - check both string and ObjectId formats
        $members = User::where('department_id', $normalizedDeptId)
            ->where('_id', '!=', $normalizedUserId)
            ->get(['_id', 'id', 'first_name', 'last_name', 'email', 'role', 'profile_picture']);
        
        // If no results, try with string department_id
        if ($members->isEmpty() && $normalizedDeptId !== $departmentId) {
            $members = User::where('department_id', (string) $departmentId)
                ->where('_id', '!=', $normalizedUserId)
                ->get(['_id', 'id', 'first_name', 'last_name', 'email', 'role', 'profile_picture']);
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $members
        ]);
    }

    public function store(Request $request)
    {
        if (!$request->user()->hasPermissionTo('department.manage')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|unique:departments,name',
            'code' => 'nullable|string|unique:departments,code',
            'description' => 'nullable|string'
        ]);

        $department = Department::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Department created successfully',
            'data' => $department
        ], 201);
    }

    public function show(Department $department)
    {
        return response()->json([
            'status' => 'success',
            'data' => $department
        ]);
    }

    public function update(Request $request, Department $department)
    {
        if (!$request->user()->hasPermissionTo('department.manage')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|unique:departments,name,' . $department->id,
            'code' => 'nullable|string|unique:departments,code,' . $department->id,
            'description' => 'nullable|string'
        ]);

        $department->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Department updated successfully',
            'data' => $department
        ]);
    }

    public function destroy(Department $department)
    {
        if (auth()->user()->hasPermissionTo('department.manage')) {
            $department->delete();
        } else {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Department deleted successfully'
        ]);
    }
}
