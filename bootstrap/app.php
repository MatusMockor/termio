<?php

use App\Http\Middleware\CheckFeatureAccess;
use App\Http\Middleware\CheckReservationLimit;
use App\Http\Middleware\CheckServiceLimit;
use App\Http\Middleware\CheckUserLimit;
use App\Http\Middleware\EnsureOwnerRole;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\TenantMiddleware;
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
        $middleware->alias([
            'tenant' => TenantMiddleware::class,
            'owner' => EnsureOwnerRole::class,
            'admin' => EnsureUserIsAdmin::class,
            'check.reservation.limit' => CheckReservationLimit::class,
            'check.user.limit' => CheckUserLimit::class,
            'check.service.limit' => CheckServiceLimit::class,
            'feature' => CheckFeatureAccess::class,
        ]);

        $middleware->statefulApi();

        $middleware->validateCsrfTokens(except: [
            'api/auth/*',
            'api/book/*',
            'api/webhooks/stripe',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
