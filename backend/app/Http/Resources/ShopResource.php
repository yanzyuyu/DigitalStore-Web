<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Shop;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Shop
 */
class ShopResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Bank details are only ever shown to the shop's own owner or an admin.
        // Exposing them publicly would invite fraud against buyers.
        $viewer = $request->user();
        $canSeeBankDetails = $viewer !== null
            && ($viewer->isAdmin() || $this->user_id === $viewer->id);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'logo_url' => MediaUrl::public($this->logo_path),
            'banner_url' => MediaUrl::public($this->banner_path),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_operational' => $this->isOperational(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            'products_count' => $this->whenCounted('products'),

            'bank' => $this->when($canSeeBankDetails, fn () => [
                'name' => $this->bank_name,
                'account_number' => $this->bank_account_number,
                'account_holder' => $this->bank_account_holder,
            ]),

            // Balances are financial data: owner or admin only.
            'balance' => $this->when($canSeeBankDetails, fn () => (float) $this->balance),
            'pending_balance' => $this->when($canSeeBankDetails, fn () => (float) $this->pending_balance),
            'commission_percent' => $this->when(
                $canSeeBankDetails,
                fn () => $this->commission_percent !== null
                    ? (float) $this->commission_percent
                    : null
            ),

            'owner' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
        ];
    }
}
