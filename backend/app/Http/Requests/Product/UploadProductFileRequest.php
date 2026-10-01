<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class UploadProductFileRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:20'],
            'is_primary' => ['nullable', 'boolean'],

            // Deliberately NOT restricted to a mime whitelist: digital products
            // are legitimately diverse (zips, psds, epubs, fonts). Safety comes
            // from storing the file on a private disk and never serving it
            // inline or executing it, plus a size ceiling.
            'file' => ['required', 'file', 'max:204800'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => 'Ukuran file maksimal 200 MB.',
            'file.required' => 'File produk wajib diunggah.',
        ];
    }
}
