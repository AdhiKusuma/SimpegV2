<?php

namespace App\Http\Middleware;

use App\Models\Legacy\TbConfig;
use App\Services\ApplicationLogoService;
use App\Services\NotificationService;
use App\Services\RbacService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $permissions = app(RbacService::class)->permissionsFor($request);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => [
                    ...$request->session()->only([
                        'id_user',
                        'nama_user',
                        'hak_akses',
                        'id_pegawai',
                        'kode_kantor',
                    ]),
                    'permissions' => $permissions,
                ],
            ],
            'appConfig' => fn (): array => $this->applicationConfig(),
            'notificationSummary' => fn (): array => app(NotificationService::class)->summary(
                (string) $request->session()->get('id_user', ''),
            ),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'welcome' => fn () => $request->session()->get('welcome'),
                'session_expired' => fn () => $request->session()->get('session_expired'),
                'login_rate_limit' => function () use ($request): ?array {
                    $loginRateLimit = $request->session()->get('login_rate_limit');

                    if (
                        ! is_array($loginRateLimit)
                        || (int) ($loginRateLimit['retry_at'] ?? 0) <= now()->timestamp
                        || (string) ($loginRateLimit['ip'] ?? '') !== (string) $request->ip()
                    ) {
                        $request->session()->forget('login_rate_limit');

                        return null;
                    }

                    unset($loginRateLimit['ip']);

                    return $loginRateLimit;
                },
            ],
        ];
    }

    private function applicationConfig(): array
    {
        $config = TbConfig::query()->first();

        return [
            'name' => $config?->nama_app ?: config('app.name', 'SIMPEG'),
            'alias' => $config?->alias_app ?: 'SIMPEG',
            'description' => $config?->desc_app ?: 'Kepegawaian',
            'logoUrl' => app(ApplicationLogoService::class)->url($config?->logo),
        ];
    }
}
