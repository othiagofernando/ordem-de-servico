<?php

use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\AuthorizeMembershipPermission;
use App\Http\Middleware\CheckRoutePermission;
use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EstablishStructuredLogContext;
use App\Http\Middleware\FlushCurrentCompanyContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);
        $middleware->prepend(FlushCurrentCompanyContext::class);
        $middleware->append(EstablishStructuredLogContext::class);

        $middleware->alias([
            'auth.token' => AuthenticateApiToken::class,
            'permission' => AuthorizeMembershipPermission::class,
            'check-route-permission' => CheckRoutePermission::class,
            'super_admin' => EnsureSuperAdmin::class,
            'subscription.active' => EnsureActiveSubscription::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
