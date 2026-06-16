<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CheckRole Middleware
 *
 * Guards routes by verifying the authenticated user holds
 * one of the required roles.
 *
 * Registration (bootstrap/app.php — Laravel 11):
 *   ->withMiddleware(function (Middleware $middleware) {
 *       $middleware->alias([
 *           'role' => \App\Http\Middleware\CheckRole::class,
 *       ]);
 *   })
 *
 * Usage in routes:
 *   ->middleware('role:admin')
 *   ->middleware('role:admin,vendor')   // either role is accepted
 *
 * The middleware accepts a comma-separated list of role names.
 * Access is granted when the user's role matches ANY of the listed names.
 */
class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param string $roles  Comma-separated allowed role names (e.g. 'admin,vendor')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Auth guard is already applied by auth:sanctum before this middleware,
        // so $request->user() is guaranteed to be non-null here.
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Resolve the user's role name (eager-load if necessary)
        $userRoleName = $user->role->name ?? null;

        if (!$userRoleName || !in_array($userRoleName, $roles, true)) {
            return response()->json([
                'message' => 'Forbidden. You do not have permission to perform this action.',
                'required_roles' => $roles,
                'your_role'      => $userRoleName,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
