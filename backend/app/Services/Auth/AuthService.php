<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Shop\ShopService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(private readonly ShopService $shops) {}

    /**
     * Register a buyer or seller.
     *
     * A seller's shop is created in the same transaction as the account, so a
     * seller can never end up in the "role is seller but no shop exists" state
     * that would break every product endpoint.
     */
    public function register(array $data): User
    {
        // Re-checked here rather than relying on the FormRequest: this service
        // is also reachable from console commands, seeders and queued jobs,
        // and the database unique index alone would surface as a raw 500.
        if (User::withTrashed()->where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => ['Email sudah terdaftar.'],
            ]);
        }

        return DB::transaction(function () use ($data): User {
            $role = UserRole::from($data['role']);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                // Casting to `hashed` on the model means the plain value is
                // hashed exactly once, even if this line is reached twice.
                'password' => $data['password'],
                'role' => $role,
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
            ]);

            if ($role === UserRole::Seller) {
                $this->shops->createForUser($user, [
                    'name' => $data['shop_name'],
                    'description' => $data['shop_description'] ?? null,
                ]);
            }

            return $user->load('shop');
        });
    }

    /**
     * Verify credentials and issue a token.
     *
     * @throws ValidationException
     */
    public function login(string $email, string $password, ?string $deviceName = null, ?string $ip = null): array
    {
        /** @var User|null $user */
        $user = User::where('email', $email)->first();

        // Hash::check is run even when the user does not exist, against a dummy
        // hash, so the response time does not reveal whether an email is
        // registered (user enumeration).
        $hash = $user?->password ?? '$2y$12$0000000000000000000000000000000000000000000000000000';
        $valid = Hash::check($password, $hash);

        if ($user === null || ! $valid) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Akun Anda telah dinonaktifkan. Hubungi administrator.'],
            ]);
        }

        // One token per device; revoking the old one prevents an unbounded
        // pile-up of live tokens from repeated logins.
        $user->tokens()->where('name', $deviceName ?? 'web')->delete();

        $token = $user->createToken(
            $deviceName ?? 'web',
            ['*'],
            now()->addMinutes((int) config('sanctum.expiration', 10080)),
        );

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ])->save();

        return [
            'user' => $user->load('shop'),
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
        ];
    }

    public function logout(User $user): void
    {
        // Revoke only the token used for this request, so logging out of one
        // device does not sign the user out everywhere.
        $user->currentAccessToken()?->delete();
    }
}
