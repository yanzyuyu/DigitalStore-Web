<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * A buyer sees their own order; a seller sees an order only if it contains
     * one of their products. A seller must never see the buyer's other items
     * from a different shop, nor the platform commission on them.
     */
    public function view(User $user, Order $order): bool
    {
        if ($order->buyer_id === $user->id) {
            return true;
        }

        if (! $user->isSeller()) {
            return false;
        }

        return $order->items()
            ->where('shop_id', $user->shop?->id)
            ->exists();
    }

    /**
     * Downloading purchased files.
     */
    public function download(User $user, Order $order): bool
    {
        return $order->buyer_id === $user->id && $order->grantsDownloadAccess();
    }

    /**
     * Uploading a proof of transfer for the order.
     */
    public function pay(User $user, Order $order): bool
    {
        return $order->buyer_id === $user->id && $order->isAwaitingPayment();
    }

    public function cancel(User $user, Order $order): bool
    {
        return $order->buyer_id === $user->id && $order->isAwaitingPayment();
    }
}
