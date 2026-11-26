<?php

namespace App\Console\Commands;

use App\Services\PayrollGradeSyncService;
use Illuminate\Console\Command;

class SyncPayrollGrades extends Command
{
    protected $signature = 'payroll:sync-grades
        {file : Path file XLSX yang berisi kolom ID Peg, Gol, MK, dan Unit Kerja}
        {--effective-from= : Tanggal mulai berlaku, format YYYY-MM-DD}
        {--dry-run : Hanya validasi dan tampilkan rencana, tanpa menyimpan}';

    protected $description = 'Menyelaraskan master dan riwayat golongan payroll berdasarkan ID Pegawai';

    public function handle(PayrollGradeSyncService $sync): int
    {
        try {
            $result = $sync->sync(
                (string) $this->argument('file'),
                (bool) $this->option('dry-run'),
                $this->option('effective-from') ?: null,
            );
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%s: %d baris ID, %d pegawai cocok, %d assignment baru, %d sudah sama.',
            $result['dry_run'] ? 'Dry-run selesai' : 'Sinkronisasi selesai',
            $result['source_rows'],
            $result['matched_employees'],
            $result['assignments_to_create'],
            $result['assignments_unchanged'],
        ));
        $this->line('Kode golongan: '.($result['grade_codes'] === [] ? '-' : implode(', ', $result['grade_codes'])));

        if ($result['missing_employees'] > 0) {
            $this->warn(sprintf(
                '%d ID tidak ditemukan di tb_pegawai dan dilewati: %s',
                $result['missing_employees'],
                implode(', ', $result['missing_employee_ids']),
            ));
        }

        if ($result['rows_defaulted_to_zero'] > 0) {
            $this->comment($result['rows_defaulted_to_zero'].' baris tanpa golongan dinormalisasi menjadi kode 0.');
        }

        if ($result['dry_run']) {
            $this->comment('Tidak ada data yang diubah karena mode dry-run.');
        }

        return self::SUCCESS;
    }
}
