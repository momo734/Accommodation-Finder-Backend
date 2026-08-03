<?php

namespace App\Support;

class MediaUrl
{
    public static function public(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim((string) config('app.url'), '/').'/storage/'.ltrim(str_replace('\\', '/', $path), '/');
    }
}
