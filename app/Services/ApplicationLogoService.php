<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class ApplicationLogoService
{
    public function url(?string $filename): string
    {
        return $this->resolve($filename, 'images/logo-simpeg-256.png');
    }

    public function darkUrl(?string $filename): string
    {
        return $this->resolve($filename, 'images/logo-simpeg-256-dark.png');
    }

    private function resolve(?string $filename, string $fallbackPath): string
    {
        $fallback = asset($fallbackPath);

        if (! $filename || basename($filename) !== $filename) {
            return $fallback;
        }

        if (File::isFile(public_path('uploads/config/'.$filename))) {
            return asset('uploads/config/'.rawurlencode($filename));
        }

        return $fallback;
    }
}
