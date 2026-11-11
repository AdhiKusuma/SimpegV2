<?php

namespace App\Services;

use App\Jobs\SendPayrollPublication;
use App\Models\PayrollImport;
use App\Models\PayrollPublication;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PayrollPublicationService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function createForImport(
        PayrollImport $payrollImport,
        string $mode = 'immediate',
        ?string $scheduledAt = null,
        ?string $actorId = null,
    ): ?PayrollPublication {
        if (! Schema::hasTable('payroll_publications')) {
            // Fallback untuk deployment lama yang belum menjalankan migration.
            $this->notifications->notifyPublishedPayroll($payrollImport);

            return null;
        }
        if ($mode !== 'immediate') {
            $mode = 'scheduled';
            $scheduledAt ??= $this->defaultScheduledAt($payrollImport);
        }
        $mode = $mode === 'immediate' ? 'immediate' : 'scheduled';
        $existing = PayrollPublication::query()->where('payroll_import_id', $payrollImport->id)->first();
        if ($existing?->status === 'sent' && $mode === 'immediate') {
            return $existing->fresh('payrollImport');
        }
        $publication = PayrollPublication::query()->updateOrCreate(
            ['payroll_import_id' => $payrollImport->id],
            [
                'mode' => $mode,
                'status' => $mode === 'scheduled' ? 'scheduled' : 'processing',
                'scheduled_at' => $mode === 'scheduled' ? $scheduledAt : null,
                'sent_at' => null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'deleted_at' => null,
                'deleted_by' => null,
                'deletion_reason' => null,
                'last_error' => null,
            ],
        );

        if ($mode === 'immediate') {
            $this->send($publication);
        }

        return $publication->fresh('payrollImport');
    }

    public function defaultScheduledAt(PayrollImport $payrollImport): string
    {
        $date = DB::table('payroll_entries')
            ->where('payroll_import_id', $payrollImport->id)
            ->whereNotNull('tgl_gajian')
            ->min('tgl_gajian');
        if (! $date) {
            return now()->toDateTimeString();
        }

        return Carbon::createFromFormat('Y-m-d', (string) $date, 'Asia/Jakarta')
            ->setTime(8, 0)
            ->setTimezone(config('app.timezone'))
            ->toDateTimeString();
    }

    public function schedule(PayrollPublication $publication, string $scheduledAt, string $actorId): PayrollPublication
    {
        abort_if($publication->status === 'sent', 409, 'Slip gaji pada batch ini sudah dikirim.');
        abort_if($publication->status === 'deleted', 409, 'Riwayat publikasi slip gaji ini sudah dihapus.');

        $publication->forceFill([
            'mode' => 'scheduled',
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
            'sent_at' => null,
            'updated_by' => $actorId,
            'last_error' => null,
        ])->save();

        return $publication->fresh('payrollImport');
    }

    public function cancel(PayrollPublication $publication, string $actorId): void
    {
        abort_if($publication->status === 'sent', 409, 'Slip gaji pada batch ini sudah dikirim.');
        abort_if($publication->status === 'deleted', 409, 'Riwayat publikasi slip gaji ini sudah dihapus.');
        $publication->forceFill([
            'status' => 'cancelled',
            'updated_by' => $actorId,
        ])->save();
    }

    public function send(PayrollPublication $publication): PayrollPublication
    {
        $publication->loadMissing('payrollImport');
        if (in_array($publication->status, ['sent', 'deleted'], true)) {
            return $publication;
        }

        $publication->forceFill(['status' => 'processing', 'last_error' => null])->save();

        try {
            $recipients = $this->notifications->payrollRecipients($publication->payrollImport);
            $sentCount = $this->notifications->notifyPublishedPayroll($publication->payrollImport);
            $publication->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                'total_recipients' => count($recipients),
                'sent_count' => $sentCount,
                'failed_count' => 0,
                'last_error' => null,
            ])->save();
        } catch (\Throwable $exception) {
            $publication->forceFill([
                'status' => 'failed',
                'failed_count' => 1,
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            throw $exception;
        }

        return $publication->fresh('payrollImport');
    }

    public function delete(PayrollPublication $publication, string $actorId, string $reason): void
    {
        abort_if($publication->status === 'processing', 409, 'Publikasi sedang diproses. Coba hapus kembali setelah proses selesai.');
        abort_if($publication->status === 'deleted', 409, 'Riwayat publikasi slip gaji ini sudah dihapus.');

        DB::transaction(function () use ($publication, $actorId, $reason): void {
            $locked = PayrollPublication::query()
                ->with('payrollImport')
                ->whereKey($publication->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_if($locked->status === 'processing', 409, 'Publikasi sedang diproses. Coba hapus kembali setelah proses selesai.');
            abort_if($locked->status === 'deleted', 409, 'Riwayat publikasi slip gaji ini sudah dihapus.');

            $this->notifications->deletePublishedPayrollNotifications($locked->payrollImport);
            $locked->forceFill([
                'status' => 'deleted',
                'deleted_at' => now(),
                'deleted_by' => $actorId,
                'deletion_reason' => trim($reason),
                'updated_by' => $actorId,
            ])->save();
        });
    }

    /**
     * Convert a legacy active import into an auditable publication record before
     * applying the same deletion rules used by regular publications.
     */
    public function deleteImport(PayrollImport $payrollImport, string $actorId, string $reason): void
    {
        $publication = PayrollPublication::query()->firstOrCreate(
            ['payroll_import_id' => $payrollImport->id],
            [
                'mode' => 'immediate',
                'status' => 'sent',
                'sent_at' => now(),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ],
        );

        $this->delete($publication, $actorId, $reason);
    }

    public function dispatchDue(int $limit = 100): int
    {
        if (! Schema::hasTable('payroll_publications')) {
            return 0;
        }

        $dispatched = 0;
        PayrollPublication::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get()
            ->each(function (PayrollPublication $publication) use (&$dispatched): void {
                $claimed = PayrollPublication::query()
                    ->whereKey($publication->id)
                    ->where('status', 'scheduled')
                    ->update(['status' => 'processing', 'updated_at' => now()]);
                if ($claimed !== 1) {
                    return;
                }

                try {
                    SendPayrollPublication::dispatch($publication->id);
                    $dispatched++;
                } catch (\Throwable $exception) {
                    PayrollPublication::query()
                        ->whereKey($publication->id)
                        ->where('status', 'processing')
                        ->update([
                            'status' => 'scheduled',
                            'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                            'updated_at' => now(),
                        ]);
                    throw $exception;
                }
            });

        return $dispatched;
    }
}
