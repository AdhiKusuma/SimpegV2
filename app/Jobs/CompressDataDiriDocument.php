<?php

namespace App\Jobs;

use App\Models\DataDiriDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

class CompressDataDiriDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $documentId, public string $expectedPath) {}

    public function handle(): void
    {
        $document = DataDiriDocument::query()->find($this->documentId);
        if (! $document || $document->path !== $this->expectedPath) {
            return;
        }

        $finder = new Process(['which', 'gs']);
        $finder->run();
        $binary = trim($finder->getOutput());
        if (! $finder->isSuccessful() || $binary === '') {
            return;
        }

        $disk = Storage::disk((string) config('filesystems.default', 'local'));
        $source = $disk->readStream($document->path);
        if (! is_resource($source)) {
            return;
        }

        $input = tempnam(sys_get_temp_dir(), 'simpeg-data-diri-');
        $output = tempnam(sys_get_temp_dir(), 'simpeg-data-diri-');
        if ($input === false || $output === false) {
            if (is_resource($source)) {
                fclose($source);
            }

            return;
        }

        try {
            $inputStream = fopen($input, 'wb');
            if (! is_resource($inputStream)) {
                return;
            }
            stream_copy_to_stream($source, $inputStream);
            fclose($inputStream);
            fclose($source);

            $process = new Process([
                $binary,
                '-sDEVICE=pdfwrite',
                '-dCompatibilityLevel=1.4',
                '-dPDFSETTINGS=/ebook',
                '-dNOPAUSE',
                '-dQUIET',
                '-dBATCH',
                '-sOutputFile='.$output,
                $input,
            ]);
            $process->setTimeout(120);
            $process->run();
            if (! $process->isSuccessful() || ! is_file($output)) {
                return;
            }

            $compressedSize = (int) filesize($output);
            if ($compressedSize <= 0 || $compressedSize >= (int) $document->ukuran) {
                return;
            }

            $current = DataDiriDocument::query()->find($this->documentId);
            if (! $current || $current->path !== $this->expectedPath) {
                return;
            }

            $compressed = fopen($output, 'rb');
            if (! is_resource($compressed) || ! $disk->put($current->path, $compressed)) {
                if (is_resource($compressed)) {
                    fclose($compressed);
                }

                return;
            }
            fclose($compressed);

            $updated = DataDiriDocument::query()
                ->whereKey($this->documentId)
                ->where('path', $this->expectedPath)
                ->update([
                    'ukuran' => $compressedSize,
                    'hash' => (string) hash_file('sha256', $output),
                ]);

            if ($updated === 0) {
                $disk->delete($this->expectedPath);
            }
        } finally {
            if (is_resource($source)) {
                fclose($source);
            }
            @unlink($input);
            @unlink($output);
        }
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }
}
