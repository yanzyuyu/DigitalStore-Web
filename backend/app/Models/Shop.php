<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShopStatus;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'logo_path',
        'banner_path',
        'status',
        'bank_name',
        'bank_account_number',
        'bank_account_holder',
        'commission_percent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShopStatus::class,
            'balance' => 'decimal:2',
            'pending_balance' => 'decimal:2',
            'commission_percent' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Denormalised shop_id on order_items keeps the sales report cheap.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    // -----------------------------------------------------------------------
    // Behaviour
    // -----------------------------------------------------------------------

    public function isOperational(): bool
    {
        return $this->status === ShopStatus::Active
            && $this->trashed() === false
            && $this->user?->is_active === true;
    }

    /**
     * Commission is resolved in priority order: a per-shop override, then the
     * platform default. Stored per order-item so a later change never rewrites
     * historical orders.
     */
    public function effectiveCommissionPercent(): float
    {
        return (float) ($this->commission_percent
            ?? config('marketplace.commission_percent'));
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ShopStatus::Active->value);
    }
}
