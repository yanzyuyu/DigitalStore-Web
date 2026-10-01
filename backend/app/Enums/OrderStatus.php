<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Processing = 'processing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Menunggu Pembayaran',
            self::Paid => 'Dibayar',
            self::Processing => 'Diproses',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
            self::Expired => 'Kedaluwarsa',
            self::Refunded => 'Dana Dikembalikan',
        };
    }

    /**
     * Whether the buyer is entitled to download the purchased files.
     */
    public function grantsAccess(): bool
    {
        return in_array($this, [self::Paid, self::Processing, self::Completed], true);
    }

    /**
     * Terminal states can no longer change except through a refund.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Cancelled, self::Expired, self::Refunded], true);
    }

    /**
     * Whether payment can still be recorded against this order.
     */
    public function isAwaitingPayment(): bool
    {
        return $this === self::PendingPayment;
    }

    /**
     * Legal status transitions. Enforced by OrderStateMachine so that a stale
     * webhook can never move an order backwards (e.g. paid -> pending).
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Paid, self::Cancelled, self::Expired],
            self::Paid => [self::Processing, self::Completed, self::Refunded],
            self::Processing => [self::Completed, self::Refunded],
            self::Completed => [self::Refunded],
            self::Cancelled, self::Expired, self::Refunded => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
