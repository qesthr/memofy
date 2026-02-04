<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MemoTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MemoTemplateController extends Controller
{
    public function index()
    {
        return response()->json([
            'status' => 'success',
            'data' => MemoTemplate::where('user_id', Auth::id())
                ->with(['signature', 'department'])
                ->get()
        ]);
    }

    public function store(Request $request)
    {
        if (!$request->user()->hasPermissionTo('template.manage')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string',
            'signature_id' => 'nullable|exists:user_signatures,id',
            'department_id' => 'nullable|exists:departments,id',
            'priority' => 'nullable|string',
            'content' => 'nullable|string',
            'template_data' => 'nullable|array'
        ]);

        $template = MemoTemplate::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'signature_id' => $validated['signature_id'],
            'department_id' => $validated['department_id'],
            'priority' => $validated['priority'] ?? 'Medium',
            'content' => $validated['content'],
            'template_data' => $validated['template_data'] ?? []
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Template saved successfully',
            'data' => $template->load(['signature', 'department'])
        ], 201);
    }

    public function show(MemoTemplate $memoTemplate)
    {
        if ($memoTemplate->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => $memoTemplate->load(['signature', 'department'])
        ]);
    }

    public function update(Request $request, MemoTemplate $memoTemplate)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('template.manage') || ($memoTemplate->user_id !== $user->id && $user->role !== 'admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string',
            'signature_id' => 'nullable|exists:user_signatures,id',
            'department_id' => 'nullable|exists:departments,id',
            'priority' => 'nullable|string',
            'content' => 'nullable|string',
            'template_data' => 'nullable|array'
        ]);

        $memoTemplate->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Template updated successfully',
            'data' => $memoTemplate->load(['signature', 'department'])
        ]);
    }

    public function destroy(MemoTemplate $memoTemplate)
    {
        $user = auth()->user();
        if (!$user->hasPermissionTo('template.manage') || ($memoTemplate->user_id !== $user->id && $user->role !== 'admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $memoTemplate->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Template deleted successfully'
        ]);
    }
}
