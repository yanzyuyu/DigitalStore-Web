<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Shop;
use App\Models\User;

class ShopPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Shop $shop): bool
    {
        // Only an approved shop is publicly visible.
        if ($shop->status->isOperational()) {
            return true;
        }

        return $user !== null && $shop->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isSeller();
    }

    public function update(User $user, Shop $shop): bool
    {
        return $shop->user_id === $user->id;
    }

    /**
     * Suspending a shop is a platform decision, never a seller's.
     */
    public function moderate(User $user, Shop $shop): bool
    {
        return $user->isAdmin();
    }
}
