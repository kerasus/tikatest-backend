<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;

class AdminOrPermissionMiddleware extends PermissionMiddleware
{
    public function handle(Request $request, Closure $next, $permission, $guard = null)
    {
        if ($request->user() && $request->user()->hasRole('admin')) {
            return $next($request);
        }

        return parent::handle($request, $next, $permission, $guard);
    }
}
