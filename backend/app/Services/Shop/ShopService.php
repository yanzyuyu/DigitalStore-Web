<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\ShopStatus;
use App\Exceptions\DomainException;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShopService
{
    /**
     * Create the single shop belonging to a seller.
     */
    public function createForUser(User $user, array $attributes): Shop
    {
        if ($user->shop()->withTrashed()->exists()) {
            throw new DomainException(
                'Anda sudah memiliki toko.',
                'shop_already_exists',
                409,
            );
        }

        return DB::transaction(function () use ($user, $attributes): Shop {
            $shop = new Shop([
                'user_id' => $user->id,
                'name' => $attributes['name'],
                'slug' => $this->uniqueSlug($attributes['name']),
                'description' => $attributes['description'] ?? null,
                'bank_name' => $attributes['bank_name'] ?? null,
                'bank_account_number' => $attributes['bank_account_number'] ?? null,
                'bank_account_holder' => $attributes['bank_account_holder'] ?? null,
                // A new shop starts pending until an admin approves it.
                'status' => ShopStatus::Pending,
            ]);

            $shop->save();

            return $shop;
        });
    }

    public function update(Shop $shop, array $attributes): Shop
    {
        if (isset($attributes['name']) && $attributes['name'] !== $shop->name) {
            $shop->slug = $this->uniqueSlug($attributes['name'], $shop->id);
        }

        $shop->fill($attributes);
        $shop->save();

        return $shop;
    }

    public function storeLogo(Shop $shop, UploadedFile $file): Shop
    {
        $path = $file->store('shops/logos', 'public');

        $shop->logo_path = $path;
        $shop->save();

        return $shop;
    }

    public function storeBanner(Shop $shop, UploadedFile $file): Shop
    {
        $path = $file->store('shops/banners', 'public');

        $shop->banner_path = $path;
        $shop->save();

        return $shop;
    }

    /**
     * Approve a shop, allowing it to trade.
     */
    public function approve(Shop $shop, User $admin): Shop
    {
        $shop->status = ShopStatus::Active;
        $shop->verified_at = now();
        $shop->save();

        return $shop;
    }

    public function suspend(Shop $shop, User $admin): Shop
    {
        $shop->status = ShopStatus::Suspended;
        $shop->save();

        return $shop;
    }

    /**
     * Build a slug that is unique across live AND soft-deleted rows.
     *
     * The unique index does not exclude soft-deleted rows, so ignoring them
     * here would let a new shop collide with a deleted one and fail at insert.
     */
    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'toko';
        }

        $slug = $base;
        $suffix = 1;

        while (
            Shop::withTrashed()
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
