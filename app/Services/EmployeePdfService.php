<?php

namespace App\Services;

use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Renders the employee export directly on the PDF canvas.
 *
 * The employee list can contain thousands of rows. Rendering the complete
 * HTML table through Dompdf builds a large CSS/layout tree and can exhaust
 * the PHP memory limit. Drawing the same report as a simple paginated table
 * keeps the export deterministic and bounded without changing the report data.
 */
class EmployeePdfService
{
    private const FONT = 'DejaVu Sans';

    private const FONT_BOLD = 'DejaVu Sans';

    private const PAGE_WIDTH = 841.89;

    private const PAGE_HEIGHT = 595.28;

    private const MARGIN_X = 28.0;

    private const CONTENT_WIDTH = 785.0;

    private const COLUMNS = [
        ['label' => 'No', 'width' => 25.0, 'align' => 'center'],
        ['label' => 'ID Pegawai', 'width' => 58.0],
        ['label' => 'Nama Pegawai', 'width' => 115.0],
        ['label' => 'Tempat, Tanggal Lahir', 'width' => 100.0],
        ['label' => 'Jabatan', 'width' => 120.0],
        ['label' => 'Divisi / Unit Kerja', 'width' => 100.0],
        ['label' => 'Kantor / Area', 'width' => 85.0],
        ['label' => 'Status', 'width' => 60.0],
        ['label' => 'Mulai Kerja', 'width' => 65.0],
        ['label' => 'Kontak', 'width' => 57.0],
    ];

