<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Seller = 'seller';
    case Buyer = 'buyer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Seller => 'Penjual',
            self::Buyer => 'Pembeli',
        };
    }

    /**
     * Roles that are allowed to operate a shop.
     */
    public function canSell(): bool
    {
        return $this === self::Seller || $this === self::Admin;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
