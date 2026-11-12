<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportPreviewService
{
    private const TTL_MINUTES = 20;

    /**
     * Persist the uploaded source file and bind it to the current session/user.
     * The browser only receives the opaque token; it never becomes the source of
     * truth for the commit step.
     */
    public function create(Request $request, string $sessionKey, string $directory, UploadedFile $file, array $metadata = []): string
    {
        $this->clean($request, $sessionKey);

        $token = (string) Str::uuid();
        $path = $file->storeAs($directory, $token.'.xlsx', 'local');

        if (! is_string($path) || $path === '') {
            throw new \RuntimeException('File preview import tidak dapat disimpan.');
        }

        $previews = $request->session()->get($sessionKey, []);
        $previews[$token] = [
            'path' => $path,
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'filename' => basename($file->getClientOriginalName()),
            'user_id' => (string) $request->session()->get('id_user'),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES)->timestamp,
            ...$metadata,
        ];

        while (count($previews) > 3) {
            $oldToken = array_key_first($previews);
            Storage::disk('local')->delete($previews[$oldToken]['path'] ?? '');
            unset($previews[$oldToken]);
        }

        $request->session()->put($sessionKey, $previews);

        return $token;
    }

    public function resolve(Request $request, string $sessionKey, string $token): ?array
    {
        $this->clean($request, $sessionKey);
        $previews = $request->session()->get($sessionKey, []);
        $preview = is_array($previews) ? ($previews[$token] ?? null) : null;

        if (! is_array($preview)
            || (string) ($preview['user_id'] ?? '') !== (string) $request->session()->get('id_user')
            || (int) ($preview['expires_at'] ?? 0) < now()->timestamp
        ) {
            return null;
        }

        $path = (string) ($preview['path'] ?? '');
        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $fullPath = Storage::disk('local')->path($path);
        if (! is_file($fullPath) || ! hash_equals((string) ($preview['sha256'] ?? ''), (string) hash_file('sha256', $fullPath))) {
            return null;
        }

        return $preview + ['token' => $token, 'full_path' => $fullPath];
    }

    public function consume(Request $request, string $sessionKey, string $token): void
    {
        $previews = $request->session()->get($sessionKey, []);
        $path = $previews[$token]['path'] ?? null;
        if (is_string($path)) {
            Storage::disk('local')->delete($path);
        }
        unset($previews[$token]);
        $request->session()->put($sessionKey, $previews);
    }

    public function clean(Request $request, string $sessionKey): void
    {
        $previews = $request->session()->get($sessionKey, []);
        if (! is_array($previews)) {
            $request->session()->forget($sessionKey);

            return;
        }

        foreach ($previews as $token => $preview) {
            if (! is_array($preview) || (int) ($preview['expires_at'] ?? 0) < now()->timestamp) {
                Storage::disk('local')->delete(is_array($preview) ? ($preview['path'] ?? '') : '');
                unset($previews[$token]);
            }
        }

        $request->session()->put($sessionKey, $previews);
    }
}
