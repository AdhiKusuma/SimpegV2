<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class ApplicationLogoService
{
    public function url(?string $filename): string
    {
        $fallback = asset('images/logo-simpeg-256.png');

        if (! $filename || basename($filename) !== $filename) {
            return $fallback;
        }

        if (File::isFile(public_path('uploads/config/'.$filename))) {
            return asset('uploads/config/'.rawurlencode($filename));
        }

        return $fallback;
    }
}
