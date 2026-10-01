<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'reference' => 'PAY-'.Carbon::now()->format('Ymd').'-'.Str::upper(Str::random(10)),
            'external_id' => null,
            'method' => PaymentMethod::Manual,
            'status' => PaymentStatus::Pending,
            'amount' => fake()->numberBetween(10, 500) * 1000,
            'currency' => 'IDR',
            'proof_path' => null,
            'gateway_payload' => null,
            'gateway_status' => null,
            'failure_reason' => null,
            'expires_at' => now()->addDay(),
            'paid_at' => null,
            'verified_by' => null,
            'verified_at' => null,
        ];
    }

    /**
     * A manual payment whose receipt has been uploaded and is waiting for an
     * admin decision — the state the verification queue acts on.
     */
    public function awaitingVerification(): static
    {
        return $this->state(fn (array $attributes): array => [
            'method' => PaymentMethod::Manual,
            'status' => PaymentStatus::Pending,
            'proof_path' => 'payments/proofs/'.fake()->uuid().'.jpg',
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Failed,
            'failure_reason' => 'Bukti pembayaran tidak valid.',
        ]);
    }

    public function viaDuitku(): static
    {
        return $this->state(fn (array $attributes): array => [
            'method' => PaymentMethod::Duitku,
            'external_id' => (string) fake()->numerify('##########'),
        ]);
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes): array => [
            'order_id' => $order->id,
            'amount' => $order->grand_total,
        ]);
    }
}
