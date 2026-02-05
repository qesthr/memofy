<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LockService;
use Illuminate\Http\Request;

class LockController extends Controller
{
    protected $lockService;

    public function __construct(LockService $lockService)
    {
        $this->lockService = $lockService;
    }

    public function acquire(Request $request)
    {
        $user = $request->user();
        
        if (!$user->hasPermissionTo('faculty.edit')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'resource_type' => 'required|string|in:user',
            'resource_id' => 'required|string',
        ]);

        $user = $request->user();
        $resourceType = $request->resource_type;
        $resourceId = $request->resource_id;

        $result = $this->lockService->acquireLock($resourceType, $resourceId, $user);

        return response()->json($result);
    }

    public function release(Request $request)
    {
        $request->validate([
            'resource_type' => 'required|string|in:user',
            'resource_id' => 'required|string',
        ]);

        $user = $request->user();
        $resourceType = $request->resource_type;
        $resourceId = $request->resource_id;

        $result = $this->lockService->releaseLock($resourceType, $resourceId, $user);

        return response()->json($result);
    }

    public function forceRelease(Request $request)
    {
        $request->validate([
            'resource_type' => 'required|string|in:user',
            'resource_id' => 'required|string',
        ]);

        $resourceType = $request->resource_type;
        $resourceId = $request->resource_id;

        $result = $this->lockService->forceReleaseLock($resourceType, $resourceId);

        return response()->json($result);
    }

    public function status(Request $request)
    {
        $request->validate([
            'resource_type' => 'required|string|in:user',
            'resource_id' => 'required|string',
        ]);

        $resourceType = $request->resource_type;
        $resourceId = $request->resource_id;

        $result = $this->lockService->getLockStatus($resourceType, $resourceId);

        return response()->json($result);
    }

    public function heartbeat(Request $request)
    {
        $request->validate([
            'resource_type' => 'required|string|in:user',
            'resource_id' => 'required|string',
        ]);

        $user = $request->user();
        $resourceType = $request->resource_type;
        $resourceId = $request->resource_id;

        $result = $this->lockService->acquireLock($resourceType, $resourceId, $user);

        return response()->json($result);
    }

    public function myLocks(Request $request)
    {
        $user = $request->user();
        $locks = $this->lockService->getActiveLocks($user);

        return response()->json([
            'locks' => $locks,
            'count' => $locks->count(),
        ]);
    }

    public function allLocks(Request $request)
    {
        $locks = $this->lockService->getActiveLocks();

        return response()->json([
            'locks' => $locks,
            'count' => $locks->count(),
        ]);
    }

    public function getSettings(Request $request)
    {
        $duration = $this->lockService->getLockDuration();

        return response()->json([
            'lock_duration' => $duration,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermissionTo('settings.lock_duration')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'minutes' => 'required|integer|min:0|max:60',
            'seconds' => 'required|integer|min:0|max:59',
        ]);

        $duration = $this->lockService->setLockDuration(
            $request->minutes,
            $request->seconds
        );

        return response()->json([
            'success' => true,
            'message' => 'Lock settings updated successfully',
            'lock_duration' => $duration,
        ]);
    }
}
