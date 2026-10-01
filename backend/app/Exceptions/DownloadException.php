<?php

declare(strict_types=1);

namespace App\Exceptions;

class DownloadException extends DomainException
{
    public static function notEntitled(): self
    {
        return new self(
            'Anda tidak memiliki akses untuk mengunduh file ini.',
            'download_not_entitled',
            403,
        );
    }

    public static function fileUnavailable(): self
    {
        return new self(
            'File tidak tersedia. Silakan hubungi penjual.',
            'download_file_unavailable',
            404,
        );
    }

    public static function revoked(): self
    {
        return new self(
            'Akses unduhan untuk pesanan ini telah dicabut.',
            'download_revoked',
            403,
        );
    }
}
