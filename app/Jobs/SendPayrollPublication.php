<?php

namespace App\Jobs;

use App\Models\PayrollPublication;
use App\Services\PayrollPublicationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPayrollPublication implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $publicationId) {}

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(PayrollPublicationService $publications): void
    {
        $publication = PayrollPublication::query()->find($this->publicationId);
        if ($publication !== null) {
            $publications->send($publication);
        }
    }
}
