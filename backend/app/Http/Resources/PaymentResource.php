<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $isStaff = $viewer !== null && $viewer->isAdmin();

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'external_id' => $this->external_id,
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'amount' => (float) $this->amount,
            'currency' => $this->currency,

            // The buyer needs to know whether their proof was received.
            'has_proof' => $this->hasProof(),
            'is_awaiting_review' => $this->isAwaitingAdminReview(),

            'paid_at' => $this->paid_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            // The raw gateway payload and internal notes are operational data,
            // not buyer-facing.
            'gateway_status' => $this->when($isStaff, fn () => $this->gateway_status),
            'failure_reason' => $this->when(
                $isStaff || $this->failure_reason !== null,
                fn () => $this->failure_reason
            ),
            'admin_note' => $this->when($isStaff, fn () => $this->admin_note),
            'verified_by' => $this->when($isStaff, fn () => $this->verified_by),
        ];
    }
}
