<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Published = 'published';
    case Rejected = 'rejected';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Pending => 'Menunggu Review',
            self::Published => 'Tayang',
            self::Rejected => 'Ditolak',
            self::Archived => 'Diarsipkan',
        };
    }

    /**
     * A product is only purchasable while published.
     */
    public function isPurchasable(): bool
    {
        return $this === self::Published;
    }

    /**
     * Transitions an admin is allowed to make from this state.
     *
     * A draft is included because a seller's product starts there and an admin
     * may publish it directly (or reject it) without requiring the seller to
     * submit it first. Without that edge, no draft could ever go live.
     *
     * @return array<int, self>
     */
    public function allowedAdminTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Pending, self::Published, self::Rejected, self::Archived],
            self::Pending => [self::Published, self::Rejected, self::Archived],
            self::Published => [self::Archived, self::Rejected],
            self::Rejected => [self::Published, self::Archived],
            self::Archived => [self::Published, self::Draft],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedAdminTransitions(), true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
