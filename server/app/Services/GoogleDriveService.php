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

    /**
     * Perform full backup of a memo and its attachments.
     */
    public function backupMemo($memo, $user)
    {
        Log::info('Initiating Google Drive backup via Service for memo: ' . $memo->id);
        try {
            // 0. Process attachments for PDF embedding (base64 for images)
            $processedAttachments = [];
            if (!empty($memo->attachments) && is_array($memo->attachments)) {
                foreach ($memo->attachments as $attachment) {
                    if (!is_array($attachment) || !isset($attachment['file_path'])) {
                        continue;
                    }

                    $item = [
                        'name' => $attachment['file_name'] ?? basename($attachment['file_path']),
                        'is_image' => false,
                        'base64' => null
                    ];

                    $extension = strtolower(pathinfo($attachment['file_path'], PATHINFO_EXTENSION));
                    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
                    
                    if (in_array($extension, $imageExtensions)) {
                        $item['is_image'] = true;
                        try {
                            if (Storage::disk('public')->exists($attachment['file_path'])) {
                                $content = Storage::disk('public')->get($attachment['file_path']);
                                if ($content) {
                                    $mime = Storage::disk('public')->mimeType($attachment['file_path']);
                                    if (strlen($content) < 2 * 1024 * 1024) {
                                        $item['base64'] = 'data:' . $mime . ';base64,' . base64_encode($content);
                                    } else {
                                        $item['is_image'] = false;
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            $item['is_image'] = false; 
                        }
                    }
                    $processedAttachments[] = $item;
                }
            }

            // 1. Generate PDF
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('emails.memo-notification', [
                'memo' => $memo,
                'recipient' => (object)['first_name' => 'Recipient', 'last_name' => ''],
                'sender' => $user,
                'type' => 'new_memo',
                'processedAttachments' => $processedAttachments
            ]);
            $pdfContent = $pdf->output();
            
            // 2. Upload PDF
            $creatorName = strtoupper(str_replace(' ', '', ($user->first_name . $user->last_name)));
            $dateStr = now()->format('m_d_Y');
            $fileName = "MEMOFY_{$dateStr}_{$creatorName}.pdf";

            $this->uploadContent($pdfContent, $fileName, 'application/pdf');

            // 3. Upload Original Attachments
            if (!empty($memo->attachments) && is_array($memo->attachments)) {
                foreach ($memo->attachments as $attachment) {
                    if (is_array($attachment) && isset($attachment['file_path'])) {
                        $this->uploadFile(
                            $attachment['file_path'], 
                            $attachment['file_name'] ?? basename($attachment['file_path'])
                        );
                    }
                }
            }
            Log::info('Google Drive backup completed via Service for memo: ' . $memo->id);
            return true;
        } catch (\Exception $e) {
            Log::error('Google Drive Backup Service Failed: ' . $e->getMessage());
            return false;
        }
    }
}
