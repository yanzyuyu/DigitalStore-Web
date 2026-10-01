<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Exceptions\DomainException;
use App\Models\Order;
use Illuminate\Support\Carbon;

/**
 * The single authority on order status changes.
 *
 * Routing every transition through here is what makes webhooks safe: a
 * payment provider may deliver the same "settlement" event twice, or deliver
 * an old event after a newer one, and neither can corrupt the order. The
 * transition table in OrderStatus rejects anything that is not a legal move.
 */
class OrderStateMachine
{
    /**
     * Move an order to a new status, applying the matching timestamps.
     *
     * Re-applying the current status is a no-op, which is what makes webhook
     * retries idempotent.
     *
     * @throws DomainException when the transition is not permitted
     */
    public function transition(Order $order, OrderStatus $to, ?string $reason = null): Order
    {
        $from = $order->status;

        if ($from === $to) {
            return $order;
        }

        if (! $from->canTransitionTo($to)) {
            throw new DomainException(
                sprintf(
                    'Transisi status pesanan dari "%s" ke "%s" tidak diizinkan.',
                    $from->value,
                    $to->value,
                ),
                'invalid_order_transition',
                409,
            );
        }

        $order->status = $to;

        $now = Carbon::now();

        match ($to) {
            OrderStatus::Paid => $order->paid_at = $now,
            OrderStatus::Completed => $order->completed_at = $now,
            OrderStatus::Cancelled, OrderStatus::Expired => $order->cancelled_at = $now,
            default => null,
        };

        if ($reason !== null) {
            $order->cancel_reason = $reason;
        }

        $order->save();

        return $order;
    }

    /**
     * Whether the order can move to the given status right now.
     */
    public function canTransition(Order $order, OrderStatus $to): bool
    {
        return $order->status === $to || $order->status->canTransitionTo($to);
    }
}
