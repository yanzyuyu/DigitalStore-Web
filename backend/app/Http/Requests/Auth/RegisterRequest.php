<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],

            // dns check is deliberately omitted: it makes registration fail on
            // transient resolver problems and rejects valid internal domains.
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],

            'password' => [
                'required',
                'string',
                'confirmed',
                Password::defaults(),
            ],

            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],

            // Only buyer and seller may self-register. Admin accounts are
            // provisioned by a seeder or another admin, never from a request.
            'role' => ['required', 'string', 'in:'.UserRole::Buyer->value.','.UserRole::Seller->value],

            // Required only when registering as a seller; the shop is created
            // alongside the account so a seller is never left without a shop.
            'shop_name' => ['required_if:role,'.UserRole::Seller->value, 'nullable', 'string', 'min:3', 'max:255'],
            'shop_description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'email.unique' => 'Email sudah terdaftar.',
            'email.email' => 'Format email tidak valid.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'role.in' => 'Peran yang dipilih tidak valid.',
            'shop_name.required_if' => 'Nama toko wajib diisi untuk mendaftar sebagai penjual.',
            'phone.regex' => 'Format nomor telepon tidak valid.',
        ];
    }

    /**
     * Normalise input before validation so "  User@Example.COM " does not
     * create a duplicate account that bypasses the unique rule.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->email) ? strtolower(trim($this->email)) : $this->email,
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
        ]);
    }
}
