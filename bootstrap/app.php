<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireLegacySession;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Existing SIMPEG middleware
        $middleware->web(append: [HandleInertiaRequests::class]);

        $middleware->alias([
            'legacy.auth' => RequireLegacySession::class,
        ]);

        // Read trusted proxy addresses from TRUSTED_PROXIES per environment.
        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('notifications:prune --execute --days=90 --batch=500')
            ->dailyAt('02:30')
            ->withoutOverlapping(10);
        $schedule->command('payroll:dispatch-publications --limit=100')
            ->everyMinute()
            ->withoutOverlapping(10);
    })
    ->create();

return $app;
