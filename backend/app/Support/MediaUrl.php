<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Centralises turning a stored path into a browser-usable URL.
 *
 * Keeping this in one place means a disk change (local -> S3) is a config
 * change, not a hunt through every resource.
 */
final class MediaUrl
{
    /**
     * URL for a file on a publicly-readable disk.
     */
    public static function public(?string $path, ?string $disk = 'public'): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return Storage::disk($disk ?? 'public')->url($path);
    }

    /**
     * Files on a private disk have no public URL by design; they are only
     * reachable through a signed, expiring route. Callers should return the
     * route instead of a storage URL.
     */
    public static function private(?string $path): ?string
    {
        return $path === null || $path === '' ? null : $path;
    }
}
