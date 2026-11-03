<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class XlsxExportService
{
    public function make(array $rows, string $sheetName = 'Data', bool $withBorders = false): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi ZipArchive diperlukan untuk membuat file XLSX.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'simpeg-xlsx-');

        if ($temporaryPath === false) {
            throw new RuntimeException('File sementara XLSX tidak dapat dibuat.');
        }

        try {
            $zip = new ZipArchive;

            if ($zip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Workbook XLSX tidak dapat dibuat.');
            }

            $zip->addFromString('[Content_Types].xml', $this->contentTypes());
            $zip->addFromString('_rels/.rels', $this->rootRelationships());
            $zip->addFromString('xl/workbook.xml', $this->workbook($sheetName));
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
            $zip->addFromString('xl/styles.xml', $this->styles());
            $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheet($rows, $withBorders));
            $zip->close();

            $content = file_get_contents($temporaryPath);

            if ($content === false) {
                throw new RuntimeException('Workbook XLSX tidak dapat dibaca.');
            }

            return $content;
        } finally {
            @unlink($temporaryPath);
        }
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$this->escape($sheetName).'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="0"/>'
            .'<fonts count="2">'
            .'<font><sz val="11"/><name val="Aptos"/></font>'
            .'<font><b/><sz val="11"/><name val="Aptos"/></font>'
            .'</fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FFD1D5DB"/></left><right style="thin"><color rgb="FFD1D5DB"/></right><top style="thin"><color rgb="FFD1D5DB"/></top><bottom style="thin"><color rgb="FFD1D5DB"/></bottom><diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0"/>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1" indent="1"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1" indent="1"/></xf>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private function worksheet(array $rows, bool $withBorders): string
    {
        $columnWidths = $withBorders ? $this->columnWidths($rows) : [];
        $columnCount = max(1, count($columnWidths));
        $lastRow = max(1, count($rows));
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        if ($withBorders) {
            $xml .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
                .'<sheetFormatPr defaultRowHeight="22"/><cols>';

            foreach ($columnWidths as $index => $width) {
                $column = $index + 1;
                $xml .= '<col min="'.$column.'" max="'.$column.'" width="'.number_format($width, 1, '.', '').'" customWidth="1"/>';
            }

            $xml .= '</cols><sheetData>';
        } else {
            $xml .= '<sheetViews><sheetView workbookViewId="0"/></sheetViews><sheetData>';
        }

        foreach (array_values($rows) as $rowIndex => $row) {
            $rowAttributes = '';

            if ($withBorders) {
                $height = $this->rowHeight((array) $row, $columnWidths, $rowIndex === 0);
                $rowAttributes = ' ht="'.$height.'" customHeight="1"';
            }

            $xml .= '<row r="'.($rowIndex + 1).'"'.$rowAttributes.'>';

            foreach (array_values((array) $row) as $columnIndex => $value) {
                $reference = $this->columnName($columnIndex + 1).($rowIndex + 1);
                $styleIndex = $withBorders ? ($rowIndex === 0 ? 3 : 2) : ($rowIndex === 0 ? 1 : null);
                $style = $styleIndex === null ? '' : ' s="'.$styleIndex.'"';

                if (is_int($value) || is_float($value)) {
                    $xml .= '<c r="'.$reference.'"'.$style.'><v>'.(string) $value.'</v></c>';

                    continue;
                }

                $text = $this->escape((string) ($value ?? ''));
                $xml .= '<c r="'.$reference.'" t="inlineStr"'.$style.'><is><t>'.$text.'</t></is></c>';
            }

            $xml .= '</row>';
        }

        $xml .= '</sheetData>';

        if ($withBorders) {
            $xml .= '<autoFilter ref="A1'.$this->columnName($columnCount).$lastRow.'"/>';
        }

        return $xml.'</worksheet>';
    }

    private function columnWidths(array $rows): array
    {
        $columnCount = 0;

        foreach ($rows as $row) {
            $columnCount = max($columnCount, count((array) $row));
        }

        $widths = array_fill(0, $columnCount, 12);

        foreach ($rows as $row) {
            foreach (array_values((array) $row) as $index => $value) {
                $text = (string) ($value ?? '');
                $length = function_exists('mb_strwidth') ? mb_strwidth($text, 'UTF-8') : strlen($text);
                $widths[$index] = max($widths[$index], $length + 3);
            }
        }

        return array_map(fn (int $width): int => min(44, max(14, $width)), $widths);
    }

    private function rowHeight(array $row, array $columnWidths, bool $isHeader): int
    {
        $maxLines = 1;

        foreach (array_values($row) as $index => $value) {
            $width = max(8, ($columnWidths[$index] ?? 14) - 3);
            $text = (string) ($value ?? '');
            $lines = preg_split('/\R/u', $text) ?: [$text];
            $cellLines = 0;

            foreach ($lines as $line) {
                $length = function_exists('mb_strwidth') ? mb_strwidth($line, 'UTF-8') : strlen($line);
                $cellLines += max(1, (int) ceil($length / $width));
            }

            $maxLines = max($maxLines, $cellLines);
        }

        $lineHeight = $isHeader ? 18 : 17;
        $minimumHeight = $isHeader ? 34 : 30;

        return max($minimumHeight, min(136, $maxLines * $lineHeight + 12));
    }

    private function columnName(int $number): string
    {
        $name = '';

        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $name = chr(65 + $remainder).$name;
            $number = intdiv($number - 1, 26);
        }

        return $name;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
