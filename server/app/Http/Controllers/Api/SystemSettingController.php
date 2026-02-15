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
        
        // Only Admin can view/edit these settings
        if (!$this->isAdmin($user)) {
             return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Fetch all settings or specific ones
        $settings = SystemSetting::all();
        
        // Transform into key-value pair for easier frontend consumption
        $formatted = [];
        foreach ($settings as $setting) {
            $formatted[$setting->key] = $setting->value;
        }

        // Ensure default for allowed_email_domains if not present
        if (!isset($formatted['allowed_email_domains'])) {
            $formatted['allowed_email_domains'] = ['buksu.edu.ph', 'student.buksu.edu.ph'];
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
            'allowed_email_domains.*' => 'string|distinct'
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
