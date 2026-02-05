<?php

namespace App\Services;

use App\Models\UserLock;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LockService
{
    const DEFAULT_LOCK_MINUTES = 1;
    const DEFAULT_LOCK_SECONDS = 50;

    const RESOURCE_TYPE_USER = 'user';

    public function acquireLock($resourceType, $resourceId, $adminUser)
    {
        $lockDuration = $this->getLockDuration();

        $existingLock = UserLock::where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->first();

        if ($existingLock) {
            if ($existingLock->isExpired()) {
                $existingLock->delete();
            } elseif (!$existingLock->isLockedBy($adminUser->id)) {
                return [
                    'success' => false,
                    'locked' => true,
                    'message' => 'This user is currently being edited by another administrator',
                    'locked_by' => [
                        'id' => $existingLock->locked_by_id,
                        'name' => $existingLock->locked_by_name,
                        'email' => $existingLock->locked_by_email,
                    ],
                    'expires_at' => $existingLock->expires_at->toIso8601String(),
                    'seconds_remaining' => $existingLock->secondsRemaining(),
                ];
            } else {
                $existingLock->update([
                    'expires_at' => now()->addMinutes($lockDuration['minutes'])->addSeconds($lockDuration['seconds']),
                ]);
                return [
                    'success' => true,
                    'locked' => true,
                    'message' => 'Lock refreshed',
                    'expires_at' => $existingLock->expires_at->toIso8601String(),
                    'seconds_remaining' => $existingLock->secondsRemaining(),
                ];
            }
        }

        $lock = UserLock::create([
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'user_id' => $resourceId,
            'locked_by_id' => $adminUser->id,
            'locked_by_email' => $adminUser->email,
            'locked_by_name' => $adminUser->full_name ?? $adminUser->email,
            'locked_at' => now(),
            'expires_at' => now()->addMinutes($lockDuration['minutes'])->addSeconds($lockDuration['seconds']),
        ]);

        return [
            'success' => true,
            'locked' => true,
            'message' => 'Lock acquired successfully',
            'lock_id' => $lock->id,
            'expires_at' => $lock->expires_at->toIso8601String(),
            'seconds_remaining' => $lock->secondsRemaining(),
        ];
    }

    public function releaseLock($resourceType, $resourceId, $adminUser)
    {
        $lock = UserLock::where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->first();

        if (!$lock) {
            return [
                'success' => true,
                'message' => 'No active lock found',
            ];
        }

        if (!$lock->isLockedBy($adminUser->id)) {
            return [
                'success' => false,
                'message' => 'You do not own this lock',
            ];
        }

        $lock->delete();

        return [
            'success' => true,
            'message' => 'Lock released successfully',
        ];
    }

    public function forceReleaseLock($resourceType, $resourceId)
    {
        $lock = UserLock::where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->first();

        if (!$lock) {
            return [
                'success' => true,
                'message' => 'No active lock found',
            ];
        }

        $lock->delete();

        return [
            'success' => true,
            'message' => 'Lock forcefully released',
        ];
    }

    public function getLockStatus($resourceType, $resourceId)
    {
        $lock = UserLock::where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->first();

        if (!$lock) {
            return [
                'locked' => false,
                'message' => 'No lock exists',
            ];
        }

        if ($lock->isExpired()) {
            $lock->delete();
            return [
                'locked' => false,
                'message' => 'Lock has expired',
            ];
        }

        return [
            'locked' => true,
            'locked_by' => [
                'id' => $lock->locked_by_id,
                'name' => $lock->locked_by_name,
                'email' => $lock->locked_by_email,
            ],
            'locked_at' => $lock->locked_at->toIso8601String(),
            'expires_at' => $lock->expires_at->toIso8601String(),
            'seconds_remaining' => $lock->secondsRemaining(),
        ];
    }

    public function getActiveLocks($adminUser = null)
    {
        $query = UserLock::query();

        if ($adminUser) {
            $query->where('locked_by_id', $adminUser->id);
        }

        $locks = $query->get()->map(function ($lock) {
            if ($lock->isExpired()) {
                $lock->delete();
                return null;
            }
            return [
                'id' => $lock->id,
                'resource_type' => $lock->resource_type,
                'resource_id' => $lock->resource_id,
                'locked_by' => [
                    'id' => $lock->locked_by_id,
                    'name' => $lock->locked_by_name,
                    'email' => $lock->locked_by_email,
                ],
                'locked_at' => $lock->locked_at->toIso8601String(),
                'expires_at' => $lock->expires_at->toIso8601String(),
                'seconds_remaining' => $lock->secondsRemaining(),
            ];
        })->filter();

        return $locks->values();
    }

    public function extendLock($resourceType, $resourceId, $adminUser, $additionalMinutes = 0, $additionalSeconds = 0)
    {
        $lock = UserLock::where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->first();

        if (!$lock) {
            return [
                'success' => false,
                'message' => 'No lock found to extend',
            ];
        }

        if (!$lock->isLockedBy($adminUser->id)) {
            return [
                'success' => false,
                'message' => 'You do not own this lock',
            ];
        }

        $lock->update([
            'expires_at' => $lock->expires_at->addMinutes($additionalMinutes)->addSeconds($additionalSeconds),
        ]);

        return [
            'success' => true,
            'message' => 'Lock extended successfully',
            'expires_at' => $lock->expires_at->toIso8601String(),
            'seconds_remaining' => $lock->secondsRemaining(),
        ];
    }

    public function getLockDuration()
    {
        $minutes = SystemSetting::where('key', 'user_edit_lock_minutes')->value('value');
        $seconds = SystemSetting::where('key', 'user_edit_lock_seconds')->value('value');

        return [
            'minutes' => $minutes ?? self::DEFAULT_LOCK_MINUTES,
            'seconds' => $seconds ?? self::DEFAULT_LOCK_SECONDS,
        ];
    }

    public function setLockDuration($minutes, $seconds)
    {
        SystemSetting::updateOrCreate(
            ['key' => 'user_edit_lock_minutes'],
            ['value' => $minutes, 'type' => 'integer', 'description' => 'User edit lock timeout (minutes)']
        );

        SystemSetting::updateOrCreate(
            ['key' => 'user_edit_lock_seconds'],
            ['value' => $seconds, 'type' => 'integer', 'description' => 'User edit lock timeout (seconds)']
        );

        return [
            'minutes' => $minutes,
            'seconds' => $seconds,
        ];
    }

    public function cleanupExpiredLocks()
    {
        return UserLock::where('expires_at', '<', now())->delete();
    }
}
