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
            // Uploaded logos are public presentation assets. Serving them
            // directly avoids colliding with a server-level `/config` deny
            // rule while keeping the existing basename/path validation above.
            return asset('uploads/config/'.$filename);
        }

        return $fallback;
    }

}
