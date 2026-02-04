<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB limit
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = Str::uuid() . '.' . $extension;
            
            $path = $file->storeAs('attachments', $fileName, 'public');

            return response()->json([
                'status' => 'success',
                'file_name' => $originalName,
                'file_path' => $path,
                'url' => Storage::url($path)
            ]);
        }

        return response()->json(['message' => 'No file uploaded'], 400);
    }

    public function download($path)
    {
        // Add security checks here if needed
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->download($path);
        }

        return response()->json(['message' => 'File not found'], 404);
    }
}
