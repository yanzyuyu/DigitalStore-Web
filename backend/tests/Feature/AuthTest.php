<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ShopStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_buyer_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'buyer',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role', 'buyer')
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $this->assertDatabaseHas('users', [
            'email' => 'budi@example.com',
            'role' => UserRole::Buyer->value,
        ]);
    }

    public function test_registering_as_a_seller_creates_a_pending_shop(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Siti Penjual',
            'email' => 'siti@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'seller',
            'shop_name' => 'Toko Siti',
        ])->assertCreated();

        $seller = User::where('email', 'siti@example.com')->firstOrFail();

        $this->assertSame(UserRole::Seller, $seller->role);
        $this->assertDatabaseHas('shops', [
            'user_id' => $seller->id,
            'name' => 'Toko Siti',
            'status' => ShopStatus::Pending->value,
        ]);
    }

    public function test_a_seller_cannot_register_without_a_shop_name(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Tanpa Toko',
            'email' => 'notoko@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'seller',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('shop_name');
    }

    public function test_registration_cannot_self_assign_the_admin_role(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Penyusup',
            'email' => 'attacker@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'attacker@example.com']);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Duplikat',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'buyer',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_password_confirmation_must_match(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi',
            'email' => 'budi2@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
            'role' => 'buyer',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_a_user_can_log_in_and_receive_a_token(): void
    {
        User::factory()->create([
            'email' => 'budi@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'budi@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_login_fails_with_a_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'budi@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'budi@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    public function test_a_suspended_user_cannot_log_in(): void
    {
        User::factory()->suspended()->create([
            'email' => 'suspended@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'suspended@example.com',
            'password' => 'password123',
        ])->assertUnprocessable();
    }

    public function test_an_authenticated_user_can_fetch_their_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'unauthenticated');
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_the_response_never_exposes_the_password_hash(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/auth/me');

        $response->assertOk();
        $this->assertArrayNotHasKey('password', $response->json('data'));
    }
}
