<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class XlsxImportService
{
    /**
     * Read the first worksheet without persisting the uploaded workbook.
     * The application already uses a small XML reader for XLSX, so imports
     * stay dependency-free and keep the existing storage flow intact.
     */
    public function rows(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw ValidationException::withMessages(['file_excel' => 'Server belum mengaktifkan pembaca XLSX.']);
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file_excel' => 'File XLSX tidak dapat dibuka.']);
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');

        if ($sharedXml !== false) {
            $shared = simplexml_load_string($sharedXml);

            if ($shared instanceof \SimpleXMLElement) {
                $shared->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

                foreach ($shared->xpath('//x:si') ?: [] as $item) {
                    $item->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                    $value = '';

                    foreach ($item->xpath('.//x:t') ?: [] as $text) {
                        $value .= (string) $text;
                    }

                    $sharedStrings[] = $value;
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw ValidationException::withMessages(['file_excel' => 'Sheet pertama XLSX tidak ditemukan.']);
        }

        $sheet = simplexml_load_string($sheetXml);

        if (! $sheet instanceof \SimpleXMLElement) {
            throw ValidationException::withMessages(['file_excel' => 'Isi XLSX tidak dapat dibaca.']);
        }

        $sheet->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];

        foreach ($sheet->xpath('//x:sheetData/x:row') ?: [] as $rowNode) {
            $rowNode->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $row = [];

            foreach ($rowNode->xpath('./x:c') ?: [] as $cell) {
                $cell->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $reference = (string) $cell['r'];
                preg_match('/^([A-Z]+)/', $reference, $matches);
                $column = $this->columnIndex($matches[1] ?? 'A');
                $type = (string) $cell['t'];
                $value = '';

                if ($type === 'inlineStr') {
                    foreach ($cell->xpath('.//x:t') ?: [] as $text) {
                        $value .= (string) $text;
                    }
                } else {
                    $value = (string) ($cell->v ?? '');

                    if ($type === 's' && $value !== '') {
                        $value = $sharedStrings[(int) $value] ?? '';
                    }
                }

                $row[$column] = trim($value);
            }

            if ($row !== []) {
                ksort($row);
                $lastColumn = max(array_keys($row));
                $rows[] = array_map(fn (int $index): string => (string) ($row[$index] ?? ''), range(0, $lastColumn));
            }
        }

        return $rows;
    }

    public function normalizeHeader(mixed $value): string
    {
        $value = trim((string) $value);
        $value = str_replace(['（', '）'], ['(', ')'], $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?: $value;

        return strtoupper(str_replace([' ', '_', '-', '/', '(', ')', ':', '.'], '', $value));
    }

    public function date(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '-') {
            return null;
        }

        if (is_numeric($value) && (int) $value > 1000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'd.m.Y', 'd-m-y', 'd/m/y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);

                if ($date !== false) {
                    return $date->format('Y-m-d');
                }
            } catch (\Throwable) {
                // Try the next supported format.
            }
        }

        return null;
    }

    public function integer(mixed $value): int
    {
        return max((int) preg_replace('/[^0-9-]/', '', (string) $value), 0);
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + ord($letter) - 64;
        }

        return max($index - 1, 0);
    }
}
