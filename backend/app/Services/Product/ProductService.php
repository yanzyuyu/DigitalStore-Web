<?php

declare(strict_types=1);

namespace App\Services\Product;

use App\Enums\ProductStatus;
use App\Exceptions\CheckoutException;
use App\Exceptions\DomainException;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\ProductImage;
use App\Models\Shop;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductService
{
    /**
     * Create a product for a shop.
     *
     * The product always starts as a draft: a seller submits it for review in
     * a separate step, and only an admin can publish it.
     */
    public function create(Shop $shop, array $attributes): Product
    {
        $this->assertProductLimit($shop);

        return DB::transaction(function () use ($shop, $attributes): Product {
            $product = new Product([
                'shop_id' => $shop->id,
                'category_id' => $attributes['category_id'] ?? null,
                'name' => $attributes['name'],
                'slug' => $this->uniqueSlug($attributes['name']),
                'short_description' => $attributes['short_description'] ?? null,
                'description' => $attributes['description'] ?? null,
                'price' => $attributes['price'],
                'compare_at_price' => $attributes['compare_at_price'] ?? null,
                'status' => ProductStatus::Draft,
                'meta' => $attributes['meta'] ?? null,
            ]);

            $product->save();

            return $product;
        });
    }

    public function update(Product $product, array $attributes): Product
    {
        if (isset($attributes['name']) && $attributes['name'] !== $product->name) {
            $product->slug = $this->uniqueSlug($attributes['name'], $product->id);
        }

        // A seller editing a published product sends it back for review rather
        // than silently changing what buyers already see.
        if (isset($attributes['status'])) {
            $product->status = ProductStatus::from($attributes['status']);
            unset($attributes['status']);
        }

        $product->fill($attributes);
        $product->save();

        return $product;
    }

    public function storeThumbnail(Product $product, UploadedFile $file): Product
    {
        $path = $file->store('products/thumbnails', 'public');

        $product->thumbnail_path = $path;
        $product->save();

        return $product;
    }

    public function storeImage(Product $product, UploadedFile $file, ?string $altText = null): ProductImage
    {
        $path = $file->store('products/images', 'public');

        return $product->images()->create([
            'path' => $path,
            'disk' => 'public',
            'alt_text' => $altText,
            'sort_order' => (int) $product->images()->max('sort_order') + 1,
            'is_primary' => ! $product->images()->exists(),
        ]);
    }

    /**
     * Attach a downloadable file to a product.
     *
     * Files go to the PRIVATE disk. That is the security boundary for the whole
     * marketplace: a digital product is only reachable through a signed route
     * that checks the buyer's entitlement.
     */
    public function storeFile(Product $product, UploadedFile $file, array $attributes = []): ProductFile
    {
        $disk = (string) config('marketplace.downloads.disk', 'private');

        $path = $file->store('products/files/'.$product->id, $disk);

        return DB::transaction(function () use ($product, $file, $path, $disk, $attributes): ProductFile {
            $isPrimary = (bool) ($attributes['is_primary'] ?? false);

            if ($isPrimary) {
                // Only one primary file per product.
                $product->files()->update(['is_primary' => false]);
            }

            return $product->files()->create([
                'name' => $attributes['name'] ?? $file->getClientOriginalName(),
                'path' => $path,
                'disk' => $disk,
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                // Detects corruption and duplicate uploads.
                'checksum' => hash_file('sha256', $file->getRealPath()) ?: null,
                'version' => $attributes['version'] ?? '1.0',
                'is_primary' => $isPrimary,
                'is_active' => true,
            ]);
        });
    }

    public function deleteFile(ProductFile $file): void
    {
        // Soft delete: an order may already reference this file, and the
        // entitlement ledger must keep resolving.
        $file->delete();
    }

    /**
     * Admin moderation.
     */
    public function changeStatus(Product $product, ProductStatus $status, ?string $reason = null): Product
    {
        if (! $product->status->canTransitionTo($status) && $product->status !== $status) {
            throw new DomainException(
                sprintf(
                    'Produk tidak dapat diubah dari "%s" ke "%s".',
                    $product->status->label(),
                    $status->label(),
                ),
                'invalid_product_transition',
                409,
            );
        }

        $product->status = $status;

        if ($status === ProductStatus::Published) {
            $product->published_at = now();
            $product->rejected_at = null;
            $product->rejection_reason = null;
        }

        if ($status === ProductStatus::Rejected) {
            $product->rejected_at = now();
            $product->rejection_reason = $reason;
        }

        $product->save();

        return $product;
    }

    /**
     * Soft-delete a product. Orders keep resolving through `withTrashed()`.
     */
    public function delete(Product $product): void
    {
        $product->delete();
    }

    private function assertProductLimit(Shop $shop): void
    {
        $limit = (int) config('marketplace.seller_max_products', 0);

        if ($limit > 0 && $shop->products()->count() >= $limit) {
            throw CheckoutException::sellerProductLimitReached($limit);
        }
    }

    /**
     * Unique across live and soft-deleted products, matching the unique index.
     */
    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'produk';
        }

        $slug = $base;
        $suffix = 1;

        while (
            Product::withTrashed()
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
