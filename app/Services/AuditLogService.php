<?php

namespace App\Services;

use App\Jobs\WriteAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuditLogService
{
    private static ?bool $available = null;

    public function __construct(private readonly IpLocationService $ipLocations) {}

    private const RECORDABLE_ACTIONS = [
        'Create',
        'Update',
        'Delete',
        'Import',
        'Export',
        'Process',
    ];

    private const SKIPPED_ROUTES = [
        'audit.index',
        'audit.cleanup.preview',
        'audit.cleanup',
        'session.activity',
        'session.location',
        'config.application.logo',
        'employees.photo',
        'files.show',
        'files.download',
        'files.data-diri.preview',
        'files.data-diri.download',
    ];

    public function available(): bool
    {
        return self::$available ??= Schema::hasTable('audit_logs');
    }

    public function record(Request $request, array $data): void
    {
        if (! config('audit.enabled', true) || ! $this->available()) {
            return;
        }

        $session = $request->session();
        $metadata = $data['metadata'] ?? null;
        if ($metadata !== null && ! is_array($metadata)) {
            $metadata = ['value' => $metadata];
        }

        $location = $data['location'] ?? $this->locationForRequest($request);

        try {
            WriteAuditLog::dispatch([
                'actor_id' => $data['actor_id'] ?? $session->get('id_user'),
                'actor_name' => $data['actor_name'] ?? $session->get('nama_user'),
                'actor_role' => $data['actor_role'] ?? $session->get('hak_akses'),
                'module' => Str::limit(trim((string) ($data['module'] ?? 'system')), 80, ''),
                'action' => Str::limit(trim((string) ($data['action'] ?? 'Activity')), 80, ''),
                'description' => trim((string) ($data['description'] ?? 'Aktivitas sistem.')),
                'entity_type' => isset($data['entity_type']) ? Str::limit(trim((string) $data['entity_type']), 80, '') : null,
                'entity_id' => isset($data['entity_id']) ? Str::limit(trim((string) $data['entity_id']), 160, '') : null,
                'route_name' => $data['route_name'] ?? $request->route()?->getName(),
                'method' => strtoupper((string) ($data['method'] ?? $request->method())),
                'url' => isset($data['url']) ? Str::limit((string) $data['url'], 2048, '') : Str::limit($request->fullUrl(), 2048, ''),
                'ip_address' => $data['ip_address'] ?? $request->ip(),
                'location_source' => $location['source'] ?? null,
                'location_label' => isset($location['label']) ? Str::limit((string) $location['label'], 255, '') : null,
                'location_latitude' => $location['latitude'] ?? null,
                'location_longitude' => $location['longitude'] ?? null,
                'location_accuracy_meters' => $location['accuracy_meters'] ?? null,
                'user_agent' => isset($data['user_agent']) ? Str::limit((string) $data['user_agent'], 1000, '') : Str::limit((string) $request->userAgent(), 1000, ''),
                'metadata' => $metadata,
                'created_at' => $data['created_at'] ?? now(),
            ]);
        } catch (Throwable $exception) {
            // Audit logging must never make the business request fail.
            report($exception);
        }
    }

    private function locationForRequest(Request $request): array
    {
        $browserLocation = $request->session()->get('audit_location');
        if (is_array($browserLocation)
            && is_numeric($browserLocation['latitude'] ?? null)
            && is_numeric($browserLocation['longitude'] ?? null)) {
            return $browserLocation;
        }

        return $this->ipLocations->resolve($request->ip()) ?? [];
    }

    public function recordRequest(Request $request, Response $response): void
    {
        $routeName = $request->route()?->getName();
        if (! $this->shouldRecordRequest($request, $routeName)) {
            return;
        }

        $module = $this->moduleForRoute($routeName);
        $action = $this->actionForRoute($request->method(), $routeName);
        $parameters = $request->route()?->parameters() ?? [];
        $entityId = collect($parameters)->first(fn ($value): bool => is_scalar($value) && trim((string) $value) !== '');

        $this->record($request, [
            'module' => $module,
            'action' => $action,
            'description' => $this->descriptionForRoute($request->method(), $module, $action),
            'entity_type' => $entityId !== null ? Str::singular($module) : null,
            'entity_id' => $entityId,
            'route_name' => $routeName,
            'metadata' => [
                'status' => $response->getStatusCode(),
                'inertia' => $request->headers->has('X-Inertia'),
            ],
        ]);
    }

    private function shouldRecordRequest(Request $request, ?string $routeName): bool
    {
        if (! $request->session()->has('id_user')
            || $routeName === null
            || in_array($routeName, self::SKIPPED_ROUTES, true)
            || in_array($request->method(), ['HEAD', 'OPTIONS'], true)) {
            return false;
        }

        // Login/logout are recorded explicitly so actor data is retained across
        // session regeneration and session flush. Only business actions are
        // recorded here; ordinary page views and polling stay out of the log.
        if (in_array($routeName, ['login.store', 'logout'], true)) {
            return false;
        }

        if (! $this->available()) {
            return false;
        }

        return in_array(
            $this->actionForRoute($request->method(), $routeName),
            self::RECORDABLE_ACTIONS,
            true,
        );
    }

    private function moduleForRoute(?string $routeName): string
    {
        $prefix = strtolower((string) Str::before((string) $routeName, '.'));

        return [
            'dashboard' => 'dashboard',
            'employees' => 'pegawai',
            'pendidikan' => 'pendidikan',
            'pangkat' => 'pangkat',
            'golongan' => 'golongan',
            'eselon' => 'eselon',
            'jabatan' => 'jabatan',
            'pengangkatan' => 'pengangkatan',
            'mutasi' => 'mutasi',
            'penghargaan' => 'penghargaan',
            'penugasan' => 'penugasan',
            'seminar' => 'seminar',
            'cuti' => 'cuti',
            'bahasa' => 'bahasa',
            'diklat' => 'diklat',
            'biaya-pendidikan' => 'biaya pendidikan',
            'sertifikasi' => 'sertifikasi',
            'pelanggaran' => 'pelanggaran',
            'reports' => 'laporan',
            'payroll' => 'payroll',
            'files' => 'files',
            'users' => 'pengguna',
            'config' => 'pengaturan',
            'offices' => 'kantor',
            'authorizations' => 'otorisasi',
            'notifications' => 'notifikasi',
            'profile' => 'profil',
            'audit' => 'audit log',
        ][$prefix] ?? ($prefix !== '' ? $prefix : 'system');
    }

    private function actionForRoute(string $method, ?string $routeName): string
    {
        $route = strtolower((string) $routeName);
        $last = Str::lower((string) Str::afterLast($route, '.'));

        if (in_array($last, ['update', 'update-post', 'decision', 'process', 'schedule', 'send', 'cancel'], true)) {
            return in_array($last, ['decision', 'process', 'schedule', 'send', 'cancel'], true) ? 'Process' : 'Update';
        }
        if ($last === 'destroy' || strtoupper($method) === 'DELETE') {
            return 'Delete';
        }
        if (str_contains($route, 'import')) {
            return 'Import';
        }
        if (str_contains($route, 'export') || in_array($last, ['download', 'template'], true)) {
            return 'Export';
        }
        if (in_array($last, ['store', 'create'], true) && strtoupper($method) !== 'GET') {
            return 'Create';
        }
        if (in_array($last, ['show', 'edit', 'create', 'index'], true)) {
            return 'View';
        }

        return match (strtoupper($method)) {
            'POST' => 'Create',
            'PUT', 'PATCH' => 'Update',
            default => 'View',
        };
    }

    private function descriptionForRoute(string $method, string $module, string $action): string
    {
        return match ($action) {
            'Create' => "Data baru ditambahkan pada modul {$module}.",
            'Update' => "Data pada modul {$module} diperbarui.",
            'Delete' => "Data pada modul {$module} dihapus atau dipindahkan.",
            'Import' => "Data diimpor ke modul {$module}.",
            'Export' => "Data dari modul {$module} diekspor.",
            'Process' => "Proses pada modul {$module} dijalankan.",
            default => "Pengguna membuka modul {$module}.",
        };
    }
}
