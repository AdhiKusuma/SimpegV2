<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProfileImageService
{
    /**
     * Normalize a profile image to a small JPEG before it is persisted.
     *
     * The original upload is never copied to the application storage. This
     * keeps profile photos predictable even when the source is a large PNG or
     * an animated GIF.
     */
    public function store(UploadedFile $file, string $directory, string $baseName): string
    {
        $sourcePath = $file->getRealPath();
        $info = $sourcePath ? @getimagesize($sourcePath) : false;
        if (! is_array($info) || ! isset($info[0], $info[1])) {
            throw ValidationException::withMessages(['foto' => 'Foto profil tidak dapat dibaca.']);
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        if ($width < 1 || $height < 1 || ($width * $height) > 20_000_000) {
            throw ValidationException::withMessages(['foto' => 'Dimensi foto terlalu besar untuk diproses.']);
        }

        $source = $this->openImage($sourcePath, (string) ($info['mime'] ?? ''));
        if (! $source) {
            throw ValidationException::withMessages(['foto' => 'Format foto tidak didukung oleh server.']);
        }

        $maxDimension = max(200, (int) config('uploads.profile_photo.max_dimension', 600));
        [$targetWidth, $targetHeight] = $this->fitDimensions($width, $height, $maxDimension);
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        if (! $canvas) {
            throw new RuntimeException('Canvas foto profil tidak dapat dibuat.');
        }

        // JPEG tidak menyimpan transparansi. Latar putih menjaga PNG/GIF tetap
        // terbaca dan menghindari area hitam pada foto dengan alpha channel.
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $directory = rtrim($directory, DIRECTORY_SEPARATOR);
        File::ensureDirectoryExists($directory, 0755);
        $filename = $baseName.'.jpg';
        $destination = $directory.DIRECTORY_SEPARATOR.$filename;
        $temporary = tempnam(sys_get_temp_dir(), 'simpeg-profile-');
        if ($temporary === false) {
            throw new RuntimeException('File sementara foto profil tidak dapat dibuat.');
        }

        try {
            $quality = max(55, min(90, (int) config('uploads.profile_photo.quality', 78)));
            $targetBytes = max(50 * 1024, (int) config('uploads.profile_photo.target_kb', 250) * 1024);
            $saved = false;

            for ($currentQuality = $quality; $currentQuality >= 55; $currentQuality -= 5) {
                if (! imagejpeg($canvas, $temporary, $currentQuality)) {
                    continue;
                }

                $saved = true;
                if ((int) filesize($temporary) <= $targetBytes || $currentQuality === 55) {
                    break;
                }
            }

            if (! $saved || ! is_file($temporary) || (int) filesize($temporary) <= 0) {
                throw new RuntimeException('Foto profil tidak dapat dioptimalkan.');
            }

            File::move($temporary, $destination);
        } finally {
            unset($canvas, $source);
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }

        return $filename;
    }

    /**
     * @return resource|false
     */
    private function openImage(string $path, string $mime): mixed
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/gif' => @imagecreatefromgif($path),
            default => false,
        };
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function fitDimensions(int $width, int $height, int $maxDimension): array
    {
        $scale = min(1, $maxDimension / max($width, $height));

        return [
            max(1, (int) round($width * $scale)),
            max(1, (int) round($height * $scale)),
        ];
    }
}
