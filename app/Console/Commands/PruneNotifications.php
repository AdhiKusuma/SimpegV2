<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune
        {--days=90 : Usia minimum notifikasi yang sudah dibaca untuk dihapus}
        {--batch=500 : Jumlah baris maksimum per batch penghapusan}
        {--execute : Jalankan penghapusan, default hanya dry-run}';

    protected $description = 'Membersihkan notifikasi yang sudah dibaca dan sudah kedaluwarsa.';

    public function handle(NotificationService $notifications): int
    {
        if (! Schema::hasTable('tb_notifikasi')) {
            $this->error('Tabel tb_notifikasi belum tersedia.');

            return self::FAILURE;
        }

        $days = max((int) $this->option('days'), 1);
        $batch = max((int) $this->option('batch'), 1);
        $cutoff = now()->subDays($days);
        $candidates = DB::table('tb_notifikasi')
            ->where('status_baca', 'read')
            ->where('waktu_notif', '<', $cutoff)
            ->count();

        $this->info(sprintf(
            '%s notifikasi sudah dibaca dan lebih lama dari %s hari.',
            number_format($candidates, 0, ',', '.'),
            number_format($days, 0, ',', '.'),
        ));

        if (! $this->option('execute')) {
            $this->comment('Dry-run: tidak ada data yang dihapus. Tambahkan --execute untuk menjalankan.');

            return self::SUCCESS;
        }

        $deleted = $notifications->pruneRead($days, $batch);
        $this->info(number_format($deleted, 0, ',', '.').' notifikasi berhasil dibersihkan.');

        return self::SUCCESS;
    }
}
