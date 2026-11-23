<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Services\IpLocationService;
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

    public function handle(IpLocationService $ipLocations): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $attributes = $this->attributes;
        if (($attributes['location_source'] ?? null) === null
            && empty($attributes['location_label'])) {
            $location = $ipLocations->resolve($attributes['ip_address'] ?? null);
            if ($location !== null) {
                $attributes['location_source'] = $location['source'] ?? null;
                $attributes['location_label'] = $location['label'] ?? null;
                $attributes['location_latitude'] = $location['latitude'] ?? null;
                $attributes['location_longitude'] = $location['longitude'] ?? null;
                $attributes['location_accuracy_meters'] = $location['accuracy_meters'] ?? null;
            }
        }

        AuditLog::query()->create($attributes);
    }
}
