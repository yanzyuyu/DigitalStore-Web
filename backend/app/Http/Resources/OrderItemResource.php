<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrderItem;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        // The commission split is the platform's and the seller's business.
        // A buyer must never see it.
        $canSeeFinancials = $viewer !== null
            && ($viewer->isAdmin() || $this->shop?->user_id === $viewer->id);

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'product_slug' => $this->product_slug,
            'product_thumbnail_url' => MediaUrl::public($this->product_thumbnail_path),
            'quantity' => $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'discount_amount' => (float) $this->discount_amount,
            'line_total' => (float) $this->line_total,

            'shop' => $this->whenLoaded('shop', fn () => [
                'id' => $this->shop->id,
                'name' => $this->shop->name,
                'slug' => $this->shop->slug,
            ]),

            'commission_percent' => $this->when($canSeeFinancials, fn () => (float) $this->commission_percent),
            'commission_amount' => $this->when($canSeeFinancials, fn () => (float) $this->commission_amount),
            'seller_earning' => $this->when($canSeeFinancials, fn () => (float) $this->seller_earning),
        ];
    }
}
