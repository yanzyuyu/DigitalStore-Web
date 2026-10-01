<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use App\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership of the target shop is enforced by ProductPolicy in the
        // controller, which knows which shop the request is for.
        return $this->user()?->isSeller() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:50000'],

            // numeric + min:0 prevents a negative price, which would turn a
            // purchase into a credit. max guards against overflow of the
            // DECIMAL(15,2) column.
            'price' => ['required', 'numeric', 'min:0', 'max:99999999999.99'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0', 'max:99999999999.99', 'gte:price'],

            // A seller may only create a draft or submit for review; publishing
            // is an admin decision.
            'status' => ['nullable', Rule::in([ProductStatus::Draft->value, ProductStatus::Pending->value])],

            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            'meta' => ['nullable', 'array'],
            'meta.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price.min' => 'Harga tidak boleh negatif.',
            'compare_at_price.gte' => 'Harga pembanding harus lebih besar atau sama dengan harga jual.',
            'images.max' => 'Maksimal 8 gambar per produk.',
        ];
    }
}
