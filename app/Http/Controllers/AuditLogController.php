<?php

namespace App\Http\Controllers;

use App\Jobs\PruneAuditLogs;
use App\Models\AuditLog;
use App\Services\RbacService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureAccess($request);
        abort_unless(Schema::hasTable('audit_logs'), 503, 'Audit log belum tersedia. Jalankan migrasi database terlebih dahulu.');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'module' => ['nullable', 'string', 'max:80'],
            'action' => ['nullable', 'string', 'max:80'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $module = trim((string) ($filters['module'] ?? ''));
        $action = trim((string) ($filters['action'] ?? ''));
        $fromDate = (string) ($filters['from_date'] ?? '');
        $toDate = (string) ($filters['to_date'] ?? '');

        $logs = AuditLog::query()
            ->where('action', '!=', 'View')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = "%{$search}%";
                $query->where(function (Builder $scope) use ($like): void {
                    $scope->where('actor_id', 'like', $like)
                        ->orWhere('actor_name', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('entity_type', 'like', $like)
                        ->orWhere('entity_id', 'like', $like)
                        ->orWhere('ip_address', 'like', $like);
                });
            })
            ->when($module !== '', fn (Builder $query) => $query->where('module', $module))
            ->when($action !== '', fn (Builder $query) => $query->where('action', $action))
            ->when($fromDate !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $fromDate))
            ->when($toDate !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $toDate))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 25), 10), 100))
            ->withQueryString()
            ->through(fn (AuditLog $log): array => [
                'id' => $log->id,
                'actor_id' => $log->actor_id,
                'actor_name' => $log->actor_name ?: 'Sistem',
                'actor_role' => $log->actor_role,
                'module' => $log->module,
                'action' => $log->action,
                'description' => $log->description,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'route_name' => $log->route_name,
                'method' => $log->method,
                'ip_address' => $log->ip_address,
                'created_at' => $this->formatAuditTimestamp($log),
            ]);

        return Inertia::render('Audit/Index', [
            'logs' => $logs,
            'filters' => [
                'search' => $search,
                'module' => $module,
                'action' => $action,
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            'modules' => AuditLog::query()->whereNotNull('module')->distinct()->orderBy('module')->pluck('module')->values()->all(),
            'actions' => AuditLog::query()->whereNotNull('action')->where('action', '!=', 'View')->distinct()->orderBy('action')->pluck('action')->values()->all(),
        ]);
    }

    private function formatAuditTimestamp(AuditLog $log): ?string
    {
        $rawTimestamp = $log->getRawOriginal('created_at');
        if ($rawTimestamp === null || trim((string) $rawTimestamp) === '') {
            return null;
        }

        return Carbon::parse((string) $rawTimestamp, 'UTC')
            ->setTimezone('Asia/Jakarta')
            ->format('Y-m-d H:i:s');
    }

    public function cleanupPreview(Request $request): JsonResponse
    {
        $this->ensureCleanupAccess($request);
        abort_unless(Schema::hasTable('audit_logs'), 503, 'Audit log belum tersedia. Jalankan migrasi database terlebih dahulu.');

        $days = $this->validatedCleanupDays($request);

        return response()->json([
            'count' => $this->cleanupQuery($days)->count(),
            'older_than_days' => $days,
            'protected_days' => $days,
        ]);
    }

    public function cleanup(Request $request): RedirectResponse
    {
        $this->ensureCleanupAccess($request);
        abort_unless(Schema::hasTable('audit_logs'), 503, 'Audit log belum tersedia. Jalankan migrasi database terlebih dahulu.');

        $days = $this->validatedCleanupDays($request);
        $count = $this->cleanupQuery($days)->count();
        if ($count === 0) {
            return back()->with('error', 'Tidak ada audit log yang melewati masa simpan tersebut.');
        }

        PruneAuditLogs::dispatch($days, [
            'id' => $request->session()->get('id_user'),
            'name' => $request->session()->get('nama_user'),
            'role' => $request->session()->get('hak_akses'),
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $message = $days === 0
            ? 'Pembersihan semua audit log lama dijadwalkan.'
            : sprintf(
                'Pembersihan %s audit log dijadwalkan. Log %s hari terakhir tetap dipertahankan.',
                number_format($count, 0, ',', '.'),
                number_format($days, 0, ',', '.'),
            );

        return back()->with('success', $message);
    }

    private function ensureAccess(Request $request): void
    {
        abort_unless(app(RbacService::class)->can($request, 'audit.view'), 403);
    }

    private function ensureCleanupAccess(Request $request): void
    {
        abort_unless(app(RbacService::class)->can($request, 'audit.cleanup'), 403);
    }

    private function validatedCleanupDays(Request $request): int
    {
        return (int) $request->validate([
            'older_than_days' => ['required', 'integer', 'min:0', 'max:3650'],
        ])['older_than_days'];
    }

    private function cleanupQuery(int $days): Builder
    {
        return AuditLog::query()->where('created_at', '<', now()->subDays(max($days, 0)));
    }
}
