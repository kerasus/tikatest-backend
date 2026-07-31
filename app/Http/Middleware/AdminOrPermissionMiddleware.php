<?php

namespace App\Http\Middleware;

use App\Enums\UserRoleType;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;

class AdminOrPermissionMiddleware extends PermissionMiddleware
{
    public function handle(Request $request, Closure $next, $permission, $guard = null)
    {
        if ($request->user() && $request->user()->hasRole(UserRoleType::Admin->value)) {
            return $next($request);
        }

        return parent::handle($request, $next, $permission, $guard);
    }
}
