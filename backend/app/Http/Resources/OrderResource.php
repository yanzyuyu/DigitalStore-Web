<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $isAdmin = $viewer !== null && $viewer->isAdmin();
        $isBuyer = $viewer !== null && $this->buyer_id === $viewer->id;

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'currency' => $this->currency,

            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'tax_total' => (float) $this->tax_total,
            'grand_total' => (float) $this->grand_total,
            'formatted_grand_total' => $this->formattedTotal(),

            'customer_name' => $this->customer_name,
            'customer_email' => $this->when($isBuyer || $isAdmin, fn () => $this->customer_email),
            'customer_phone' => $this->when($isBuyer || $isAdmin, fn () => $this->customer_phone),
            'notes' => $this->when($isBuyer || $isAdmin, fn () => $this->notes),

            'can_download' => $this->grantsDownloadAccess(),
            'is_awaiting_payment' => $this->isAwaitingPayment(),

            'expires_at' => $this->expires_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            // Platform financials: admin only.
            'commission_total' => $this->when($isAdmin, fn () => (float) $this->commission_total),
            'seller_net_total' => $this->when($isAdmin, fn () => (float) $this->seller_net_total),

            'buyer' => new UserResource($this->whenLoaded('buyer')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'latest_payment' => $this->when(
                $this->relationLoaded('payments'),
                fn () => $this->payments->sortByDesc('id')->first() !== null
                    ? new PaymentResource($this->payments->sortByDesc('id')->first())
                    : null
            ),
        ];
    }
}
