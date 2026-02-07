<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        // Bypass permission check for admin users
        $isAdmin = $user && (
            strtolower($user->getAttribute('role') ?? '') === 'admin' || 
            ($user->assignedRole && strtolower($user->assignedRole->name ?? '') === 'admin')
        );

        if ($isAdmin) {
            return $next($request);
        }

        if (!$user || !$user->hasPermissionTo($permission)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        return $next($request);
    }
}
