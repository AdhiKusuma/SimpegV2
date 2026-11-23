<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune
        {--days= : Usia minimum log yang boleh dihapus}
        {--batch=1000 : Jumlah baris maksimum per batch penghapusan}
        {--execute : Jalankan penghapusan, default hanya dry-run}';

    protected $description = 'Membersihkan audit log yang sudah melewati masa simpan.';

    public function handle(): int
    {
        if (! Schema::hasTable('audit_logs')) {
            $this->error('Tabel audit_logs belum tersedia.');

            return self::FAILURE;
        }

        $daysOption = $this->option('days');
        $days = max(
            (int) ($daysOption !== null && $daysOption !== '' ? $daysOption : config('audit.retention_days', 180)),
            0,
        );
        $batch = max((int) $this->option('batch'), 1);
        $cutoff = now()->subDays($days);
        $candidates = AuditLog::query()->where('created_at', '<', $cutoff)->count();

        $this->info(sprintf(
            '%s audit log lebih lama dari %s hari.',
            number_format($candidates, 0, ',', '.'),
            number_format($days, 0, ',', '.'),
        ));

        if (! $this->option('execute')) {
            $this->comment('Dry-run: tidak ada data yang dihapus. Tambahkan --execute untuk menjalankan.');

            return self::SUCCESS;
        }

        $deleted = 0;
        do {
            $ids = AuditLog::query()
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($batch)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += AuditLog::query()->whereIn('id', $ids)->delete();
        } while (true);

        $this->info(number_format($deleted, 0, ',', '.').' audit log berhasil dibersihkan.');

        return self::SUCCESS;
    }
}
