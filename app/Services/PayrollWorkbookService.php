<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use Throwable;
use ZipArchive;

class PayrollWorkbookService
{
    private const IMPORT_TEMPLATE_PATH = 'resources/templates/payroll/Template_Import_Payroll.xlsx';

    private const TEMPLATE_PATH = 'resources/templates/payroll/Template_Payroll.xlsx';

    private const TUKIN_TEMPLATE_PATH = 'resources/templates/payroll/Template_Slip_Gaji_Tukin.xlsx';

    private const COMPONENTS = [
        ['gaji_pokok', 'D'],
        ['tunj_suami_istri', 'E'],
        ['tunj_anak', 'F'],
        ['tunj_jabatan', 'G'],
        ['tunj_ops', 'H'],
        ['tunj_pajak', 'I'],
        ['tunj_kinerja', 'J'],
        ['tunj_fungsional', 'K'],
        ['tunj_masa_kerja', 'L'],
        ['honor_kontrak', 'M'],
        ['thl', 'N'],
        ['uang_makan_kontrak', 'O'],
        ['bpjs_kesehatan', 'P'],
        ['bpjs_tenaga_kerja', 'Q'],
        ['keris', 'R'],
    ];

    private const WORKSHEET_NAMESPACE = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const RELATIONSHIP_NAMESPACE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const PACKAGE_RELATIONSHIP_NAMESPACE = 'http://schemas.openxmlformats.org/package/2006/relationships';

    public function template(): string
    {
        $temporaryFile = $this->temporaryTemplateCopy(self::IMPORT_TEMPLATE_PATH);
        $archive = new ZipArchive;

        try {
            if ($archive->open($temporaryFile) !== true) {
                throw new RuntimeException('Workbook payroll tidak dapat dibuka untuk mengunduh template.');
            }

            $retainedParts = $this->retainOnlyWorksheet($archive, 'Template Import', true);
            $this->addPayrollImportExampleWorksheet($archive, $retainedParts);
            if (! $archive->close()) {
                throw new RuntimeException('Template import payroll tidak dapat disimpan.');
            }

            return $temporaryFile;
        } catch (Throwable $exception) {
            $archive->close();
            @unlink($temporaryFile);

            throw $exception;
        }
    }

    public function export(array $entries): string
    {
        $temporaryFile = $this->temporaryTemplateCopy(self::TEMPLATE_PATH);
        $archive = new ZipArchive;

        try {
            if ($archive->open($temporaryFile) !== true) {
                throw new RuntimeException('Workbook payroll tidak dapat dibuka untuk ekspor.');
            }

            $worksheetPath = $this->worksheetPath($archive, 'hasil ketika export');
            $worksheetXml = $archive->getFromName($worksheetPath);
            if ($worksheetXml === false) {
                throw new RuntimeException('Sheet hasil ketika export tidak ditemukan pada template payroll.');
            }

            $document = $this->loadXml($worksheetXml);
            $this->populateExportSheet($document, $entries);
            $updatedXml = $document->saveXML();
            if ($updatedXml === false || ! $archive->addFromString($worksheetPath, $updatedXml, ZipArchive::FL_OVERWRITE)) {
                throw new RuntimeException('Sheet hasil ekspor payroll tidak dapat diperbarui.');
            }

            $this->retainOnlyWorksheet($archive, 'hasil ketika export');

            if (! $archive->close()) {
                throw new RuntimeException('Workbook hasil ekspor payroll tidak dapat disimpan.');
            }

            return $temporaryFile;
        } catch (Throwable $exception) {
            $archive->close();
            @unlink($temporaryFile);

            throw $exception;
        }
    }

    public function exportMaster(array $entries): string
    {
        $temporaryFile = $this->temporaryTemplateCopy(self::TEMPLATE_PATH);
        $archive = new ZipArchive;

        try {
            if ($archive->open($temporaryFile) !== true) {
                throw new RuntimeException('Workbook payroll tidak dapat dibuka untuk ekspor Master Gaji.');
            }

            $worksheetPath = $this->worksheetPath($archive, 'hasil ketika export');
            $worksheetXml = $archive->getFromName($worksheetPath);
            if ($worksheetXml === false) {
                throw new RuntimeException('Sheet hasil ketika export tidak ditemukan pada template payroll.');
            }

            $document = $this->loadXml($worksheetXml);
            $this->populateExportSheet($document, $entries);
            $updatedXml = $document->saveXML();
            if ($updatedXml === false || ! $archive->addFromString($worksheetPath, $updatedXml, ZipArchive::FL_OVERWRITE)) {
                throw new RuntimeException('Sheet hasil ekspor Master Gaji tidak dapat diperbarui.');
            }
            $this->retainOnlyWorksheet($archive, 'hasil ketika export');

            if (! $archive->close()) {
                throw new RuntimeException('Workbook Master Gaji tidak dapat disimpan.');
            }

            return $temporaryFile;
        } catch (Throwable $exception) {
            $archive->close();
            @unlink($temporaryFile);

            throw $exception;
        }
    }

