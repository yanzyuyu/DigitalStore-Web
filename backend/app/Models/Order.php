<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Totals are never mass-assignable: they are computed by the checkout
     * service and must not be settable from request input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'invoice_number',
        'buyer_id',
        'coupon_id',
        'status',
        'currency',
        'customer_name',
        'customer_email',
        'customer_phone',
        'notes',
        'cancel_reason',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'commission_total' => 'decimal:2',
            'seller_net_total' => 'decimal:2',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(OrderDeliverable::class);
    }

    /**
     * The most recent payment attempt.
     */
    public function latestPayment(): ?Payment
    {
        return $this->payments()->latest('id')->first();
    }

    // -----------------------------------------------------------------------
    // Behaviour
    // -----------------------------------------------------------------------

    public function isAwaitingPayment(): bool
    {
        return $this->status->isAwaitingPayment();
    }

    public function grantsDownloadAccess(): bool
    {
        return $this->status->grantsAccess();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null
            && $this->expires_at->isPast()
            && $this->isAwaitingPayment();
    }

    public function formattedTotal(): string
    {
        return 'Rp '.number_format((float) $this->grand_total, 0, ',', '.');
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    public function scopeWithStatus(Builder $query, OrderStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function scopeForBuyer(Builder $query, int $buyerId): Builder
    {
        return $query->where('buyer_id', $buyerId);
    }

    /**
     * Pending orders past their deadline, used by the expiry sweeper.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query
            ->where('status', OrderStatus::PendingPayment->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }
}
