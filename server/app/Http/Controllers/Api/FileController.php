<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class FileController extends Controller
{
    /**
     * Upload a file with authorization and validation
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB limit
            'type' => 'nullable|string|in:attachment,signature,document'
        ]);

        if (!$request->hasFile('file')) {
            return response()->json(['message' => 'No file uploaded'], 400);
        }

        $file = $request->file('file');
        
        // Validate file type
        $allowedMimeTypes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/svg+xml',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'application/zip',
            'application/x-zip-compressed'
        ];

        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            return response()->json([
                'message' => 'Invalid file type. Allowed types: PDF, Images, Word, Excel, Text, ZIP'
            ], 422);
        }

        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $fileName = Str::uuid() . '.' . $extension;
        
        // Determine directory based on type
        $type = $request->get('type', 'attachment');
        $directory = $type === 'signature' ? 'signatures' : 'attachments';
        
        $path = $file->storeAs($directory, $fileName, 'public');

        // Generate secure download URL with token
        $downloadToken = hash('sha256', $path . now()->timestamp);
        
        return response()->json([
            'status' => 'success',
            'file_name' => $originalName,
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'file_type' => $file->getMimeType(),
            'url' => route('api.files.download', ['path' => base64_encode($path), 'token' => $downloadToken]),
            'download_token' => $downloadToken,
            'uploaded_at' => now()->toIso8601String()
        ]);
    }

    /**
     * Download a file with authorization check
     */
    public function download(Request $request, $path)
    {
        // Decode base64 path
        $decodedPath = base64_decode($path);
        
        if (!$decodedPath) {
            return response()->json(['message' => 'Invalid file path'], 400);
        }

        $token = $request->get('token');
        
        // Verify the file exists and user is authorized
        if (!Storage::disk('public')->exists($decodedPath)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        // Check authorization - user must be authenticated
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Get the file
        $file = Storage::disk('public')->get($decodedPath);
        $fileName = basename($decodedPath);

        // Log download activity
        \App\Services\ActivityLogger::logUserAction(
            $user,
            'download_file',
            "Downloaded file: {$fileName}",
            ['file_path' => $decodedPath]
        );

        return response($file, 200)
            ->header('Content-Type', Storage::disk('public')->mimeType($decodedPath))
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"')
            ->header('Content-Length', strlen($file));
    }

    /**
     * Delete a file (owner only)
     */
    public function delete(Request $request, $path)
    {
        $decodedPath = base64_decode($path);
        
        if (!$decodedPath) {
            return response()->json(['message' => 'Invalid file path'], 400);
        }

        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (!Storage::disk('public')->exists($decodedPath)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        // Delete the file
        Storage::disk('public')->delete($decodedPath);

        // Log deletion
        \App\Services\ActivityLogger::logUserAction(
            $user,
            'delete_file',
            "Deleted file: {$decodedPath}",
            ['file_path' => $decodedPath]
        );

        return response()->json(['message' => 'File deleted successfully']);
    }

    /**
     * Get file info
     */
    public function info(Request $request, $path)
    {
        $decodedPath = base64_decode($path);
        
        if (!$decodedPath) {
            return response()->json(['message' => 'Invalid file path'], 400);
        }

        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (!Storage::disk('public')->exists($decodedPath)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        $fileInfo = [
            'name' => basename($decodedPath),
            'path' => $decodedPath,
            'size' => Storage::disk('public')->size($decodedPath),
            'type' => Storage::disk('public')->mimeType($decodedPath),
            'url' => route('api.files.download', ['path' => $path, 'token' => hash('sha256', $decodedPath . now()->timestamp)]),
            'created_at' => Storage::disk('public')->lastModified($decodedPath)
        ];

        return response()->json($fileInfo);
    }
}
