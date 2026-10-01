<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrderDeliverable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A file the buyer is entitled to download.
 *
 * @mixin OrderDeliverable
 */
class OrderDeliverableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'download_count' => (int) $this->download_count,
            'last_downloaded_at' => $this->last_downloaded_at?->toIso8601String(),
            'is_active' => $this->is_active,

            'file' => $this->whenLoaded('productFile', fn () => [
                'id' => $this->productFile->id,
                'name' => $this->productFile->name,
                'version' => $this->productFile->version,
                'mime_type' => $this->productFile->mime_type,
                'human_size' => $this->productFile->humanSize(),
            ]),

            // The signed URL is generated per-request and expires, so it is
            // never a stable, shareable address.
            'download_url' => $this->when(
                $this->is_active,
                fn () => route('downloads.file', ['deliverable' => $this->id])
            ),
        ];
    }
}
