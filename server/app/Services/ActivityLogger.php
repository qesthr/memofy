<?php

namespace App\Services;

use App\Models\UserActivityLog;
use Illuminate\Http\Request;

class ActivityLogger
{
    /**
     * Log an authentication action (login, logout, failed login)
     */
    public function logAuthAction($user, $action, $description, $metadata = [])
    {
        $this->createLog($user, $action, $user->email . ' - ' . $description, null, $metadata);
    }

    /**
     * Log a user action on a target (user update, memo creation, etc.)
     */
    public function logUserAction($actor, $action, $target, $metadata = [])
    {
        $targetId = is_object($target) ? ($target->id ?? null) : null;
        $targetDescription = $this->getTargetDescription($target);
        
        $this->createLog($actor, $action, $description = null, $targetId, $metadata, $targetDescription);
    }

    /**
     * Core logging methods
     */
    protected function createLog($actor, $action, $description, $targetId = null, $metadata = [], $targetDesc = null)
    {
        try {
            // Extract request info from metadata if passed, or global request if available
            $ip = $metadata['ip'] ?? request()->ip();
            $userAgent = $metadata['user_agent'] ?? request()->userAgent();
            
            // Clean metadata
            unset($metadata['ip']);
            unset($metadata['user_agent']);

            // Build description if not provided
            if (!$description && $targetDesc) {
                $description = ucfirst(str_replace('_', ' ', $action)) . ': ' . $targetDesc;
            }

            UserActivityLog::create([
                'actor_id' => $actor->id ?? null,
                'actor_email' => $actor->email ?? 'system',
                'actor_role' => $actor->role ?? 'system',
                'actor_department' => $actor->department ?? null,
                'action' => $action,
                'target' => $targetDesc ?? 'System',
                'target_id' => $targetId,
                'description' => $description ?? $action,
                'details' => $metadata,
                'ip_address' => $ip,
                'user_agent' => $userAgent
            ]);
        } catch (\Exception $e) {
            // Fallback logging to file if database logging fails
            \Log::error('Activity Logger Failed: ' . $e->getMessage());
        }
    }

    protected function getTargetDescription($target)
    {
        if (is_string($target)) return $target;
        
        if ($target instanceof \App\Models\User) {
            return "User: {$target->email}";
        }
        
        if ($target instanceof \App\Models\Memo) {
            return "Memo: {$target->subject}";
        }
        
        return class_basename($target);
    }

    public function extractRequestInfo(Request $request)
    {
        return [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ];
    }
}
