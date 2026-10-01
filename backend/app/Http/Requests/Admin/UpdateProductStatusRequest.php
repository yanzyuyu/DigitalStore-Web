<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(ProductStatus::values())],
            // Required when rejecting so the seller knows what to fix; the
            // controller enforces the conditional.
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function wantsRejectionReason(): bool
    {
        return $this->input('status') === ProductStatus::Rejected->value;
    }
}
