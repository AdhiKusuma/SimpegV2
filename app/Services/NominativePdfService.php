<?php

namespace App\Services;

use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Low-memory renderer for the all-employee nominative report.
 */
class NominativePdfService
{
    private const PAGE_WIDTH = 841.89;

    private const PAGE_HEIGHT = 595.28;

    private const MARGIN_X = 28.0;

    private const CONTENT_WIDTH = 785.0;

    private const FONT = 'DejaVu Sans';

    private const COLUMNS = [
        ['label' => 'No', 'width' => 25.0, 'align' => 'center'],
        ['label' => 'Nama Pegawai', 'width' => 145.0],
        ['label' => 'ID Pegawai', 'width' => 60.0],
        ['label' => 'Jabatan', 'width' => 125.0],
        ['label' => 'TMT Jabatan', 'width' => 70.0],
        ['label' => 'Unit Kerja', 'width' => 110.0],
        ['label' => 'Status Kepegawaian', 'width' => 90.0],
        ['label' => 'Jenjang', 'width' => 60.0],
        ['label' => 'Sekolah / Universitas', 'width' => 100.0],
    ];

    public function download(iterable $employees, array $filters, string $filename): StreamedResponse
    {
        $dompdf = new Dompdf;
        $canvas = new CPDF('a4', 'landscape', $dompdf);
        $total = 0;

        $this->drawHeader($canvas, $filters);
        $y = 86.0;
        $rowHeight = 25.0;
        $this->drawTableHeader($canvas, $y);
        $y += 22.0;

        foreach ($employees as $employee) {
            if ($y + $rowHeight > self::PAGE_HEIGHT - 32) {
                $this->drawFooter($canvas);
                $canvas->new_page();
                $this->drawHeader($canvas, $filters);
                $y = 86.0;
                $this->drawTableHeader($canvas, $y);
                $y += 22.0;
            }

            $total++;
            $values = [
                (string) $total,
                $employee->nama ?? '',
                $employee->id_peg ?? '',
                $employee->jabatan ?? '',
                $employee->tmt_jabatan ? date('d/m/Y', strtotime((string) $employee->tmt_jabatan)) : '',
                $employee->nama_kantor ?? '',
                $employee->status_kepeg ?? '',
                $employee->jenjang ?? '',
                $employee->nama_sekolah ?? '',
            ];
            $this->drawRow($canvas, $y, $values, $rowHeight);
            $y += $rowHeight;
        }

        if ($total === 0) {
            $this->drawRow($canvas, $y, ['', 'Data nominatif tidak ditemukan.'], $rowHeight, true);
        }

        $this->drawFooter($canvas);
        $content = $canvas->output();

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function drawHeader(CPDF $canvas, array $filters): void
    {
        $canvas->text(self::MARGIN_X, 28, 'Laporan Nominatif Pegawai', self::FONT, 14, [0.09, 0.13, 0.20]);
        $summary = sprintf(
            'Kantor: %s · Jabatan: %s · Status: %s%s',
            $filters['kantor'] ?: 'Semua kantor',
            $filters['jabatan'] ?: 'Semua jabatan',
            $filters['status_kepeg'] ?: 'Semua status',
            $filters['search'] ? ' · Pencarian: '.$filters['search'] : '',
        );
        $canvas->text(self::MARGIN_X, 45, $this->fitText($summary, 145), self::FONT, 7.2, [0.32, 0.38, 0.46]);
        $canvas->line(self::MARGIN_X, 60, self::PAGE_WIDTH - self::MARGIN_X, 60, [0.78, 0.82, 0.87], 0.6);
    }

    private function drawTableHeader(CPDF $canvas, float $y): void
    {
        $x = self::MARGIN_X;
        foreach (self::COLUMNS as $column) {
            $canvas->filled_rectangle($x, $y, $column['width'], 22.0, [0.12, 0.23, 0.44]);
            $canvas->rectangle($x, $y, $column['width'], 22.0, [0.70, 0.75, 0.82], 0.4);
            $this->drawCellText($canvas, $x, $y, $column['width'], $column['label'], 22.0, $column['align'] ?? 'left', true);
            $x += $column['width'];
        }
    }

    private function drawRow(CPDF $canvas, float $y, array $values, float $height, bool $empty = false): void
    {
        $x = self::MARGIN_X;
        foreach (self::COLUMNS as $index => $column) {
            $canvas->rectangle($x, $y, $column['width'], $height, [0.78, 0.82, 0.87], 0.4);
            $value = $values[$index] ?? '';
            $this->drawCellText($canvas, $x, $y, $column['width'], $value, $height, $column['align'] ?? 'left', false, $empty && $index === 1);
            $x += $column['width'];
        }
    }

    private function drawCellText(CPDF $canvas, float $x, float $y, float $width, mixed $value, float $height, string $align = 'left', bool $header = false, bool $empty = false): void
    {
        $text = $this->fitText($this->displayValue($value), max(6, (int) floor(($width - 8) / (($header ? 7.0 : 7.2) * 0.5))));
        $size = $header ? 6.2 : 6.6;
        $textWidth = strlen($text) * $size * 0.5;
        $textX = match ($align) {
            'center' => $x + max(4, ($width - $textWidth) / 2),
            'right' => $x + $width - $textWidth - 4,
            default => $x + 4,
        };
        $canvas->text($textX, $y + ($height / 2) + 2.5, $text, self::FONT, $size, $header ? [1.0, 1.0, 1.0] : [0.09, 0.13, 0.20]);
    }

    private function drawFooter(CPDF $canvas): void
    {
        $canvas->page_text(self::PAGE_WIDTH - self::MARGIN_X - 55, self::PAGE_HEIGHT - 18, 'Halaman {PAGE_NUM} / {PAGE_COUNT}', self::FONT, 7, [0.38, 0.43, 0.50]);
    }

    private function displayValue(mixed $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '—' : preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    }

    private function fitText(string $value, int $maxCharacters): string
    {
        if (mb_strlen($value) <= $maxCharacters) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, max(1, $maxCharacters - 1))).'…';
    }
}
