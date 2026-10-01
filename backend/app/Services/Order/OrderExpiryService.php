<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Expires unpaid orders.
 *
 * Without this, abandoned checkouts accumulate forever, sales counters stay
 * inflated, and coupons are held hostage by baskets nobody will ever pay for.
 * Run from the scheduler; each order is handled in its own transaction so one
 * failure cannot abort the whole sweep.
 */
class OrderExpiryService
{
    public function __construct(private readonly OrderStateMachine $stateMachine) {}

    /**
     * @return int the number of orders expired
     */
    public function expireOverdueOrders(int $limit = 500): int
    {
        $expired = 0;

        Order::query()
            ->expired()
            ->orderBy('expires_at')
            ->limit($limit)
            ->pluck('id')
            ->each(function (int $orderId) use (&$expired): void {
                if ($this->expireOrder($orderId)) {
                    $expired++;
                }
            });

        return $expired;
    }

    private function expireOrder(int $orderId): bool
    {
        return DB::transaction(function () use ($orderId): bool {
            // Re-check inside the lock: a payment webhook may have landed
            // between the query above and this transaction.
            $order = Order::query()->lockForUpdate()->find($orderId);

            if ($order === null || ! $order->isAwaitingPayment()) {
                return false;
            }

            $this->stateMachine->transition(
                $order,
                OrderStatus::Expired,
                'Pesanan kedaluwarsa karena tidak dibayar.',
            );

            // Close out any payment attempts so the UI stops offering to pay.
            $order->payments()
                ->where('status', PaymentStatus::Pending->value)
                ->update([
                    'status' => PaymentStatus::Expired->value,
                    'updated_at' => now(),
                ]);

            return true;
        }, attempts: 3);
    }
}
