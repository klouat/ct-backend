<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
                'errors' => [],
            ], 401);
        }

        $roles = array_map('trim', array_filter(explode('|', $role)));

        if (! in_array($user->role, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
                'errors' => [
                    'role' => ['You do not have permission to access this resource.'],
                ],
            ], 403);
        }

        return $next($request);
    }
}
