<?php

namespace App\Services;

use Dompdf\Dompdf;
use RuntimeException;

class TukinPayslipPdfService
{
    public function download(array $slip, string $mode): mixed
    {
        $template = resource_path('views/files/tukin-payslip.blade.php');
        if (! is_file($template)) {
            throw new RuntimeException('Template PDF slip Tukin belum tersedia.');
        }

        $html = view('files.tukin-payslip', ['slip' => $slip, 'mode' => $mode])->render();
        $dompdf = new Dompdf;
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $content = $dompdf->output();
        $modeLabel = $mode === '75' ? '75persen' : 'real';
        $filename = sprintf('Slip-Tukin-%s-%s-%s.pdf', $slip['employee_id'], str_replace('-', '', $slip['period']), $modeLabel);

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
