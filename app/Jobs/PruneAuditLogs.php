<?php

namespace App\Jobs;

use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Schema;

class PruneAuditLogs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $olderThanDays,
        public readonly array $actor,
    ) {
        $this->onConnection((string) config('audit.queue_connection', 'database'));
        $this->onQueue((string) config('audit.queue', 'audit'));
    }

    public function handle(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $days = max($this->olderThanDays, 0);
        $cutoff = now()->subDays($days);
        $deleted = 0;
        $batchSize = 1000;

        do {
            $ids = AuditLog::query()
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($batchSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += AuditLog::query()->whereIn('id', $ids)->delete();
        } while (true);

        if ($deleted === 0) {
            return;
        }

        AuditLog::query()->create([
            'actor_id' => $this->actor['id'] ?? null,
            'actor_name' => $this->actor['name'] ?? null,
            'actor_role' => $this->actor['role'] ?? null,
            'module' => 'audit log',
            'action' => 'Delete',
            'description' => sprintf(
                '%s audit log lama dibersihkan secara manual.',
                number_format($deleted, 0, ',', '.'),
            ),
            'entity_type' => 'audit_logs',
            'entity_id' => (string) $deleted,
            'route_name' => 'audit.cleanup',
            'method' => 'POST',
            'url' => $this->actor['url'] ?? null,
            'ip_address' => $this->actor['ip_address'] ?? null,
            'user_agent' => $this->actor['user_agent'] ?? null,
            'metadata' => [
                'manual_cleanup' => true,
                'deleted_count' => $deleted,
                'retention_days' => $days,
                'protected_days' => $days,
            ],
            'created_at' => now(),
        ]);
    }
}
