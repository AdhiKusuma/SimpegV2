<?php

use App\Models\PayrollImport;
use App\Services\PayrollPublicationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('payroll:prune-revisions {--keep=1 : Jumlah revisi nonaktif yang dipertahankan per periode} {--execute : Jalankan penghapusan, default hanya dry-run}', function (): int {
    if (! Schema::hasTable('payroll_imports')) {
        $this->error('Tabel payroll_imports belum tersedia.');

        return Command::FAILURE;
    }

    $keep = max((int) $this->option('keep'), 0);
    $imports = PayrollImport::query()
        ->where('is_current', false)
        ->orderByDesc('tahun')
        ->orderByDesc('bulan')
        ->orderByDesc('id')
        ->get(['id', 'tahun', 'bulan']);
    $candidates = [];

    foreach ($imports->groupBy(fn (PayrollImport $import): string => sprintf('%04d-%02d', $import->tahun, $import->bulan)) as $periodImports) {
        foreach ($periodImports->slice($keep) as $import) {
            $candidates[] = (int) $import->id;
        }
    }

    $protected = [];
    if ($candidates !== [] && Schema::hasTable('payroll_salary_masters')) {
        $protected = DB::table('payroll_salary_masters')
            ->whereIn('source_import_id', $candidates)
            ->whereNotNull('source_import_id')
            ->pluck('source_import_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $candidates = array_values(array_diff($candidates, $protected));
    }

    $this->info(sprintf(
        '%s revisi nonaktif dapat dibersihkan; %s revisi dilindungi karena masih menjadi sumber Master Gaji.',
        number_format(count($candidates), 0, ',', '.'),
        number_format(count($protected), 0, ',', '.'),
    ));

    if (! $this->option('execute')) {
        $this->comment('Dry-run: tidak ada data yang dihapus. Tambahkan --execute untuk menjalankan.');

        return Command::SUCCESS;
    }

    if ($candidates !== []) {
        DB::transaction(function () use ($candidates): void {
            PayrollImport::query()->whereIn('id', $candidates)->delete();
        });
    }

    $this->info('Pembersihan revisi payroll selesai. Batch aktif tidak dihapus.');

    return Command::SUCCESS;
})->purpose('Membersihkan revisi payroll nonaktif tanpa menghapus batch aktif');

Artisan::command('payroll:dispatch-publications {--limit=100 : Maksimal publikasi yang dikirim ke queue}', function (): int {
    $count = app(PayrollPublicationService::class)->dispatchDue(max((int) $this->option('limit'), 1));
    $this->info("{$count} publikasi payroll dikirim ke queue.");

    return Command::SUCCESS;
})->purpose('Mengirim publikasi slip gaji yang sudah jatuh tempo ke queue');
