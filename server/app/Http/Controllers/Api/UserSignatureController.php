<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserSignatureController extends Controller
{
    public function index()
    {
        return response()->json([
            'status' => 'success',
            'data' => UserSignature::where('user_id', Auth::id())->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'signature_data' => 'required|string',
            'is_default' => 'boolean'
        ]);

        // If setting as default, unset other defaults for this user
        if ($request->is_default) {
            UserSignature::where('user_id', Auth::id())->update(['is_default' => false]);
        }

        $signature = UserSignature::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'signature_data' => $validated['signature_data'],
            'is_default' => $validated['is_default'] ?? false
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Signature saved successfully',
            'data' => $signature
        ], 201);
    }

    public function destroy(UserSignature $userSignature)
    {
        if ($userSignature->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $userSignature->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Signature deleted successfully'
        ]);
    }

    public function setDefault(UserSignature $userSignature)
    {
        if ($userSignature->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        UserSignature::where('user_id', Auth::id())->update(['is_default' => false]);
        $userSignature->update(['is_default' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'Default signature updated'
        ]);
    }
}
