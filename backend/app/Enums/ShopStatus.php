<?php

declare(strict_types=1);

namespace App\Enums;

enum ShopStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Verifikasi',
            self::Active => 'Aktif',
            self::Suspended => 'Ditangguhkan',
        };
    }

    /**
     * Only an active shop may publish products and receive orders.
     */
    public function isOperational(): bool
    {
        return $this === self::Active;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