    public function download(array $data, string $filename): StreamedResponse
    {
        $dompdf = new Dompdf;
        $canvas = new CPDF('a4', 'landscape', $dompdf);
        $employees = $data['employees'] ?? [];
        $filters = $data['filters'] ?? [];
        $generatedAt = (string) ($data['generatedAt'] ?? now('Asia/Jakarta')->format('d-m-Y H:i'));
        $total = (int) ($data['total'] ?? count($employees));

        $this->drawPageHeader($canvas, $filters, $generatedAt, $total);

        $y = 86.0;
        $rowHeight = 25.0;
        $rowNumber = 0;

        $this->drawTableHeader($canvas, $y);
        $y += 22.0;

        foreach ($employees as $employee) {
            if ($y + $rowHeight > self::PAGE_HEIGHT - 32) {
                $this->drawFooter($canvas);
                $canvas->new_page();
                $this->drawPageHeader($canvas, $filters, $generatedAt, $total);
                $y = 86.0;
                $this->drawTableHeader($canvas, $y);
                $y += 22.0;
            }

            $rowNumber++;
            $this->drawRow($canvas, $y, $rowNumber, $employee, $rowHeight);
            $y += $rowHeight;
        }

        if ($employees === []) {
            $this->drawRow($canvas, $y, null, ['name' => 'Data pegawai tidak ditemukan.'], $rowHeight, true);
            $y += $rowHeight;
        }

        if ($y + $rowHeight > self::PAGE_HEIGHT - 32) {
            $this->drawFooter($canvas);
            $canvas->new_page();
            $this->drawPageHeader($canvas, $filters, $generatedAt, $total);
            $y = 86.0;
            $this->drawTableHeader($canvas, $y);
            $y += 22.0;
        }

        $this->drawTotalRow($canvas, $y, $total, $rowHeight);
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

    private function drawPageHeader(CPDF $canvas, array $filters, string $generatedAt, int $total): void
    {
        $canvas->text(self::MARGIN_X, 28, 'Laporan Data Pegawai', self::FONT_BOLD, 14, [0.09, 0.13, 0.20]);
        $canvas->text(self::MARGIN_X, 45, $this->fitText('Status: '.($filters['tab'] ?? 'Aktif'), 92), self::FONT, 8, [0.32, 0.38, 0.46]);
        $canvas->text(self::MARGIN_X + 105, 45, 'Total: '.number_format($total, 0, ',', '.'), self::FONT, 8, [0.32, 0.38, 0.46]);
        $canvas->text(self::PAGE_WIDTH - 175, 28, 'Dicetak: '.$generatedAt, self::FONT, 8, [0.32, 0.38, 0.46]);

        $canvas->line(self::MARGIN_X, 60, self::PAGE_WIDTH - self::MARGIN_X, 60, [0.78, 0.82, 0.87], 0.6);
    }

    private function drawTableHeader(CPDF $canvas, float $y): void
    {
        $x = self::MARGIN_X;
        $height = 22.0;
        $canvas->filled_rectangle($x, $y, self::CONTENT_WIDTH, $height, [0.12, 0.23, 0.44]);

        foreach (self::COLUMNS as $column) {
            $canvas->rectangle($x, $y, $column['width'], $height, [0.70, 0.75, 0.82], 0.4);
            $this->drawCellText($canvas, $x, $y, $column['width'], $column['label'], $height, $column['align'] ?? 'left', true);
            $x += $column['width'];
        }
    }

    private function drawRow(CPDF $canvas, float $y, ?int $rowNumber, array $employee, float $height, bool $empty = false): void
    {
        $values = $empty
            ? ['', '', (string) ($employee['name'] ?? ''), '', '', '', '', '', '', '']
            : [
                (string) $rowNumber,
                $employee['id'] ?? '',
                $employee['name'] ?? '',
                $employee['ttl'] ?? '',
                $employee['position'] ?? '',
                $employee['division'] ?? '',
                $employee['office'] ?? '',
                $employee['status'] ?? '',
                $employee['start_date'] ?? '',
                $employee['phone'] ?? '',
            ];

        $x = self::MARGIN_X;
        foreach (self::COLUMNS as $index => $column) {
            $canvas->rectangle($x, $y, $column['width'], $height, [0.78, 0.82, 0.87], 0.4);
            $this->drawCellText($canvas, $x, $y, $column['width'], $values[$index], $height, $column['align'] ?? 'left', false, $empty && $index === 2);
            $x += $column['width'];
        }
    }

    private function drawTotalRow(CPDF $canvas, float $y, int $total, float $height): void
    {
        $canvas->filled_rectangle(self::MARGIN_X, $y, self::CONTENT_WIDTH, $height, [0.93, 0.95, 0.97]);
        $canvas->rectangle(self::MARGIN_X, $y, self::CONTENT_WIDTH, $height, [0.70, 0.75, 0.82], 0.4);
        $canvas->text(self::MARGIN_X + 5, $y + 16, 'Total pegawai', self::FONT_BOLD, 8, [0.09, 0.13, 0.20]);
        $canvas->text(self::PAGE_WIDTH - self::MARGIN_X - 38, $y + 16, number_format($total, 0, ',', '.'), self::FONT_BOLD, 8, [0.09, 0.13, 0.20]);
    }

    private function drawFooter(CPDF $canvas): void
    {
        $canvas->page_text(self::PAGE_WIDTH - self::MARGIN_X - 55, self::PAGE_HEIGHT - 18, 'Halaman {PAGE_NUM} / {PAGE_COUNT}', self::FONT, 7, [0.38, 0.43, 0.50]);
    }

    private function drawCellText(CPDF $canvas, float $x, float $y, float $width, mixed $value, float $height, string $align = 'left', bool $header = false, bool $empty = false): void
    {
        $text = $this->fitText($this->displayValue($value), $this->maxCharacters($width, $header ? 7.0 : 7.2));
        $font = $header || $empty ? self::FONT_BOLD : self::FONT;
        $size = $header ? 6.2 : 6.6;
        $color = $header ? [1.0, 1.0, 1.0] : [0.09, 0.13, 0.20];
        $textWidth = max(0, strlen($text) * $size * 0.5);
        $textX = match ($align) {
            'center' => $x + max(4, ($width - $textWidth) / 2),
            'right' => $x + $width - $textWidth - 4,
            default => $x + 4,
        };

        $canvas->text($textX, $y + ($height / 2) + 2.5, $text, $font, $size, $color);
    }

    private function displayValue(mixed $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '—' : preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    }

    private function maxCharacters(float $width, float $fontSize): int
    {
        return max(6, (int) floor(($width - 8) / ($fontSize * 0.5)));
    }

    private function fitText(string $value, int $maxCharacters): string
    {
        if (mb_strlen($value) <= $maxCharacters) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, max(1, $maxCharacters - 1))).'…';
    }
}
