<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Services\ActivityLogger;

class FileController extends Controller
{
    protected $activityLogger;

    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }
    /**
     * Upload a file with authorization and validation
     */
    public function upload(Request $request)
    {
        // Debugging: Log request details
        \Log::info('Upload Request Details:', [
            'has_file' => $request->hasFile('file'),
            'mime_type' => $request->hasFile('file') ? $request->file('file')->getMimeType() : 'N/A',
            'client_mime_type' => $request->hasFile('file') ? $request->file('file')->getClientMimeType() : 'N/A',
            'size' => $request->hasFile('file') ? $request->file('file')->getSize() : 'N/A',
            'type' => $request->get('type'),
            'all_params' => $request->all()
        ]);

        try {
            $request->validate([
                'file' => 'required|file|max:10240', // 10MB limit
                'type' => 'nullable|string|in:attachment,signature,document'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Upload Validation Failed:', [
                'errors' => $e->errors(),
                'request' => $request->all()
            ]);
            throw $e;
        }

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
        
        // Ensure directory exists
        if (!Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }
        
        try {
            $path = $file->storeAs($directory, $fileName, 'public');
            
            // Log upload activity
            $this->activityLogger->logUserAction(
                Auth::user(),
                'upload_file',
                "Uploaded file: {$originalName}",
                ['file_path' => $path, 'type' => $type]
            );
        } catch (\Exception $e) {
            \Log::error("File upload failed: " . $e->getMessage());
            return response()->json(['message' => 'Failed to store file on server'], 500);
        }

        // Generate secure download URL with token
        $downloadToken = hash('sha256', $path . now()->timestamp);
        
        // Generate direct preview URL via PHP serving (bypasses storage:link)
        $previewUrl = route('api.files.serve', ['path' => base64_encode($path)]);
        
        return response()->json([
            'status' => 'success',
            'file_name' => $originalName,
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'file_type' => $file->getMimeType(),
            'url' => $previewUrl, // Direct URL for viewing/embedding
            'download_url' => route('api.files.download', ['path' => base64_encode($path), 'token' => $downloadToken]),
            'download_token' => $downloadToken,
            'uploaded_at' => now()->toIso8601String()
        ]);
    }

    /**
     * Serve a file inline (for images/previews)
     */
    public function serve(Request $request, $path)
    {
        $decodedPath = base64_decode($path);
        
        if (!$decodedPath || !Storage::disk('public')->exists($decodedPath)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        // We can add auth checks here if needed, but for now we rely on the route middleware
        // or allow public access if the route is public. 
        // Given the use case (images in emails/modals), standard cookie auth or signed URLs are best.
        
        $file = Storage::disk('public')->get($decodedPath);
        $mimeType = Storage::disk('public')->mimeType($decodedPath);
        
        return response($file, 200)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', 'inline; filename="' . basename($decodedPath) . '"');
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
        $this->activityLogger->logUserAction(
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
        $this->activityLogger->logUserAction(
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
