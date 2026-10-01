<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Http\Responses\ApiResponse;
use App\Services\Checkout\CheckoutService;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CheckoutService $checkout) {}

    /**
     * POST /api/v1/checkout
     *
     * The whole checkout runs inside a database transaction; a failure at any
     * point leaves no order, no payment and no burnt coupon behind.
     */
    public function store(CheckoutRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $order = $this->checkout->checkout(
            buyer: $request->user(),
            lines: $validated['items'],
            couponCode: $validated['coupon_code'] ?? null,
            customer: array_filter([
                'name' => $validated['customer_name'] ?? null,
                'email' => $validated['customer_email'] ?? null,
                'phone' => $validated['customer_phone'] ?? null,
            ], static fn ($value): bool => $value !== null),
            notes: $validated['notes'] ?? null,
        );

        return $this->created(
            new OrderResource($order->load(['items.shop', 'payments'])),
            'Pesanan berhasil dibuat. Silakan selesaikan pembayaran.',
        );
    }
}
