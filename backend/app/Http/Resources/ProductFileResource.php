<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ProductFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Seller-facing view of an attached file.
 *
 * `path` is intentionally omitted: even the owner does not need the raw
 * storage path, and leaving it out means it can never leak through a response
 * that was not meant to carry it. `download_url` is a signed, expiring route
 * that works for owners too.
 *
 * @mixin ProductFile
 */
class ProductFileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'version' => $this->version,
            'mime_type' => $this->mime_type,
            'size_bytes' => (int) $this->size_bytes,
            'human_size' => $this->humanSize(),
            'is_primary' => $this->is_primary,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
