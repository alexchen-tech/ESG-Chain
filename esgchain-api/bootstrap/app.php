<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API 純 JWT，無 web session；未認證時不重導向 login 路由
        $middleware->redirectGuestsTo(fn ($request) => null);
        $middleware->alias([
            'erp.hmac'      => \App\Http\Middleware\VerifyErpHmacSignature::class,
            'export.apikey' => \App\Http\Middleware\VerifyExportApiKey::class,
            'supplier.scope' => \App\Http\Middleware\EnsureSupplierPortalScope::class,
            'role.admin'      => \App\Http\Middleware\EnsureAdminRole::class,
            'role.any'        => \App\Http\Middleware\EnsureAnyRole::class,
            'permission'      => \App\Http\Middleware\EnsurePermission::class,
            'internal.token'  => \App\Http\Middleware\VerifyInternalServiceToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API 路由的未認證請求一律回傳 401 JSON，不嘗試重導向 web login 路由
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }
        });
    })->create();
