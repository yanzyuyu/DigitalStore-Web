<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Counters and aggregates (sales_count, rating_average, ...) are
     * deliberately absent: they are derived values that must only move through
     * the service layer, inside a transaction, never via mass assignment.
     *
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'category_id',
        'name',
        'slug',
        'short_description',
        'description',
        'thumbnail_path',
        'price',
        'compare_at_price',
        'status',
        'is_featured',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'rating_average' => 'decimal:2',
            'is_featured' => 'boolean',
            'meta' => 'array',
            'published_at' => 'datetime',
            'rejected_at' => 'datetime',
            'sales_count' => 'integer',
            'view_count' => 'integer',
            'download_count' => 'integer',
        ];
    }

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProductFile::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    // -----------------------------------------------------------------------
    // Behaviour
    // -----------------------------------------------------------------------

    public function isPurchasable(): bool
    {
        return $this->status === ProductStatus::Published
            && $this->trashed() === false;
    }

    public function hasDiscount(): bool
    {
        return $this->compare_at_price !== null
            && (float) $this->compare_at_price > (float) $this->price;
    }

    /**
     * Discount as a whole percentage, used for the "20% off" badge.
     */
    public function discountPercentage(): int
    {
        if (! $this->hasDiscount()) {
            return 0;
        }

        $original = (float) $this->compare_at_price;

        return $original > 0
            ? (int) round((($original - (float) $this->price) / $original) * 100)
            : 0;
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Published->value);
    }

    public function scopeForShop(Builder $query, int $shopId): Builder
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeInPriceRange(Builder $query, ?float $min, ?float $max): Builder
    {
        return $query
            ->when($min !== null, fn (Builder $q) => $q->where('price', '>=', $min))
            ->when($max !== null, fn (Builder $q) => $q->where('price', '<=', $max));
    }

    /**
     * Search the catalogue.
     *
     * MySQL gets a real FULLTEXT index (boolean mode so partial words match).
     * Other drivers — notably the SQLite database used by the test suite —
     * fall back to a LIKE scan, which keeps the query portable while the
     * production index does the heavy lifting.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        if ($query->getConnection()->getDriverName() === 'mysql') {
            // Strip boolean-mode operators so user input cannot alter the query
            // shape, then require every word to be present.
            $sanitised = preg_replace('/[+\-><()~*"@]+/', ' ', $term);
            $words = preg_split('/\s+/', trim((string) $sanitised), -1, PREG_SPLIT_NO_EMPTY);

            if ($words === []) {
                return $query;
            }

            $expression = implode(' ', array_map(
                static fn (string $word): string => '+'.$word.'*',
                $words
            ));

            return $query->whereRaw(
                'MATCH(name, short_description) AGAINST (? IN BOOLEAN MODE)',
                [$expression]
            );
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('name', 'like', $like)
                ->orWhere('short_description', 'like', $like);
        });
    }

    public function scopeBestSelling(Builder $query): Builder
    {
        return $query->orderByDesc('sales_count');
    }
}
