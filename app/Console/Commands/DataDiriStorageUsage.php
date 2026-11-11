<?php

namespace App\Console\Commands;

use App\Models\DataDiriDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DataDiriStorageUsage extends Command
{
    protected $signature = 'data-diri:storage-usage';

    protected $description = 'Menampilkan total ukuran storage dokumen data diri.';

    public function handle(): int
    {
        $disk = Storage::disk((string) config('filesystems.default', 'local'));
        $paths = $disk->allFiles('data-diri');
        $total = 0;
        $byType = [];

        foreach ($paths as $path) {
            $size = (int) $disk->size($path);
            $total += $size;
            $parts = explode('/', $path);
            $type = $parts[2] ?? 'lainnya';
            $byType[$type] = ($byType[$type] ?? 0) + $size;
        }

        $this->line('Disk: '.config('filesystems.default', 'local'));
        $this->line('File aktif: '.number_format(count($paths), 0, ',', '.'));
        $this->line('Metadata aktif: '.number_format(DataDiriDocument::query()->count(), 0, ',', '.'));
        $this->line('Total ukuran: '.$this->humanSize($total));

        if ($byType !== []) {
            $this->table(
                ['Jenis', 'Ukuran'],
                collect($byType)->sortKeys()->map(fn (int $size, string $type): array => [$type, $this->humanSize($size)])->values()->all(),
            );
        }

        return self::SUCCESS;
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $size = (float) $bytes;
        foreach ($units as $unit) {
            $size /= 1024;
            if ($size < 1024 || $unit === 'TB') {
                return number_format($size, 2, ',', '.').' '.$unit;
            }
        }

        return number_format($size, 2, ',', '.').' TB';
    }
}
