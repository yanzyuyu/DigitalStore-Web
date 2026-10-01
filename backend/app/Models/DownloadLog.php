<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DownloadLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit record. There is no `updated_at` because a log entry is
 * never modified after it is written.
 */
class DownloadLog extends Model
{
    /** @use HasFactory<DownloadLogFactory> */
    use HasFactory;

    /**
     * The table records only `downloaded_at`. Eloquent's default timestamp
     * handling would try to write created_at/updated_at and fail.
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_deliverable_id',
        'user_id',
        'product_file_id',
        'ip_address',
        'user_agent',
        'downloaded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'downloaded_at' => 'datetime',
        ];
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(OrderDeliverable::class, 'order_deliverable_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function productFile(): BelongsTo
    {
        return $this->belongsTo(ProductFile::class)->withTrashed();
    }
}
