<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Product;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public product representation.
 *
 * File paths are never included here — only in the seller's own management
 * endpoints via ProductFileResource — because the whole point of a private
 * disk is that the path is not discoverable.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'thumbnail_url' => MediaUrl::public($this->thumbnail_path),

            'price' => (float) $this->price,
            'compare_at_price' => $this->compare_at_price !== null
                ? (float) $this->compare_at_price
                : null,
            'has_discount' => $this->hasDiscount(),
            'discount_percentage' => $this->discountPercentage(),
            'formatted_price' => 'Rp '.number_format((float) $this->price, 0, ',', '.'),

            'status' => $this->status->value,
            'status_label' => $this->status->label(),

            'rating_average' => (float) $this->rating_average,
            'rating_count' => (int) $this->rating_count,
            'sales_count' => (int) $this->sales_count,
            'is_featured' => $this->is_featured,

            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            'category' => new CategoryResource($this->whenLoaded('category')),
            'shop' => new ShopResource($this->whenLoaded('shop')),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),

            // Files are metadata only, and only for someone allowed to manage
            // the product. `files` is loaded by the seller endpoints alone.
            'files' => ProductFileResource::collection($this->whenLoaded('files')),
        ];
    }
}
