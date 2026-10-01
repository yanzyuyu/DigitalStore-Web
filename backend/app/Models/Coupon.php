<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DiscountType;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * `used_count` is deliberately absent: it is a counter that must only be
     * incremented inside the checkout transaction.
     *
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'code',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'min_order_amount',
        'usage_limit',
        'usage_limit_per_user',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'usage_limit' => 'integer',
            'usage_limit_per_user' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * A platform-wide coupon has no owning shop.
     */
    public function isPlatformWide(): bool
    {
        return $this->shop_id === null;
    }

    /**
     * Whether the coupon may be redeemed right now, ignoring the basket.
     */
    public function isRedeemable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at !== null && $this->starts_at->isAfter($now)) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->isBefore($now)) {
            return false;
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    /**
     * Compute the discount for a given basket subtotal.
     *
     * The result is clamped so it can never exceed the subtotal, which would
     * otherwise produce a negative order total.
     */
    public function calculateDiscount(float $subtotal): float
    {
        if (! $this->isRedeemable()) {
            return 0.0;
        }

        if ($this->min_order_amount !== null && $subtotal < (float) $this->min_order_amount) {
            return 0.0;
        }

        $discount = match ($this->discount_type) {
            DiscountType::Fixed => (float) $this->discount_value,
            DiscountType::Percent => $subtotal * ((float) $this->discount_value / 100),
        };

        if ($this->max_discount_amount !== null) {
            $discount = min($discount, (float) $this->max_discount_amount);
        }

        return round(max(0.0, min($discount, $subtotal)), 2);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', strtoupper(trim($code)));
    }
}
