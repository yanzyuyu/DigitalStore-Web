<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\User;

/**
 * Record-level authorisation for products.
 *
 * Route middleware answers "is this a seller?"; this policy answers "is this
 * *their* product?". Both are required — middleware alone would let any seller
 * edit any other seller's catalogue.
 */
class ProductPolicy
{
    /**
     * Admins can do anything; everyone else is scoped to their own shop.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(?User $user): bool
    {
        // The public catalogue is browsable without authentication.
        return true;
    }

    public function view(?User $user, Product $product): bool
    {
        if ($product->status === ProductStatus::Published) {
            return true;
        }

        return $user !== null && $this->owns($user, $product);
    }

    public function create(User $user): bool
    {
        // A seller may only publish once their shop has been approved.
        return $user->isSeller() && $user->hasActiveShop();
    }

    public function update(User $user, Product $product): bool
    {
        return $this->owns($user, $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->owns($user, $product);
    }

    /**
     * Managing the downloadable files attached to a product.
     */
    public function manageFiles(User $user, Product $product): bool
    {
        return $this->owns($user, $product);
    }

    /**
     * Moderation (approve/reject/archive) is admin-only, which `before()`
     * already grants; no seller may reach it.
     */
    public function moderate(User $user, Product $product): bool
    {
        return $user->isAdmin();
    }

    private function owns(User $user, Product $product): bool
    {
        return $user->isSeller()
            && $product->shop !== null
            && $product->shop->user_id === $user->id;
    }
}
