<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        return response()->json([
            'status' => 'success',
            'data' => Department::all()
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
