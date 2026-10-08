<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use App\Http\Middleware\VerifySchoolExternalKey;
use Spatie\Permission\Middleware\RoleMiddleware;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\AdminOrPermissionMiddleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            $prefix = config('app.api_prefix', 'api');

            Route::middleware('api')
                ->prefix($prefix)
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'admin_or_permission' => AdminOrPermissionMiddleware::class,
            'school.external.key' => VerifySchoolExternalKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            $prefix = config('app.api_prefix', 'api');
            return $prefix ? $request->is("{$prefix}/*") : true;
        });

        $exceptions->render(function (UnauthorizedException $e, Request $request) {
            return response()->json([
                'message' => 'دسترسی لازم برای انجام این عملیات ندارید.',
            ], 403);
        });
    })->create();
