<?php

namespace App\Jobs;

use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Schema;

class WriteAuditLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly array $attributes)
    {
        $this->onConnection((string) config('audit.queue_connection', 'database'));
        $this->onQueue((string) config('audit.queue', 'audit'));
    }

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::query()->create($this->attributes);
    }
}
