<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OrderDeliverableFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDeliverable extends Model
{
    /** @use HasFactory<OrderDeliverableFactory> */
    use HasFactory;

    /**
     * Download counters (download_count, last_downloaded_at) are deliberately
     * absent: they are incremented atomically by the download service only.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'order_item_id',
        'product_file_id',
        'buyer_id',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'download_count' => 'integer',
            'last_downloaded_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function productFile(): BelongsTo
    {
        return $this->belongsTo(ProductFile::class)->withTrashed();
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
