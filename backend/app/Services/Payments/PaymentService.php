<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Order\DeliverableService;
use App\Services\Order\OrderStateMachine;
use App\Support\Payments\WebhookResult;
use Illuminate\Support\Facades\DB;

/**
 * Applies payment outcomes to orders.
 *
 * This is the only place an order is marked paid. Both the webhook path and
 * the manual admin-verification path funnel through settle(), so the
 * entitlement grant, the seller balance credit and the order transition can
 * never diverge between the two.
 *
 * Transaction discipline: every public method opens exactly one transaction
 * and holds a row lock on the payment for its duration. The private settle()
 * deliberately does NOT open one, because it must run inside the caller's lock.
 */
class PaymentService
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
        private readonly DeliverableService $deliverables,
    ) {}

    /**
     * Record that a buyer has uploaded proof of a manual transfer.
     *
     * The payment stays pending; only an admin can settle it.
     */
    public function submitProof(Payment $payment, string $proofPath): Payment
    {
        if (! $payment->isManual()) {
            throw PaymentException::paymentNotReviewable();
        }

        if ($payment->isSettled()) {
            throw PaymentException::alreadyPaid($payment->order->invoice_number);
        }

        // A second upload before the first is reviewed would let a buyer swap
        // the evidence out from under the admin who is looking at it.
        if ($payment->hasProof()) {
            throw PaymentException::proofAlreadySubmitted();
        }

        $payment->proof_path = $proofPath;
        $payment->save();

        return $payment;
    }

    /**
     * An admin confirms a manual transfer.
     */
    public function verifyManually(Payment $payment, User $admin, ?string $note = null): Payment
    {
        if (! $payment->isManual()) {
            throw PaymentException::paymentNotReviewable();
        }

        if (! $payment->hasProof()) {
            throw new PaymentException('Bukti pembayaran belum diunggah.', 'proof_missing');
        }

        return $this->settleWithinLock($payment, function (Payment $locked) use ($admin, $note): Payment {
            if ($locked->isSettled()) {
                throw PaymentException::alreadyPaid($locked->order->invoice_number);
            }

            $locked->verified_by = $admin->id;
            $locked->verified_at = now();
            $locked->admin_note = $note;
            $locked->save();

            return $this->settle($locked, PaymentStatus::Paid);
        });
    }

    /**
     * An admin rejects a manual transfer.
     */
    public function rejectManually(Payment $payment, User $admin, string $reason): Payment
    {
        if (! $payment->isManual()) {
            throw PaymentException::paymentNotReviewable();
        }

        return $this->settleWithinLock($payment, function (Payment $locked) use ($admin, $reason): Payment {
            if ($locked->isSettled()) {
                throw PaymentException::paymentNotReviewable();
            }

            $locked->verified_by = $admin->id;
            $locked->verified_at = now();
            $locked->admin_note = $reason;
            $locked->failure_reason = $reason;
            $locked->save();

            return $this->settle($locked, PaymentStatus::Failed);
        });
    }

    /**
     * Apply a verified gateway webhook.
     *
     * Safe against duplicate delivery: a repeated "paid" event finds the
     * payment already settled and returns without side effects.
     */
    public function applyWebhook(WebhookResult $result): Payment
    {
        return DB::transaction(function () use ($result): Payment {
            $payment = Payment::query()
                ->where('reference', $result->reference)
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                throw new PaymentException(
                    sprintf('Referensi pembayaran "%s" tidak ditemukan.', $result->reference),
                    'payment_not_found',
                    404,
                );
            }

            // Guard before mutating anything: a validly-signed event for a
            // smaller amount must not settle a larger order.
            $this->assertAmountMatches($payment, $result->amount);

            $payment->external_id = $result->externalId ?? $payment->external_id;
            $payment->gateway_payload = $result->payload;
            $payment->gateway_status = $result->status->value;

            if ($result->failureReason !== null) {
                $payment->failure_reason = $result->failureReason;
            }

            $payment->save();

            return $this->settle($payment, $result->status);
        }, attempts: 3);
    }

    /**
     * Run a callback with the payment row locked inside a transaction, then
     * return the freshly reloaded payment.
     *
     * @param  callable(Payment): Payment  $callback
     */
    private function settleWithinLock(Payment $payment, callable $callback): Payment
    {
        return DB::transaction(function () use ($payment, $callback): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            return $callback($locked)->refresh();
        }, attempts: 3);
    }

    /**
     * The single settlement path.
     *
     * MUST be called inside a transaction that already holds a lock on
     * $payment. Idempotent: a payment that is already settled is returned
     * untouched, which is what makes a duplicate webhook a no-op.
     */
    private function settle(Payment $payment, PaymentStatus $status): Payment
    {
        // Only these movements are meaningful. Anything else — most importantly
        // a duplicate delivery of an event we have already applied, or a stale
        // "paid" event arriving after a failure — is a silent no-op.
        //
        // Note that a plain "already settled" check would be wrong here: Paid
        // is itself a settled state, yet Paid -> Refunded is a legitimate
        // transition that must still be applied.
        $allowed = match ($status) {
            PaymentStatus::Paid => $payment->status === PaymentStatus::Pending,
            PaymentStatus::Refunded => $payment->status === PaymentStatus::Paid,
            PaymentStatus::Failed, PaymentStatus::Expired => $payment->status === PaymentStatus::Pending,
            PaymentStatus::Pending => false,
        };

        if (! $allowed) {
            return $payment;
        }

        $payment->status = $status;

        if ($status === PaymentStatus::Paid) {
            $payment->paid_at = now();
        }

        $payment->save();

        $order = $payment->order()->lockForUpdate()->firstOrFail();

        match ($status) {
            PaymentStatus::Paid => $this->settleOrder($order),
            PaymentStatus::Failed => $this->stateMachine->transition(
                $order,
                OrderStatus::Cancelled,
                $payment->failure_reason ?? 'Pembayaran gagal.',
            ),
            PaymentStatus::Expired => $this->stateMachine->transition(
                $order,
                OrderStatus::Expired,
                'Pembayaran kedaluwarsa.',
            ),
            PaymentStatus::Refunded => $this->refundOrder($order),
            PaymentStatus::Pending => null,
        };

        return $payment;
    }

    /**
     * Mark the order paid, release the download entitlements and credit the
     * sellers. All three happen together or not at all.
     */
    private function settleOrder(Order $order): void
    {
        // A late or out-of-order event for an already-advanced order is not an
        // error worth failing the webhook over: the money is recorded, the
        // state is left alone.
        if (! $order->status->isAwaitingPayment()) {
            return;
        }

        $this->stateMachine->transition($order, OrderStatus::Paid);

        $this->deliverables->grantForOrder($order);

        $this->creditSellers($order);
    }

    /**
     * Move each seller's share into their pending balance.
     */
    private function creditSellers(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items->groupBy('shop_id') as $shopId => $items) {
            $earning = round((float) $items->sum(
                static fn ($item): float => (float) $item->seller_earning
            ), 2);

            if ($earning <= 0) {
                continue;
            }

            DB::table('shops')
                ->where('id', $shopId)
                ->update([
                    // Increment in SQL so concurrent settlements cannot lose an
                    // update through read-modify-write. The interpolated value
                    // is a float we formatted ourselves, never user input.
                    'pending_balance' => DB::raw('pending_balance + '.number_format($earning, 2, '.', '')),
                    'updated_at' => now(),
                ]);
        }
    }

    private function refundOrder(Order $order): void
    {
        $this->stateMachine->transition($order, OrderStatus::Refunded, 'Dana dikembalikan.');

        // Revoke access: a refunded buyer must not keep downloading.
        $this->deliverables->revokeForOrder($order);
    }

    /**
     * Reject a webhook whose amount does not match what we charged.
     */
    private function assertAmountMatches(Payment $payment, ?float $amount): void
    {
        if ($amount === null) {
            return;
        }

        // Compare in cents to sidestep float equality entirely.
        $expected = (int) round((float) $payment->amount * 100);
        $actual = (int) round($amount * 100);

        if ($expected !== $actual) {
            throw PaymentException::amountMismatch(
                number_format($expected / 100, 2, '.', ''),
                number_format($actual / 100, 2, '.', ''),
            );
        }
    }
}
