<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class CheckServicesStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'services:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the status of all configured APIs and services';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->displayBanner();
        $this->checkAllServices();
        $this->newLine();
        $this->displaySummary();
        
        return Command::SUCCESS;
    }

    /**
     * Display the application banner.
     */
    protected function displayBanner()
    {
        $this->newLine();
        $this->line('<fg=cyan;bold>================================================</>');
        $this->line('<fg=cyan;bold>          BUKSU MEMOFY - SERVICE STATUS         </>');
        $this->line('<fg=cyan;bold>================================================</>');
        $this->newLine();
    }

    /**
     * Check all configured services.
     */
    protected function checkAllServices()
    {
        $services = [
            'Database (MongoDB)' => fn() => $this->checkDatabase(),
            'Google OAuth' => fn() => $this->checkGoogleOAuth(),
            'Google Drive API' => fn() => $this->checkGoogleDrive(),
            'Google Calendar API' => fn() => $this->checkGoogleCalendar(),
            'Google Analytics API' => fn() => $this->checkGoogleAnalytics(),
            'SMTP Mail' => fn() => $this->checkSmtp(),
            'reCAPTCHA' => fn() => $this->checkRecaptcha(),
            'Frontend URL' => fn() => $this->checkFrontendUrl(),
        ];

        foreach ($services as $name => $checker) {
            $result = $checker();
            $this->displayServiceStatus($name, $result);
        }
    }

    /**
     * Display service status with colored output.
     */
    protected function displayServiceStatus(string $name, array $result)
    {
        $status = $result['status'];
        $message = $result['message'] ?? '';

        $statusIcon = $status === 'ok' ? '<fg=green>[OK]</>' : 
                      ($status === 'warning' ? '<fg=yellow>[WARNING]</>' : '<fg=red>[FAILED]</>');
        
        $statusText = $status === 'ok' ? '<fg=green>Connected</>' : 
                      ($status === 'warning' ? '<fg=yellow>Configured (Not Verified)</>' : '<fg=red>Error</>');

        $this->line(sprintf('  %-25s %s %s', $name, $statusIcon, $statusText));
        
        if ($message && $status !== 'ok') {
            $this->line(sprintf('    <fg=gray>%s</>', $message));
        }
    }

    /**
     * Check database connection.
     */
    protected function checkDatabase(): array
    {
        try {
            $connection = config('database.default');
            
            if ($connection === 'mongodb') {
                // Test MongoDB connection
                $client = DB::connection('mongodb')->getMongoClient();
                $client->listDatabases();
                
                return [
                    'status' => 'ok',
                    'message' => 'MongoDB connection successful'
                ];
            }
            
            // Test standard database connection
            DB::connection()->getPdo();
            
            return [
                'status' => 'ok',
                'message' => 'Database connection successful'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'message' => 'Connection failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check Google OAuth configuration.
     */
    protected function checkGoogleOAuth(): array
    {
        $clientId = env('GOOGLE_CLIENT_ID');
        $clientSecret = env('GOOGLE_CLIENT_SECRET');
        $callbackUrl = env('GOOGLE_CALLBACK_URL');

        if (empty($clientId) || empty($clientSecret)) {
            return [
                'status' => 'failed',
                'message' => 'Google OAuth credentials not configured'
            ];
        }

        if (empty($callbackUrl)) {
            return [
                'status' => 'warning',
                'message' => 'Callback URL not set'
            ];
        }

        // Verify the credentials format
        if (!str_contains($clientId, '.apps.googleusercontent.com')) {
            return [
                'status' => 'warning',
                'message' => 'Client ID format may be invalid'
            ];
        }

        return [
            'status' => 'ok',
            'message' => 'Google OAuth configured'
        ];
    }

    /**
     * Check Google Drive API configuration.
     */
    protected function checkGoogleDrive(): array
    {
        $clientId = env('GOOGLE_DRIVE_CLIENT_ID');
        $clientSecret = env('GOOGLE_DRIVE_CLIENT_SECRET');
        $folderId = env('GOOGLE_DRIVE_FOLDER_ID');

        if (empty($clientId) || empty($clientSecret)) {
            return [
                'status' => 'failed',
                'message' => 'Google Drive credentials not configured'
            ];
        }

        if (empty($folderId)) {
            return [
                'status' => 'warning',
                'message' => 'Folder ID not set'
            ];
        }

        return [
            'status' => 'ok',
            'message' => 'Google Drive API configured'
        ];
    }

    /**
     * Check Google Calendar API configuration.
     */
    protected function checkGoogleCalendar(): array
    {
        $clientId = env('GOOGLE_CALENDAR_CLIENT_ID');
        $clientSecret = env('GOOGLE_CALENDAR_CLIENT_SECRET');
        $apiKey = env('GOOGLE_CALENDAR_API_KEY');

        if (empty($clientId) || empty($clientSecret)) {
            return [
                'status' => 'failed',
                'message' => 'Google Calendar credentials not configured'
            ];
        }

        if (empty($apiKey)) {
            return [
                'status' => 'warning',
                'message' => 'API Key not set'
            ];
        }

        return [
            'status' => 'ok',
            'message' => 'Google Calendar API configured'
        ];
    }

    /**
     * Check Google Analytics API configuration.
     */
    protected function checkGoogleAnalytics(): array
    {
        $clientId = env('GOOGLE_ANALYTICS_CLIENT_ID');
        $clientSecret = env('GOOGLE_ANALYTICS_CLIENT_SECRET');
        $propertyId = env('GOOGLE_ANALYTICS_PROPERTY_ID');

        if (empty($clientId) || empty($clientSecret)) {
            return [
                'status' => 'failed',
                'message' => 'Google Analytics credentials not configured'
            ];
        }

        if (empty($propertyId)) {
            return [
                'status' => 'warning',
                'message' => 'Property ID not set'
            ];
        }

        return [
            'status' => 'ok',
            'message' => 'Google Analytics API configured'
        ];
    }

    /**
     * Check SMTP mail configuration.
     */
    protected function checkSmtp(): array
    {
        $mailer = env('MAIL_MAILER');
        $host = env('MAIL_HOST');
        $port = env('MAIL_PORT');
        $username = env('MAIL_USERNAME');
        $password = env('MAIL_PASSWORD');

        if (empty($mailer) || $mailer === 'log') {
            return [
                'status' => 'warning',
                'message' => 'Mail is set to log mode'
            ];
        }

        if (empty($host) || empty($port) || empty($username) || empty($password)) {
            return [
                'status' => 'failed',
                'message' => 'SMTP configuration incomplete'
            ];
        }

        // Try to verify SMTP connection
        try {
            $socket = @fsockopen($host, $port, $errno, $errstr, 5);
            if ($socket) {
                fclose($socket);
                return [
                    'status' => 'ok',
                    'message' => 'SMTP server reachable'
                ];
            }
        } catch (\Exception $e) {
            // Fall through to warning
        }

        return [
            'status' => 'warning',
            'message' => 'SMTP configured but server not verified'
        ];
    }

    /**
     * Check reCAPTCHA configuration.
     */
    protected function checkRecaptcha(): array
    {
        $secret = env('RECAPTCHA_SECRET');
        $siteKey = env('RECAPTCHA_SITE_KEY');
        $bypass = env('BYPASS_RECAPTCHA', false);

        if ($bypass) {
            return [
                'status' => 'warning',
                'message' => 'reCAPTCHA is bypassed (development mode)'
            ];
        }

        if (empty($secret) || empty($siteKey)) {
            return [
                'status' => 'failed',
                'message' => 'reCAPTCHA keys not configured'
            ];
        }

        return [
            'status' => 'ok',
            'message' => 'reCAPTCHA configured'
        ];
    }

    /**
     * Check Frontend URL configuration.
     */
    protected function checkFrontendUrl(): array
    {
        $frontendUrl = env('APP_FRONTEND_URL');

        if (empty($frontendUrl)) {
            return [
                'status' => 'warning',
                'message' => 'Frontend URL not set'
            ];
        }

        // Try to reach the frontend
        try {
            $response = @get_headers($frontendUrl, 1);
            if ($response !== false) {
                return [
                    'status' => 'ok',
                    'message' => 'Frontend URL reachable'
                ];
            }
        } catch (\Exception $e) {
            // Fall through
        }

        return [
            'status' => 'warning',
            'message' => "Configured: $frontendUrl"
        ];
    }

    /**
     * Display summary of all services.
     */
    protected function displaySummary()
    {
        $this->line('<fg=cyan;bold>================================================</>');
        $this->newLine();
        $this->line('<fg=white>Server running at: </><fg=green;bold>' . env('APP_URL', 'http://localhost:8000') . '</>');
        $this->line('<fg=white>Frontend URL: </><fg=green;bold>' . env('APP_FRONTEND_URL', 'http://localhost:5173') . '</>');
        $this->line('<fg=white>Environment: </><fg=yellow;bold>' . env('APP_ENV', 'local') . '</>');
        $this->line('<fg=white>Debug Mode: </><fg=yellow;bold>' . (env('APP_DEBUG', false) ? 'ON' : 'OFF') . '</>');
        $this->newLine();
        $this->line('<fg=cyan;bold>================================================</>');
        $this->newLine();
        $this->line('<fg=gray>Press Ctrl+C to stop the server</>');
        $this->newLine();
    }
}