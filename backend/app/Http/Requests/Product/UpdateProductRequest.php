<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use App\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSeller() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:3', 'max:255'],
            'category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'short_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999999.99'],
            'compare_at_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999999.99'],

            // A seller may move between draft and pending, or archive their own
            // product. Publishing and rejecting remain admin-only actions.
            'status' => ['sometimes', Rule::in([
                ProductStatus::Draft->value,
                ProductStatus::Pending->value,
                ProductStatus::Archived->value,
            ])],

            'thumbnail' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'meta' => ['sometimes', 'nullable', 'array'],
            'meta.*' => ['nullable', 'string', 'max:500'],
        ];
    }
}
