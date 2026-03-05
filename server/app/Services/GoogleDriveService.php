<?php

namespace App\Services;

use App\Models\SystemSetting;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GoogleDriveService
{
    protected $client;
    protected $folderId;

    public function __construct()
    {
        $this->client = new GoogleClient();
        $this->client->setClientId(config('services.google_drive.client_id'));
        $this->client->setClientSecret(config('services.google_drive.client_secret'));
        $this->client->setRedirectUri(config('services.google_drive.redirect'));
        $this->client->addScope(GoogleDrive::DRIVE_FILE);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');

        $this->folderId = config('services.google_drive.folder_id');
    }

    /**
     * Get the Google Client instance.
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Initialize the client with stored tokens.
     */
    protected function authorize()
    {
        $token = SystemSetting::get('google_drive_token');
        if (!$token) {
            return false;
        }

        $this->client->setAccessToken($token);

        if ($this->client->isAccessTokenExpired()) {
            $refreshToken = $this->client->getRefreshToken();
            if ($refreshToken) {
                $newToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
                SystemSetting::set('google_drive_token', $newToken);
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Upload a file to Google Drive.
     */
    public function uploadFile($filePath, $fileName, $mimeType = null)
    {
        if (!$this->authorize()) {
            Log::error('Google Drive not authorized.');
            return null;
        }

        $driveService = new GoogleDrive($this->client);

        $fileMetadata = new DriveFile([
            'name' => $fileName,
            'parents' => [$this->folderId]
        ]);

        $content = Storage::disk('public')->get($filePath);
        
        try {
            $file = $driveService->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => $mimeType ?? Storage::disk('public')->mimeType($filePath),
                'uploadType' => 'multipart',
                'fields' => 'id'
            ]);

            return $file->id;
        } catch (\Exception $e) {
            Log::error('Google Drive Upload Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Upload raw content as a file to Google Drive.
     */
    public function uploadContent($content, $fileName, $mimeType)
    {
        if (!$this->authorize()) {
            Log::error('Google Drive not authorized.');
            return null;
        }

        $driveService = new GoogleDrive($this->client);

        $fileMetadata = new DriveFile([
            'name' => $fileName,
            'parents' => [$this->folderId]
        ]);

        try {
            $file = $driveService->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id'
            ]);

            return $file->id;
        } catch (\Exception $e) {
            Log::error('Google Drive Upload Content Error: ' . $e->getMessage());
            return null;
        }
    }
}
