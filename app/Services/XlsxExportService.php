<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class XlsxExportService
{
    /**
     * Keep the existing export contract intact. Import templates use
     * makeTemplate() below so they can add examples and Excel validations.
     */
    public function make(array $rows, string $sheetName = 'Data', bool $withBorders = false): string
    {
        return $this->makeWorkbook([
            [
                'name' => $sheetName,
                'rows' => $rows,
                'withBorders' => $withBorders,
            ],
        ]);
    }

    /**
     * Build an import workbook without changing the first-sheet structure
     * consumed by the existing import parsers.
     *
     * Examples and lookup lists are kept on separate sheets so a user cannot
     * accidentally import the dummy rows. List validations use workbook
     * defined names, which is the Excel-compatible way to reference another
     * worksheet.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $examples
     * @param  array<string, array<int, mixed>|array{title?: string, values: array<int, mixed>}>  $lists
     * @param  array<int, array{column: string, list: string, sqref?: string, prompt?: string}>  $validations
     */
    public function makeTemplate(
        array $headers,
        string $sheetName,
        array $examples = [],
        array $lists = [],
        array $validations = [],
    ): string {
        $headers = array_values($headers);
        $listDefinitions = $this->normaliseListDefinitions($lists);
        $definedNames = [];
        $listRows = [];

        if ($listDefinitions !== []) {
            $listRows[] = array_map(
                static fn (array $definition): string => $definition['title'],
                array_values($listDefinitions),
            );

            $maxValues = max(array_map(
                static fn (array $definition): int => count($definition['values']),
                array_values($listDefinitions),
            ));

            for ($row = 0; $row < $maxValues; $row++) {
                $listRows[] = array_map(
                    static fn (array $definition): mixed => $definition['values'][$row] ?? '',
                    array_values($listDefinitions),
                );
            }

            foreach (array_values($listDefinitions) as $index => $definition) {
                $column = $this->columnName($index + 1);
                $name = $this->definedName($definition['key']);
                $lastRow = max(2, count($definition['values']) + 1);
                $definedNames[$name] = "'Daftar Pilihan'!$".$column.'$2:$'.$column.'$'.$lastRow;
            }
        }

        $templateValidations = [];
        foreach ($validations as $validation) {
            $listKey = (string) ($validation['list'] ?? '');
            if ($listKey === '' || ! isset($listDefinitions[$listKey])) {
                continue;
            }

            $column = (string) ($validation['column'] ?? '');
            if ($column === '') {
                continue;
            }

            $templateValidations[] = [
                'listName' => $this->definedName($listKey),
                'sqref' => (string) ($validation['sqref'] ?? $column.'2:'.$column.'1000'),
                'prompt' => (string) ($validation['prompt'] ?? 'Pilih nilai dari daftar yang tersedia.'),
            ];
        }

        $sheets = [
            [
                'name' => $sheetName,
                'rows' => [$headers],
                'withBorders' => true,
                'validations' => $templateValidations,
            ],
            [
                'name' => 'Contoh Pengisian',
                'rows' => array_merge([$headers], array_map('array_values', $examples)),
                'withBorders' => true,
            ],
        ];

        if ($listRows !== []) {
            $sheets[] = [
                'name' => 'Daftar Pilihan',
                'rows' => $listRows,
                'withBorders' => true,
            ];
        }

        return $this->makeWorkbook($sheets, $definedNames);
    }

    private function makeWorkbook(array $sheets, array $definedNames = []): string
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

            $zip->addFromString('[Content_Types].xml', $this->contentTypes(count($sheets)));
            $zip->addFromString('_rels/.rels', $this->rootRelationships());
            $zip->addFromString('xl/workbook.xml', $this->workbook($sheets, $definedNames));
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships(count($sheets)));
            $zip->addFromString('xl/styles.xml', $this->styles());

            foreach (array_values($sheets) as $index => $sheet) {
                $zip->addFromString(
                    'xl/worksheets/sheet'.($index + 1).'.xml',
                    $this->worksheet(
                        (array) ($sheet['rows'] ?? []),
                        (bool) ($sheet['withBorders'] ?? false),
                        (array) ($sheet['validations'] ?? []),
                    ),
                );
            }

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

    private function contentTypes(int $sheetCount): string
    {
        $overrides = '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        for ($index = 1; $index <= $sheetCount; $index++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$index.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .$overrides
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

    private function workbook(array $sheets, array $definedNames): string
    {
        $sheetXml = '';
        foreach (array_values($sheets) as $index => $sheet) {
            $sheetXml .= '<sheet name="'.$this->escape((string) ($sheet['name'] ?? 'Sheet'.($index + 1))).'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>';
        }

        $definedNameXml = '';
        if ($definedNames !== []) {
            $definedNameXml = '<definedNames>';
            foreach ($definedNames as $name => $formula) {
                $definedNameXml .= '<definedName name="'.$this->escape((string) $name).'">'.$this->escape((string) $formula).'</definedName>';
            }
            $definedNameXml .= '</definedNames>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheetXml.'</sheets>'
            .$definedNameXml
            .'</workbook>';
    }

    private function workbookRelationships(int $sheetCount): string
    {
        $relationships = '';
        for ($index = 1; $index <= $sheetCount; $index++) {
            $relationships .= '<Relationship Id="rId'.$index.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$index.'.xml"/>';
        }
        $relationships .= '<Relationship Id="rId'.($sheetCount + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$relationships
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

    private function worksheet(array $rows, bool $withBorders, array $validations = []): string
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

        if ($validations !== []) {
            $validationsXml = '';
            $validationCount = 0;
            foreach ($validations as $validation) {
                $sqref = trim((string) ($validation['sqref'] ?? ''));
                $listName = trim((string) ($validation['listName'] ?? ''));
                if ($sqref === '' || $listName === '') {
                    continue;
                }
                $validationCount++;
                $validationsXml .= '<dataValidation type="list" errorStyle="stop" allowBlank="1" showInputMessage="1" showErrorMessage="1" sqref="'.$this->escape($sqref).'">'
                    .'<formula1>'.$this->escape('='.$listName).'</formula1>'
                    .'</dataValidation>';
            }
            if ($validationsXml !== '') {
                $xml .= '<dataValidations count="'.$validationCount.'">'.$validationsXml.'</dataValidations>';
            }
        }

        return $xml.'</worksheet>';
    }

    private function normaliseListDefinitions(array $lists): array
    {
        $definitions = [];
        foreach ($lists as $key => $definition) {
            $values = is_array($definition) && array_key_exists('values', $definition)
                ? (array) $definition['values']
                : (array) $definition;
            $values = array_values(array_filter(array_map(
                static fn (mixed $value): string => trim((string) $value),
                $values,
            ), static fn (string $value): bool => $value !== ''));
            $key = (string) $key;
            if ($key === '' || $values === []) {
                continue;
            }

            $definitions[$key] = [
                'key' => $key,
                'title' => is_array($definition) && isset($definition['title']) ? (string) $definition['title'] : $key,
                'values' => $values,
            ];
        }

        return $definitions;
    }

    private function definedName(string $key): string
    {
        $key = preg_replace('/[^A-Za-z0-9_]+/', '_', trim($key)) ?? 'List';

        return 'List_'.trim($key, '_');
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

        return array_map(static fn (int $width): int => min(44, max(14, $width)), $widths);
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
