<?php

namespace App\Services;

use Dompdf\Dompdf;
use RuntimeException;

class PayslipPdfService
{
    public function download(array $slip): mixed
    {
        $template = resource_path('views/files/payslip.blade.php');
        if (! is_file($template)) {
            throw new RuntimeException('Template PDF slip gaji belum tersedia.');
        }

        $html = view('files.payslip', ['slip' => $slip])->render();
        $dompdf = new Dompdf;
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();
        $content = $dompdf->output();
        $filename = sprintf('Slip-Gaji-%s-%s.pdf', $slip['employee_id'], str_replace('-', '', $slip['period']));

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
