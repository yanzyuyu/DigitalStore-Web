<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Creates the initial administrator.
 *
 * Admin accounts are never self-registerable, so this seeder is the only way
 * the first one comes into existence. Credentials come from config
 * (ADMIN_EMAIL / ADMIN_PASSWORD) rather than being hard-coded: shipping a
 * default admin password is one of the most common ways a marketplace is
 * compromised shortly after launch.
 */
class AdminUserSeeder extends BaseSeeder
{
    public function run(): void
    {
        $email = config('marketplace.admin.email');
        $password = config('marketplace.admin.password');

        if (blank($email) || blank($password)) {
            $this->warn('ADMIN_EMAIL / ADMIN_PASSWORD tidak diset — akun admin dilewati.');

            return;
        }

        if (strlen((string) $password) < 12) {
            $this->warn('ADMIN_PASSWORD minimal 12 karakter — akun admin dilewati.');

            return;
        }

        // withTrashed so an admin who was soft-deleted can be restored rather
        // than colliding with the unique index on email.
        $admin = User::withTrashed()->firstOrNew(['email' => $email]);

        $admin->fill([
            'name' => config('marketplace.admin.name', 'Administrator'),
            'email' => $email,
            'password' => $password,
            'role' => UserRole::Admin,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $admin->deleted_at = null;
        $admin->save();

        $this->info(sprintf('Akun admin siap: %s', $email));
    }
}
