<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Manual = 'manual';
    case Duitku = 'duitku';
    case Midtrans = 'midtrans';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Transfer Manual',
            self::Duitku => 'Duitku',
            self::Midtrans => 'Midtrans',
        };
    }

    /**
     * Manual payments are confirmed by an admin, not by a webhook.
     */
    public function isManual(): bool
    {
        return $this === self::Manual;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