    /**
     * Populate the supplied Tukin workbook without changing its visual
     * structure. The workbook already contains both reference sheets.
     *
     * @param  array<int, array<string, mixed>>  $realEntries
     * @param  array<int, array<string, mixed>>  $seventyFiveEntries
     */
    public function exportTukin(array $realEntries, array $seventyFiveEntries): string
    {
        $temporaryFile = $this->temporaryTemplateCopy(self::TUKIN_TEMPLATE_PATH);
        $archive = new ZipArchive;

        try {
            if ($archive->open($temporaryFile) !== true) {
                throw new RuntimeException('Workbook template Tukin tidak dapat dibuka untuk ekspor.');
            }

            $worksheets = [];
            foreach (['tukin real', 'tukin 75%'] as $sheetName) {
                $worksheetPath = $this->worksheetPath($archive, $sheetName);
                $worksheetXml = $archive->getFromName($worksheetPath);
                if ($worksheetXml === false) {
                    throw new RuntimeException('Sheet '.$sheetName.' tidak ditemukan pada template Tukin.');
                }
                $worksheets[$sheetName] = [$worksheetPath, $worksheetXml];
            }

            $orphanedParts = $this->stripTukinExternalReferences($archive);
            foreach ([
                'tukin real' => $realEntries,
                'tukin 75%' => $seventyFiveEntries,
            ] as $sheetName => $entries) {
                [$worksheetPath, $worksheetXml] = $worksheets[$sheetName];
                $document = $this->loadXml($worksheetXml);
                $this->populateTukinSheet($document, $entries, $sheetName === 'tukin 75%');
                $updatedXml = $document->saveXML();
                if ($updatedXml === false || ! $archive->addFromString($worksheetPath, $updatedXml, ZipArchive::FL_OVERWRITE)) {
                    throw new RuntimeException('Sheet '.$sheetName.' tidak dapat diperbarui.');
                }
            }

            if (! $archive->close()) {
                throw new RuntimeException('Workbook hasil ekspor Tukin tidak dapat disimpan.');
            }

            $this->removeTukinOrphanedParts($temporaryFile, $orphanedParts);

            return $temporaryFile;
        } catch (Throwable $exception) {
            $archive->close();
            @unlink($temporaryFile);

            throw $exception;
        }
    }

    private function temporaryTemplateCopy(string $templatePath): string
    {
        $source = base_path($templatePath);
        if (! is_file($source) || ! is_readable($source)) {
            throw new RuntimeException('Template payroll tidak ditemukan pada resources/templates/payroll.');
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'simpeg-payroll-');
        if ($temporaryFile === false || ! copy($source, $temporaryFile)) {
            if ($temporaryFile !== false) {
                @unlink($temporaryFile);
            }

            throw new RuntimeException('Salinan sementara template payroll tidak dapat dibuat.');
        }

        return $temporaryFile;
    }

    private function populateExportSheet(DOMDocument $document, array $entries): void
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('m', self::WORKSHEET_NAMESPACE);
        $sheetData = $xpath->query('/m:worksheet/m:sheetData')->item(0);
        if (! $sheetData instanceof DOMElement) {
            throw new RuntimeException('Struktur sheet hasil ketika export tidak valid.');
        }

