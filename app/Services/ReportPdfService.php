<?php

namespace App\Services;

use Dompdf\Dompdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportPdfService
{
    public function download(string $view, array $data, string $filename): StreamedResponse
    {
        $html = view($view, [...$data, 'pdf' => true])->render();
        $dompdf = new Dompdf;
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $content = $dompdf->output();

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
