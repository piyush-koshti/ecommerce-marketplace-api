<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * Usage: permission:products.view
     * Usage: permission:products.view,products.update
     *
     * When multiple permissions are supplied, the user needs all.
     */
    public function handle(
        Request $request,
        Closure $next,
        string ...$permissions
    ): Response {
        $user = $request->user();

        if (! $user) {
            throw new AuthenticationException;
        }

        foreach ($permissions as $permission) {
            if (! $user->hasPermissionTo($permission)) {
                throw new AuthorizationException(
                    'You do not have the required permission.'
                );
            }
        }

        return $next($request);
    }
}