        $rows = [];
        foreach ($xpath->query('./m:row', $sheetData) as $row) {
            if ($row instanceof DOMElement) {
                $rows[(int) $row->getAttribute('r')] = $row;
            }
        }

        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber >= 18) {
                foreach ($xpath->query('./m:c', $row) as $cell) {
                    if ($cell instanceof DOMElement) {
                        $this->clearCell($cell);
                    }
                }
            }
        }

        $sums = array_fill_keys(array_column(self::COMPONENTS, 0), '0.00');
        foreach ($entries as $entry) {
            foreach (self::COMPONENTS as [$field]) {
                $sums[$field] = $this->addAmounts($sums[$field], (string) ($entry[$field] ?? '0'));
            }
        }

        foreach (self::COMPONENTS as $index => [$field]) {
            $rowNumber = $index + 1;
            $this->setCachedNumber($this->cell($rows[$rowNumber] ?? null, 'C'.$rowNumber, $xpath, $sheetData), $sums[$field]);
        }

        foreach (array_values($entries) as $index => $entry) {
            $rowNumber = $index + 18;
            $row = $rows[$rowNumber] ?? null;
            if ($rowNumber > 20 && $row instanceof DOMElement) {
                $sheetData->removeChild($row);
                $row = null;
            }
            $row ??= $this->cloneStyledRow($rows[18] ?? null, $rowNumber, $sheetData, $xpath);
            $rows[$rowNumber] = $row;

            $account = trim((string) ($entry['no_rekening'] ?? ''));
            $this->setFormulaText(
                $this->cell($row, 'A'.$rowNumber, $xpath, $sheetData),
                'LEFT($B'.$rowNumber.',3)',
                substr($account, 0, 3),
            );
            $this->setText($this->cell($row, 'B'.$rowNumber, $xpath, $sheetData), $account);
            $this->setText($this->cell($row, 'C'.$rowNumber, $xpath, $sheetData), (string) ($entry['nama_nasabah'] ?? ''));

            foreach (self::COMPONENTS as [$field, $column]) {
                $this->setNumber($this->cell($row, $column.$rowNumber, $xpath, $sheetData), (string) ($entry[$field] ?? '0'));
            }
            $this->setNumber($this->cell($row, 'S'.$rowNumber, $xpath, $sheetData), (string) ($entry['gaji_bersih'] ?? $entry['total'] ?? '0'));
        }

        $lastRow = max(199, count($entries) + 17);
        $this->setDimension($document, 'A1:T'.$lastRow);
        $this->sortRows($sheetData, $xpath);
    }

    private function populateTukinSheet(DOMDocument $document, array $entries, bool $seventyFive): void
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('m', self::WORKSHEET_NAMESPACE);
        $sheetData = $xpath->query('/m:worksheet/m:sheetData')->item(0);
        if (! $sheetData instanceof DOMElement) {
            throw new RuntimeException('Struktur sheet Tukin tidak valid.');
        }

        $rows = [];
        foreach ($xpath->query('./m:row', $sheetData) as $row) {
            if ($row instanceof DOMElement) {
                $rows[(int) $row->getAttribute('r')] = $row;
            }
        }
        $dataStyle = $rows[7] ?? null;
        $kpiStyle = $rows[9] ?? null;
        $footerStyle = $rows[11] ?? null;
        if (! $dataStyle instanceof DOMElement || ! $kpiStyle instanceof DOMElement || ! $footerStyle instanceof DOMElement) {
            throw new RuntimeException('Baris gaya sheet Tukin tidak lengkap.');
        }

        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber >= 7) {
                $sheetData->removeChild($row);
            }
        }

        $kpiRow = 7 + count($entries) + 1;
        foreach (array_values($entries) as $index => $entry) {
            $rowNumber = $index + 7;
            $row = $this->cloneStyledRow($dataStyle, $rowNumber, $sheetData, $xpath);
            $this->populateTukinRow($row, $rowNumber, $entry, $seventyFive, $kpiRow, $xpath, $sheetData);
        }

        $kpi = $this->cloneStyledRow($kpiStyle, $kpiRow, $sheetData, $xpath);
        $this->setText($this->cell($kpi, 'B'.$kpiRow, $xpath, $sheetData), 'KPI Konsolidasi/cabang :');
        $this->setNumber($this->cell($kpi, 'C'.$kpiRow, $xpath, $sheetData), (string) ($entries[0]['kpi'] ?? PayrollTukinExportService::DEFAULT_KPI));

        $footerRow = $kpiRow + 2;
        $footer = $this->cloneStyledRow($footerStyle, $footerRow, $sheetData, $xpath);
        $this->setText(
            $this->cell($footer, 'B'.$footerRow, $xpath, $sheetData),
            $seventyFive ? 'Slip Gaji untuk Pengajuan Kredit Pegawai' : 'Take Home Pay / bulan',
        );
        if ($seventyFive) {
            foreach ($xpath->query('/m:worksheet/m:mergeCells/m:mergeCell[@ref="B11:D11"]') as $merge) {
                if ($merge instanceof DOMElement) {
                    $merge->setAttribute('ref', 'B'.$footerRow.':D'.$footerRow);
                }
            }
        }

        $this->widenTukinAmountColumns($document);
        $this->setDimension($document, 'A1:AC'.$footerRow);
        $this->sortRows($sheetData, $xpath);
    }

    private function widenTukinAmountColumns(DOMDocument $document): void
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('m', self::WORKSHEET_NAMESPACE);

        foreach ($xpath->query('/m:worksheet/m:cols/m:col') as $column) {
            if (! $column instanceof DOMElement) {
                continue;
            }

            $min = (int) $column->getAttribute('min');
            $max = (int) $column->getAttribute('max');
            if ($max < 10 || $min > 29) {
                continue;
            }

            // The supplied template has a few narrow amount columns (for
            // example Tunj. Jabatan and Honor Kontrak). A value such as
            // 1.545.000 is valid, but Excel renders it as "###" when the
            // column is too narrow. Widen only columns below the safe minimum
            // so the original visual proportions remain intact elsewhere.
            $width = (float) $column->getAttribute('width');
            if ($width >= 12.5) {
                continue;
            }

            $column->setAttribute('width', '12.5');
            $column->setAttribute('customWidth', '1');
            $column->removeAttribute('bestFit');
        }
    }

    private function populateTukinRow(DOMElement $row, int $rowNumber, array $entry, bool $seventyFive, int $kpiRow, DOMXPath $xpath, DOMElement $sheetData): void
    {
        foreach ([
            'A' => $entry['employee_id'] ?? '',
            'B' => $entry['employee_name'] ?? '',
            'C' => $entry['position_name'] ?? '',
            'D' => $entry['unit_name'] ?? '',
            'E' => $entry['employment_status'] ?? '',
            'F' => $entry['grade'] ?? '',
        ] as $column => $value) {
            $this->setText($this->cell($row, $column.$rowNumber, $xpath, $sheetData), (string) $value);
        }

        foreach ([
            'G' => $entry['service_years'] ?? 0,
            'H' => $entry['spouse_count'] ?? 0,
            'I' => $entry['child_count'] ?? 0,
            'J' => $entry['gaji_pokok'] ?? 0,
            'N' => $entry['tunj_jabatan'] ?? 0,
            'O' => $entry['tunj_ops'] ?? 0,
            'P' => $entry['tunj_kinerja_100'] ?? 0,
            'S' => $entry['tunj_masa_kerja'] ?? 0,
            'T' => $entry['tunj_fungsional'] ?? 0,
            'U' => $entry['honor_kontrak'] ?? 0,
            'W' => $entry['potongan_angsuran'] ?? 0,
            'X' => $entry['potongan_absen'] ?? 0,
            'Y' => $entry['potongan_bpjs'] ?? 0,
            'Z' => $entry['pengembalian_tunjangan'] ?? 0,
            'AA' => $entry['pengalihan_keris'] ?? 0,
            'AB' => $entry['uang_makan_kontrak'] ?? 0,
        ] as $column => $value) {
            $this->setNumber($this->cell($row, $column.$rowNumber, $xpath, $sheetData), (string) $value);
        }

        $this->setFormulaNumber($this->cell($row, 'K'.$rowNumber, $xpath, $sheetData), '$H'.$rowNumber.'*10%*$J'.$rowNumber, (string) ($entry['tunj_suami_istri'] ?? 0));
        $this->setFormulaNumber($this->cell($row, 'L'.$rowNumber, $xpath, $sheetData), '$I'.$rowNumber.'*5%*$J'.$rowNumber, (string) ($entry['tunj_anak'] ?? 0));
        $this->setFormulaNumber($this->cell($row, 'M'.$rowNumber, $xpath, $sheetData), 'SUM(J'.$rowNumber.':L'.$rowNumber.')', (string) ($entry['jumlah_gaji_dasar'] ?? 0));
        $this->setFormulaNumber($this->cell($row, 'Q'.$rowNumber, $xpath, $sheetData), '75%*P'.$rowNumber, (string) ($entry['tunj_kinerja_75'] ?? 0));
        $this->setFormulaNumber($this->cell($row, 'R'.$rowNumber, $xpath, $sheetData), '$Q'.$rowNumber.'+(($C'.$kpiRow.')*($P'.$rowNumber.'-$Q'.$rowNumber.'))', (string) ($entry['tunj_kinerja_real'] ?? 0));
        $this->setFormulaNumber(
            $this->cell($row, 'V'.$rowNumber, $xpath, $sheetData),
            $seventyFive ? 'SUM(M'.$rowNumber.':O'.$rowNumber.')+Q'.$rowNumber.'+SUM(S'.$rowNumber.':U'.$rowNumber.')' : 'SUM(M'.$rowNumber.':O'.$rowNumber.')+SUM(R'.$rowNumber.':U'.$rowNumber.')',
            (string) ($entry['jumlah_pendapatan'] ?? 0),
        );
        $this->setFormulaNumber($this->cell($row, 'AC'.$rowNumber, $xpath, $sheetData), 'V'.$rowNumber.'-SUM($W'.$rowNumber.':$AA'.$rowNumber.')+$AB'.$rowNumber, (string) ($entry['gaji_diterima'] ?? 0));
    }

    /**
     * The reference workbook contains an old external link and calc chain.
     * Neither is used by the export, so remove them from the generated copy
     * to avoid Excel prompting for a local Windows file path.
     */
    /**
     * @return array<int, string>
     */
    private function stripTukinExternalReferences(ZipArchive $archive): array
    {
        $workbookXml = $archive->getFromName('xl/workbook.xml');
        $relationshipsXml = $archive->getFromName('xl/_rels/workbook.xml.rels');
        $contentTypesXml = $archive->getFromName('[Content_Types].xml');
        if ($workbookXml === false || $relationshipsXml === false || $contentTypesXml === false) {
            throw new RuntimeException('Metadata workbook Tukin tidak lengkap.');
        }

        $workbook = $this->loadXml($workbookXml);
        $workbookXPath = new DOMXPath($workbook);
        $workbookXPath->registerNamespace('m', self::WORKSHEET_NAMESPACE);
        foreach ($workbookXPath->query('/m:workbook/m:externalReferences | /m:workbook/m:definedNames/m:definedName[@name="GAJI"] | /m:workbook/*[local-name()="AlternateContent"]') as $node) {
            $node->parentNode?->removeChild($node);
        }

        $relationships = $this->loadXml($relationshipsXml);
        $relationshipXPath = new DOMXPath($relationships);
        $relationshipXPath->registerNamespace('p', self::PACKAGE_RELATIONSHIP_NAMESPACE);
        $removedParts = [];
        foreach ($relationshipXPath->query('/p:Relationships/p:Relationship') as $relationship) {
            if (! $relationship instanceof DOMElement) {
                continue;
            }

            $type = $relationship->getAttribute('Type');
            if (! str_ends_with($type, '/externalLink') && ! str_ends_with($type, '/calcChain')) {
                continue;
            }

            $target = $this->packagePath('xl', $relationship->getAttribute('Target'));
            $removedParts[] = $target;
            $relationship->parentNode?->removeChild($relationship);
        }

        $contentTypes = $this->loadXml($contentTypesXml);
        $contentTypesXPath = new DOMXPath($contentTypes);
        $contentTypesXPath->registerNamespace('ct', 'http://schemas.openxmlformats.org/package/2006/content-types');
        foreach ($contentTypesXPath->query('/ct:Types/ct:Override') as $override) {
            if (! $override instanceof DOMElement) {
                continue;
            }

            $partName = ltrim($override->getAttribute('PartName'), '/');
            if (in_array($partName, $removedParts, true)) {
                $override->parentNode?->removeChild($override);
            }
        }

        // Do not delete ZIP entries while the archive is still being edited.
        // ZipArchive can invalidate compressed entries after deleteName(),
        // which would make the next worksheet unreadable. The caller removes
        // these now-orphaned parts after the first close/reopen cycle.

        $updatedWorkbook = $workbook->saveXML();
        $updatedRelationships = $relationships->saveXML();
        $updatedContentTypes = $contentTypes->saveXML();
        if ($updatedWorkbook === false || $updatedRelationships === false || $updatedContentTypes === false
            || ! $archive->addFromString('xl/workbook.xml', $updatedWorkbook, ZipArchive::FL_OVERWRITE)
            || ! $archive->addFromString('xl/_rels/workbook.xml.rels', $updatedRelationships, ZipArchive::FL_OVERWRITE)
            || ! $archive->addFromString('[Content_Types].xml', $updatedContentTypes, ZipArchive::FL_OVERWRITE)) {
            throw new RuntimeException('Referensi eksternal workbook Tukin tidak dapat dibersihkan.');
        }

        return array_values(array_unique($removedParts));
    }

    /**
     * Remove the unused external-link and calc-chain parts after the workbook
     * metadata has been saved and the archive can safely be reopened.
     *
     * @param  array<int, string>  $parts
     */
    private function removeTukinOrphanedParts(string $path, array $parts): void
    {
        if ($parts === []) {
            return;
        }

        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            throw new RuntimeException('Part lama workbook Tukin tidak dapat dibuka untuk dibersihkan.');
        }

        try {
            foreach ($parts as $part) {
                $archive->deleteName($part);
                $archive->deleteName(dirname($part).'/_rels/'.basename($part).'.rels');
            }

            if (! $archive->close()) {
                throw new RuntimeException('Part lama workbook Tukin tidak dapat dihapus.');
            }
        } catch (Throwable $exception) {
            $archive->close();

            throw $exception;
        }
    }

    private function populateImportSheet(DOMDocument $document, array $entries): void
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('m', self::WORKSHEET_NAMESPACE);
        $sheetData = $xpath->query('/m:worksheet/m:sheetData')->item(0);
        if (! $sheetData instanceof DOMElement) {
            throw new RuntimeException('Struktur sheet Template Import tidak valid.');
        }

        $rows = [];
        foreach ($xpath->query('./m:row', $sheetData) as $row) {
            if ($row instanceof DOMElement) {
                $rows[(int) $row->getAttribute('r')] = $row;
            }
        }
        $styleRow = $rows[2] ?? null;
        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber < 2) {
                continue;
            }
            foreach ($xpath->query('./m:c', $row) as $cell) {
                if ($cell instanceof DOMElement) {
                    $this->clearCell($cell);
                }
            }
        }

        $amountColumns = ['F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U'];
        foreach (array_values($entries) as $index => $entry) {
            $rowNumber = $index + 2;
            $row = $rows[$rowNumber] ?? $this->cloneStyledRow($styleRow, $rowNumber, $sheetData, $xpath);
            $rows[$rowNumber] = $row;

            $this->setText($this->cell($row, 'A'.$rowNumber, $xpath, $sheetData), (string) ($entry['office_code'] ?? ''));
            $this->setText($this->cell($row, 'B'.$rowNumber, $xpath, $sheetData), (string) ($entry['employee_id'] ?? ''));
            $this->setText($this->cell($row, 'C'.$rowNumber, $xpath, $sheetData), (string) ($entry['bank_office_code'] ?? ''));
            $this->setText($this->cell($row, 'D'.$rowNumber, $xpath, $sheetData), (string) ($entry['no_rekening'] ?? ''));
            $this->setText($this->cell($row, 'E'.$rowNumber, $xpath, $sheetData), (string) ($entry['nama_nasabah'] ?? ''));
            foreach (self::COMPONENTS as $index => [$field]) {
                $this->setNumber($this->cell($row, $amountColumns[$index].$rowNumber, $xpath, $sheetData), (string) ($entry[$field] ?? '0'));
            }
            $this->setNumber($this->cell($row, 'U'.$rowNumber, $xpath, $sheetData), (string) ($entry['total'] ?? '0'));
            $this->setText($this->cell($row, 'V'.$rowNumber, $xpath, $sheetData), (string) ($entry['tgl_gajian'] ?? ''));
        }

        $this->setDimension($document, 'A1:V'.max(199, count($entries) + 1));
        $this->sortRows($sheetData, $xpath);
    }

    private function worksheetPath(ZipArchive $archive, string $sheetName): string
    {
        $workbookXml = $archive->getFromName('xl/workbook.xml');
        $relationshipsXml = $archive->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml === false || $relationshipsXml === false) {
            throw new RuntimeException('Metadata workbook payroll tidak lengkap.');
        }

        $workbook = $this->loadXml($workbookXml);
        $relationships = $this->loadXml($relationshipsXml);
        $workbookXPath = new DOMXPath($workbook);
        $workbookXPath->registerNamespace('m', self::WORKSHEET_NAMESPACE);
        $workbookXPath->registerNamespace('r', self::RELATIONSHIP_NAMESPACE);
        $relationshipId = null;
        foreach ($workbookXPath->query('/m:workbook/m:sheets/m:sheet') as $sheet) {
            if ($sheet instanceof DOMElement && $sheet->getAttribute('name') === $sheetName) {
                $relationshipId = $sheet->getAttributeNS(self::RELATIONSHIP_NAMESPACE, 'id');
                break;
            }
        }

        if ($relationshipId === null || $relationshipId === '') {
            throw new RuntimeException('Sheet '.$sheetName.' tidak ditemukan pada template payroll.');
        }

        $relationshipXPath = new DOMXPath($relationships);
        $relationshipXPath->registerNamespace('p', self::PACKAGE_RELATIONSHIP_NAMESPACE);
        foreach ($relationshipXPath->query('/p:Relationships/p:Relationship') as $relationship) {
            if ($relationship instanceof DOMElement && $relationship->getAttribute('Id') === $relationshipId) {
                $target = ltrim($relationship->getAttribute('Target'), '/');

                return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            }
        }

        throw new RuntimeException('Lokasi sheet '.$sheetName.' tidak ditemukan pada template payroll.');
    }

    private function retainOnlyWorksheet(ZipArchive $archive, string $sheetName, bool $includeWorksheetXml = false): array
    {
        $workbookXml = $archive->getFromName('xl/workbook.xml');
        $relationshipsXml = $archive->getFromName('xl/_rels/workbook.xml.rels');
        $contentTypesXml = $archive->getFromName('[Content_Types].xml');
        if ($workbookXml === false || $relationshipsXml === false || $contentTypesXml === false) {
            throw new RuntimeException('Metadata workbook payroll tidak lengkap.');
        }

        $workbook = $this->loadXml($workbookXml);
        $relationships = $this->loadXml($relationshipsXml);
        $contentTypes = $this->loadXml($contentTypesXml);

        $workbookXPath = new DOMXPath($workbook);
        $workbookXPath->registerNamespace('m', self::WORKSHEET_NAMESPACE);
        $sheets = $workbookXPath->query('/m:workbook/m:sheets/m:sheet');
        $allSheets = iterator_to_array($sheets);
        $selectedSheet = null;
        $selectedIndex = null;
        $sheetRelationships = [];
        foreach ($allSheets as $index => $sheet) {
            if (! $sheet instanceof DOMElement) {
                continue;
            }

            $relationshipId = $sheet->getAttributeNS(self::RELATIONSHIP_NAMESPACE, 'id');
            $sheetRelationships[$relationshipId] = null;
            if ($sheet->getAttribute('name') === $sheetName) {
                $selectedSheet = $sheet;
                $selectedIndex = $index;
            }
        }

        if (! $selectedSheet instanceof DOMElement || $selectedIndex === null) {
            throw new RuntimeException('Sheet '.$sheetName.' tidak ditemukan pada template payroll.');
        }

        $relationshipDocument = $this->loadXml($relationshipsXml);
        $relationshipXPath = new DOMXPath($relationshipDocument);
        $relationshipXPath->registerNamespace('p', self::PACKAGE_RELATIONSHIP_NAMESPACE);
        $selectedRelationshipId = $selectedSheet->getAttributeNS(self::RELATIONSHIP_NAMESPACE, 'id');
        $worksheetPaths = [];
        $selectedPath = null;
        $allRelationships = iterator_to_array($relationshipXPath->query('/p:Relationships/p:Relationship'));
        foreach ($allRelationships as $relationship) {
            if (! $relationship instanceof DOMElement) {
                continue;
            }

            $relationshipId = $relationship->getAttribute('Id');
            if (! array_key_exists($relationshipId, $sheetRelationships)) {
                continue;
            }

            $target = $relationship->getAttribute('Target');
            $path = $this->packagePath('xl', $target);
            $sheetRelationships[$relationshipId] = $path;
            if ($relationshipId === $selectedRelationshipId) {
                $selectedPath = $path;
            } else {
                $worksheetPaths[] = $path;
                $relationship->parentNode?->removeChild($relationship);
            }
        }

        if ($selectedPath === null) {
            throw new RuntimeException('Lokasi sheet '.$sheetName.' tidak ditemukan pada template payroll.');
        }
        $selectedWorksheetXml = null;
        if ($includeWorksheetXml) {
            $selectedWorksheetXml = $archive->getFromName($selectedPath);
            if ($selectedWorksheetXml === false) {
                throw new RuntimeException('Isi sheet '.$sheetName.' tidak ditemukan pada template payroll.');
            }
        }

        foreach ($allSheets as $sheet) {
            if ($sheet instanceof DOMElement && $sheet !== $selectedSheet) {
                $sheet->parentNode?->removeChild($sheet);
            }
        }

        $workbookXPath->registerNamespace('m', self::WORKSHEET_NAMESPACE);
        foreach ($workbookXPath->query('/m:workbook/m:bookViews/m:workbookView') as $view) {
            if ($view instanceof DOMElement) {
                $view->setAttribute('activeTab', '0');
            }
        }

        foreach ($workbookXPath->query('/m:workbook/m:definedNames/m:definedName[@localSheetId]') as $definedName) {
            if (! $definedName instanceof DOMElement) {
                continue;
            }

            if ((int) $definedName->getAttribute('localSheetId') !== $selectedIndex) {
                $definedName->parentNode?->removeChild($definedName);
            } else {
                $definedName->setAttribute('localSheetId', '0');
            }
        }

        $contentTypesXPath = new DOMXPath($contentTypes);
        $contentTypesXPath->registerNamespace('ct', 'http://schemas.openxmlformats.org/package/2006/content-types');
        foreach ($worksheetPaths as $path) {
            $archive->deleteName($path);
            $sheetRelationshipsPath = dirname($path).'/_rels/'.basename($path).'.rels';
            $archive->deleteName($sheetRelationshipsPath);

            $partName = '/'.$path;
            foreach ($contentTypesXPath->query('/ct:Types/ct:Override[@PartName="'.$partName.'"]') as $override) {
                $override->parentNode?->removeChild($override);
            }
        }

        $updatedWorkbook = $workbook->saveXML();
        $updatedRelationships = $relationshipDocument->saveXML();
        $updatedContentTypes = $contentTypes->saveXML();
        if ($updatedWorkbook === false || $updatedRelationships === false || $updatedContentTypes === false
            || ! $archive->addFromString('xl/workbook.xml', $updatedWorkbook, ZipArchive::FL_OVERWRITE)
            || ! $archive->addFromString('xl/_rels/workbook.xml.rels', $updatedRelationships, ZipArchive::FL_OVERWRITE)
            || ! $archive->addFromString('[Content_Types].xml', $updatedContentTypes, ZipArchive::FL_OVERWRITE)) {
            throw new RuntimeException('Struktur tab workbook payroll tidak dapat diperbarui.');
        }

        return [
            'worksheet' => $selectedWorksheetXml,
            'workbook' => $updatedWorkbook,
            'relationships' => $updatedRelationships,
            'content_types' => $updatedContentTypes,
        ];
    }

    /**
     * Menambahkan sheet contoh tanpa mengubah sheet yang dipakai untuk import.
     * Sheet ini sengaja dibuat terpisah agar contoh tidak ikut tersimpan sebagai
     * data payroll ketika pengguna mengisi sheet "Template Import".
     */
    private function addPayrollImportExampleWorksheet(ZipArchive $archive, array $retainedParts): void
    {
        $worksheetXml = $retainedParts['worksheet'] ?? false;
        $workbookXml = $retainedParts['workbook'] ?? false;
        $relationshipsXml = $retainedParts['relationships'] ?? false;
        $contentTypesXml = $retainedParts['content_types'] ?? false;
        if (! is_string($worksheetXml) || ! is_string($workbookXml) || ! is_string($relationshipsXml) || ! is_string($contentTypesXml)) {
            throw new RuntimeException('Metadata template contoh payroll tidak lengkap.');
        }

        $worksheet = $this->loadXml($worksheetXml);
        $worksheetXPath = new DOMXPath($worksheet);
        $worksheetXPath->registerNamespace('m', self::WORKSHEET_NAMESPACE);
        $sheetData = $worksheetXPath->query('/m:worksheet/m:sheetData')->item(0);
        if (! $sheetData instanceof DOMElement) {
            throw new RuntimeException('Struktur sheet contoh payroll tidak valid.');
        }

        $styleRow = null;
        foreach ($worksheetXPath->query('./m:row', $sheetData) as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            $rowNumber = (int) $row->getAttribute('r');
            if ($rowNumber === 3) {
                $styleRow = $row;
            }
            if ($rowNumber >= 3) {
                $sheetData->removeChild($row);
            }
        }

        $exampleRow = $this->cloneStyledRow($styleRow, 3, $sheetData, $worksheetXPath);
        $numberValues = [
            'A' => '1',
            'L' => '0',
            'M' => '0',
            'N' => '5000000',
            'O' => '0',
            'P' => '0',
            'Q' => '500000',
            'R' => '250000',
            'S' => '0',
            'T' => '0',
            'U' => '0',
            'V' => '0',
            'W' => '0',
            'X' => '0',
            'Y' => '0',
            'Z' => '5750000',
            'AA' => '150000',
            'AB' => '100000',
            'AC' => '0',
            'AD' => '0',
            'AE' => '250000',
            'AF' => '500000',
            'AG' => '5250000',
        ];
        foreach ($numberValues as $column => $value) {
            $this->setNumber(
                $this->cell($exampleRow, $column.'3', $worksheetXPath, $sheetData),
                $value,
            );
        }

        $textValues = [
            'B' => '000000',
            'C' => 'CONTOH-001',
            'D' => '001',
            'E' => '1234567890',
            'F' => 'Contoh Pegawai',
            'G' => 'Staf Administrasi',
            'H' => 'Kantor Pusat',
            'I' => 'Aktif',
            'J' => 'III/a',
            'K' => '1 tahun 2 bulan',
            'AH' => '1234567890',
            'AI' => '081234567890',
            'AJ' => '07-11-2026',
        ];
        foreach ($textValues as $column => $value) {
            $this->setText(
                $this->cell($exampleRow, $column.'3', $worksheetXPath, $sheetData),
                $value,
            );
        }

        $this->setDimension($worksheet, 'A1:AJ3');
        $updatedWorksheet = $worksheet->saveXML();
        if ($updatedWorksheet === false || ! $archive->addFromString('xl/worksheets/sheet2.xml', $updatedWorksheet, ZipArchive::FL_OVERWRITE)) {
            throw new RuntimeException('Sheet contoh pengisian payroll tidak dapat dibuat.');
        }

        $workbook = $this->loadXml($workbookXml);
        $relationships = $this->loadXml($relationshipsXml);
        $contentTypes = $this->loadXml($contentTypesXml);

        $relationshipXPath = new DOMXPath($relationships);
        $relationshipXPath->registerNamespace('p', self::PACKAGE_RELATIONSHIP_NAMESPACE);
        $usedRelationshipIds = [];
        foreach ($relationshipXPath->query('/p:Relationships/p:Relationship') as $relationship) {
            if ($relationship instanceof DOMElement) {
                $usedRelationshipIds[] = $relationship->getAttribute('Id');
            }
        }
        $relationshipNumber = 1;
        do {
            $relationshipId = 'rId'.$relationshipNumber++;
        } while (in_array($relationshipId, $usedRelationshipIds, true));

        $relationship = $relationships->createElementNS(self::PACKAGE_RELATIONSHIP_NAMESPACE, 'Relationship');
        $relationship->setAttribute('Id', $relationshipId);
        $relationship->setAttribute('Type', self::RELATIONSHIP_NAMESPACE.'/worksheet');
        $relationship->setAttribute('Target', 'worksheets/sheet2.xml');
        $relationships->documentElement?->appendChild($relationship);

        $workbookXPath = new DOMXPath($workbook);
        $workbookXPath->registerNamespace('m', self::WORKSHEET_NAMESPACE);
        $sheets = $workbookXPath->query('/m:workbook/m:sheets')->item(0);
        if (! $sheets instanceof DOMElement) {
            throw new RuntimeException('Daftar sheet template payroll tidak valid.');
        }

        $sheetId = 0;
        foreach ($workbookXPath->query('/m:workbook/m:sheets/m:sheet') as $sheet) {
            if ($sheet instanceof DOMElement) {
                $sheetId = max($sheetId, (int) $sheet->getAttribute('sheetId'));
            }
        }
        $exampleSheet = $workbook->createElementNS(self::WORKSHEET_NAMESPACE, 'sheet');
        $exampleSheet->setAttribute('name', 'Contoh Pengisian');
        $exampleSheet->setAttribute('sheetId', (string) ($sheetId + 1));
        $exampleSheet->setAttributeNS(self::RELATIONSHIP_NAMESPACE, 'r:id', $relationshipId);
        $sheets->appendChild($exampleSheet);

        $contentTypesXPath = new DOMXPath($contentTypes);
        $contentTypesXPath->registerNamespace('ct', 'http://schemas.openxmlformats.org/package/2006/content-types');
        $override = $contentTypes->createElementNS('http://schemas.openxmlformats.org/package/2006/content-types', 'Override');
        $override->setAttribute('PartName', '/xl/worksheets/sheet2.xml');
        $override->setAttribute('ContentType', 'application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml');
        $contentTypes->documentElement?->appendChild($override);

        $updatedWorkbook = $workbook->saveXML();
        $updatedRelationships = $relationships->saveXML();
        $updatedContentTypes = $contentTypes->saveXML();
        if ($updatedWorkbook === false || $updatedRelationships === false || $updatedContentTypes === false
            || ! $archive->addFromString('xl/workbook.xml', $updatedWorkbook, ZipArchive::FL_OVERWRITE)
            || ! $archive->addFromString('xl/_rels/workbook.xml.rels', $updatedRelationships, ZipArchive::FL_OVERWRITE)
            || ! $archive->addFromString('[Content_Types].xml', $updatedContentTypes, ZipArchive::FL_OVERWRITE)) {
            throw new RuntimeException('Metadata sheet contoh payroll tidak dapat diperbarui.');
        }
    }

    private function packagePath(string $baseDirectory, string $target): string
    {
        $parts = str_starts_with($target, '/') ? [] : explode('/', $baseDirectory);
        foreach (explode('/', ltrim($target, '/')) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);

                continue;
            }

            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    private function loadXml(string $xml): DOMDocument
    {
        $document = new DOMDocument;
        $document->preserveWhiteSpace = true;
        $document->formatOutput = false;
        if (! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('XML workbook payroll tidak dapat dibaca.');
        }

        return $document;
    }

    private function cell(?DOMElement $row, string $reference, DOMXPath $xpath, DOMElement $sheetData): DOMElement
    {
        if ($row === null) {
            $rowNumber = (int) preg_replace('/\D+/', '', $reference);
            $row = $this->cloneStyledRow(null, $rowNumber, $sheetData, $xpath);
        }

        foreach ($xpath->query('./m:c', $row) as $cell) {
            if ($cell instanceof DOMElement && $cell->getAttribute('r') === $reference) {
                return $cell;
            }
        }

        $cell = $row->ownerDocument->createElementNS(self::WORKSHEET_NAMESPACE, 'c');
        $cell->setAttribute('r', $reference);
        $column = preg_replace('/\d+/', '', $reference);
        $targetIndex = $this->columnIndex((string) $column);
        $insertBefore = null;
        foreach ($xpath->query('./m:c', $row) as $existing) {
            if ($existing instanceof DOMElement && $this->columnIndex(preg_replace('/\d+/', '', $existing->getAttribute('r'))) > $targetIndex) {
                $insertBefore = $existing;
                break;
            }
        }
        $row->insertBefore($cell, $insertBefore);

        return $cell;
    }

    private function cloneStyledRow(?DOMElement $templateRow, int $rowNumber, DOMElement $sheetData, DOMXPath $xpath): DOMElement
    {
        $document = $sheetData->ownerDocument;
        if ($templateRow === null) {
            $row = $document->createElementNS(self::WORKSHEET_NAMESPACE, 'row');
            $row->setAttribute('r', (string) $rowNumber);
        } else {
            $row = $templateRow->cloneNode(true);
            if (! $row instanceof DOMElement) {
                throw new RuntimeException('Baris gaya template payroll tidak dapat disalin.');
            }
            $row->setAttribute('r', (string) $rowNumber);
            foreach ($xpath->query('./m:c', $row) as $cell) {
                if ($cell instanceof DOMElement) {
                    $reference = preg_replace('/\d+/', '', $cell->getAttribute('r'));
                    $cell->setAttribute('r', $reference.$rowNumber);
                    $this->clearCell($cell);
                }
            }
        }

        $insertBefore = null;
        foreach ($xpath->query('./m:row', $sheetData) as $existing) {
            if ($existing instanceof DOMElement && (int) $existing->getAttribute('r') > $rowNumber) {
                $insertBefore = $existing;
                break;
            }
        }
        $sheetData->insertBefore($row, $insertBefore);

        return $row;
    }

    private function clearCell(DOMElement $cell): void
    {
        while ($cell->firstChild !== null) {
            $cell->removeChild($cell->firstChild);
        }
        $cell->removeAttribute('t');
    }

    private function setText(DOMElement $cell, string $value): void
    {
        $this->clearCell($cell);
        $cell->setAttribute('t', 'inlineStr');
        $inline = $cell->ownerDocument->createElementNS(self::WORKSHEET_NAMESPACE, 'is');
        $text = $cell->ownerDocument->createElementNS(self::WORKSHEET_NAMESPACE, 't');
        $text->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        $text->appendChild($cell->ownerDocument->createTextNode($value));
        $inline->appendChild($text);
        $cell->appendChild($inline);
    }

    private function setFormulaText(DOMElement $cell, string $formula, string $cachedValue): void
    {
        $this->clearCell($cell);
        $cell->setAttribute('t', 'str');
        $cell->appendChild($cell->ownerDocument->createElementNS(self::WORKSHEET_NAMESPACE, 'f', $formula));
        $cell->appendChild($cell->ownerDocument->createElementNS(self::WORKSHEET_NAMESPACE, 'v', $cachedValue));
    }

    private function setFormulaNumber(DOMElement $cell, string $formula, string $cachedValue): void
    {
        $this->clearCell($cell);
        $cell->setAttribute('t', 'n');
        $cell->appendChild($cell->ownerDocument->createElementNS(self::WORKSHEET_NAMESPACE, 'f', $formula));
        $cell->appendChild($cell->ownerDocument->createElementNS(self::WORKSHEET_NAMESPACE, 'v', $this->number($cachedValue)));
    }

    private function setNumber(DOMElement $cell, string $value): void
    {
        $this->clearCell($cell);
        $cell->setAttribute('t', 'n');
        $cell->appendChild($cell->ownerDocument->createElementNS(self::WORKSHEET_NAMESPACE, 'v', $this->number($value)));
    }

    private function setCachedNumber(DOMElement $cell, string $value): void
    {
        foreach (iterator_to_array($cell->childNodes) as $child) {
            if ($child instanceof DOMElement && $child->localName === 'v') {
                $cell->removeChild($child);
            }
        }
        $cell->setAttribute('t', 'n');
        $cell->appendChild($cell->ownerDocument->createElementNS(self::WORKSHEET_NAMESPACE, 'v', $this->number($value)));
    }

    private function setDimension(DOMDocument $document, string $range): void
    {
        $root = $document->documentElement;
        if (! $root instanceof DOMElement) {
            throw new RuntimeException('Worksheet hasil ekspor payroll tidak valid.');
        }

        $dimensions = $root->getElementsByTagNameNS(self::WORKSHEET_NAMESPACE, 'dimension');
        if ($dimensions->length > 0) {
            $dimension = $dimensions->item(0);
        } else {
            $dimension = $document->createElementNS(self::WORKSHEET_NAMESPACE, 'dimension');
            $root->insertBefore($dimension, $root->firstChild);
        }
        $dimension->setAttribute('ref', $range);
    }

    private function sortRows(DOMElement $sheetData, DOMXPath $xpath): void
    {
        $rows = [];
        foreach ($xpath->query('./m:row', $sheetData) as $row) {
            if ($row instanceof DOMElement) {
                $rows[] = $row;
            }
        }
        usort($rows, fn (DOMElement $left, DOMElement $right): int => (int) $left->getAttribute('r') <=> (int) $right->getAttribute('r'));
        foreach ($rows as $row) {
            $sheetData->appendChild($row);
        }
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split(strtoupper($letters)) as $letter) {
            $index = $index * 26 + ord($letter) - 64;
        }

        return $index;
    }

    private function number(string $value): string
    {
        $value = trim($value);
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            return '0';
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = rtrim($fraction, '0');

        return ($value[0] === '-' && ($whole !== '0' || $fraction !== '') ? '-' : '')
            .$whole
            .($fraction === '' ? '' : '.'.$fraction);
    }

    private function addAmounts(string $left, string $right): string
    {
        if (function_exists('bcadd')) {
            return bcadd($left, $right, 2);
        }

        return number_format((float) $left + (float) $right, 2, '.', '');
    }
}
