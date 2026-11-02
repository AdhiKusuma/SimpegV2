<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $mysql = config('database.connections.mysql');

        config(['database.connections' => $mysql === null ? [] : ['mysql' => $mysql]]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)
                ->by((string) $request->ip())
                ->response(function (Request $request, array $headers) {
                    $retryAfter = max(1, (int) ($headers['Retry-After'] ?? 60));
                    $request->session()->put('login_rate_limit', [
                        'retry_at' => now()->addSeconds($retryAfter)->timestamp,
                        'ip' => (string) $request->ip(),
                    ]);
                    $response = redirect()->route('login', [], 303);

                    foreach ($headers as $name => $value) {
                        $response->headers->set($name, (string) $value);
                    }

                    return $response;
                });
        });
    }
}
