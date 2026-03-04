<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use App\Services\ActivityLogger;

class SystemSettingController extends Controller
{
    protected $activityLogger;

    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        
        // Fetch all settings or specific ones
        $settings = SystemSetting::all();
        
        // Transform into key-value pair for easier frontend consumption
        $formatted = [];
        foreach ($settings as $setting) {
            $formatted[$setting->key] = $setting->value;
        }

        // Ensure default for login_lockout_minutes if not present
        $formatted['login_lockout_minutes'] = (int) ($formatted['login_lockout_minutes'] ?? 15);
        if ($formatted['login_lockout_minutes'] < 1) {
            $formatted['login_lockout_minutes'] = 15;
        }

        // Ensure default for session_timeout_minutes if not present
        $formatted['session_timeout_minutes'] = (int) ($formatted['session_timeout_minutes'] ?? 30);
        if ($formatted['session_timeout_minutes'] < 1) {
            $formatted['session_timeout_minutes'] = 30;
        }

        return response()->json($formatted);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        if (!$this->isAdmin($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'allowed_email_domains' => 'nullable|array',
            'allowed_email_domains.*' => 'string|distinct',
            'login_lockout_minutes' => 'nullable|integer|min:1|max:60',
            'session_timeout_minutes' => 'nullable|integer|min:1|max:1440'
        ]);

        $settings = [];

        if (array_key_exists('allowed_email_domains', $validated)) {
            $domains = $validated['allowed_email_domains'] ?? [];
            
            // Enforce default immutable domains
            $defaultDomains = ['buksu.edu.ph'];
            $domains = array_unique(array_merge($domains, $defaultDomains));
            // Re-index array to be clean
            $domains = array_values($domains);

            SystemSetting::set('allowed_email_domains', $domains, $user->id);
            $settings['allowed_email_domains'] = $domains;
            
            // Log activity
            $this->activityLogger->logUserAction($user, 'update_system_settings', "Updated allowed email domains", [
                'domains' => $domains
            ]);
        }

        if (array_key_exists('login_lockout_minutes', $validated) && !is_null($validated['login_lockout_minutes'])) {
            $minutes = intval($validated['login_lockout_minutes']);
            SystemSetting::set('login_lockout_minutes', $minutes, $user->id);
            $settings['login_lockout_minutes'] = $minutes;

            $this->activityLogger->logUserAction($user, 'update_system_settings', "Updated login lockout duration to {$minutes} minutes");
        }

        if (array_key_exists('session_timeout_minutes', $validated) && !is_null($validated['session_timeout_minutes'])) {
            $timeoutMinutes = intval($validated['session_timeout_minutes']);
            SystemSetting::set('session_timeout_minutes', $timeoutMinutes, $user->id);
            $settings['session_timeout_minutes'] = $timeoutMinutes;

            $this->activityLogger->logUserAction($user, 'update_system_settings', "Updated session timeout duration to {$timeoutMinutes} minutes");
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
            'settings' => $settings
        ]);
    }
    
    // Helper helper for admin check (duplicated from other controllers, ideally trait/service)
    private function isAdmin($user)
    {
        $roleField = strtolower($user->role ?? '');
        
        if ($roleField === 'admin' || $roleField === 'super_admin') {
            return true;
        }
        
        if ($user->role_id) {
            try {
                $roleModel = $user->assignedRole;
                if ($roleModel && strtolower($roleModel->name ?? '') === 'admin') {
                    return true;
                }
            } catch (\Exception $e) {
                return false;
            }
        }
        
        return false;
    }
}
