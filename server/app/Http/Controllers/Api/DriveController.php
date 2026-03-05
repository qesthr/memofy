<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class DriveController extends Controller
{
    protected $driveService;

    public function __construct(GoogleDriveService $driveService)
    {
        $this->driveService = $driveService;
    }

    public function connect(Request $request)
    {
        // Removed auth check for local connection convenience
        // $user = $request->user();
        // if ($user->role !== 'admin') {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $authUrl = $this->driveService->getClient()->createAuthUrl();

        return redirect($authUrl);
    }

    public function callback(Request $request)
    {
        $code = $request->input('code');
        if (!$code) {
            return $this->returnPopupError('Authorization failed: No code provided.');
        }

        try {
            $token = $this->driveService->getClient()->fetchAccessTokenWithAuthCode($code);
            
            if (isset($token['error'])) {
                return $this->returnPopupError('Token error: ' . $token['error_description']);
            }

            // Store token in system settings
            SystemSetting::set('google_drive_token', $token, auth()->id());

            return $this->returnPopupSuccess();
        } catch (\Exception $e) {
            Log::error('Google Drive OAuth Error: ' . $e->getMessage());
            return $this->returnPopupError('Exception: ' . $e->getMessage());
        }
    }

    private function returnPopupSuccess()
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<body>
<script>
    if (window.opener) {
        window.opener.postMessage({ type: 'GOOGLE_DRIVE_CONNECTED' }, '*');
        window.close();
    } else {
        document.body.innerHTML = '<h1>Google Drive Connected Successfully!</h1><p>You can close this window now.</p>';
    }
</script>
</body>
</html>
HTML;
        return response($html);
    }

    private function returnPopupError($message)
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<body>
<script>
    if (window.opener) {
        window.opener.postMessage({ type: 'GOOGLE_DRIVE_FAILURE', error: "$message" }, '*');
        window.close();
    } else {
        document.body.innerHTML = '<h1>Connection Failed</h1><p>$message</p>';
    }
</script>
</body>
</html>
HTML;
        return response($html);
    }
}
