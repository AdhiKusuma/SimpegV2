<?php

namespace App\Services;

use App\Jobs\CompressDataDiriDocument;
use App\Models\DataDiriDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DataDiriDocumentService
{
    private const MAX_BYTES = 2_097_152;

    public function store(string $employeeId, string $type, UploadedFile $file, string $uploaderId): DataDiriDocument
    {
        $this->assertPdf($file);

        $disk = Storage::disk($this->diskName());
        $path = sprintf('data-diri/%s/%s/%s.pdf', trim($employeeId), $type, (string) Str::uuid());
        $stored = $disk->putFileAs(dirname($path), $file, basename($path));

        if (! $stored) {
            throw ValidationException::withMessages(['file_pdf' => 'Dokumen tidak dapat disimpan ke storage private.']);
        }

        $oldPath = null;
        try {
            [$document, $oldPath] = DB::transaction(function () use ($employeeId, $type, $file, $uploaderId, $path): array {
                $existing = DataDiriDocument::query()
                    ->where('pegawai_id', $employeeId)
                    ->where('jenis', $type)
                    ->lockForUpdate()
                    ->first();
                $oldPath = $existing?->path;
                $attributes = [
                    'nama_asli' => $file->getClientOriginalName(),
                    'path' => $path,
                    'ukuran' => (int) $file->getSize(),
                    'hash' => (string) hash_file('sha256', $file->getRealPath()),
                    'diunggah_oleh' => $uploaderId !== '' ? $uploaderId : null,
                ];

                if ($existing) {
                    $existing->fill($attributes);
                    $existing->save();
                    $document = $existing;
                } else {
                    $document = DataDiriDocument::query()->create([
                        'pegawai_id' => $employeeId,
                        'jenis' => $type,
                        ...$attributes,
                    ]);
                }

                return [$document, $oldPath];
            });
        } catch (Throwable $exception) {
            $disk->delete($path);
            throw $exception;
        }

        if ($oldPath !== null && $oldPath !== $path) {
            $this->deleteQuietly($oldPath);
        }

        try {
            CompressDataDiriDocument::dispatch((int) $document->id, $path);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $document->fresh();
    }

    public function delete(DataDiriDocument $document): void
    {
        $path = $document->path;
        DB::transaction(fn (): bool => (bool) $document->delete());
        $this->deleteQuietly($path);
    }

    public function diskName(): string
    {
        return (string) config('filesystems.default', 'local');
    }

    private function assertPdf(UploadedFile $file): void
    {
        if ((int) $file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages(['file_pdf' => 'Ukuran PDF maksimal 2 MB.']);
        }

        $realPath = $file->getRealPath();
        $mime = $realPath ? (new \finfo(FILEINFO_MIME_TYPE))->file($realPath) : false;
        $signature = $realPath ? file_get_contents($realPath, false, null, 0, 5) : false;
        if ($mime !== 'application/pdf' || $signature !== '%PDF-') {
            throw ValidationException::withMessages(['file_pdf' => 'File harus berupa PDF yang valid.']);
        }
    }

    private function deleteQuietly(string $path): void
    {
        try {
            Storage::disk($this->diskName())->delete($path);
        } catch (Throwable $exception) {
            Log::warning('Gagal menghapus dokumen data diri lama.', ['path' => $path, 'error' => $exception->getMessage()]);
        }
    }
}
