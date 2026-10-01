<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, Payment $payment): bool
    {
        return $payment->order->buyer_id === $user->id;
    }

    /**
     * Only the buyer who owns the order may upload its proof of payment.
     */
    public function uploadProof(User $user, Payment $payment): bool
    {
        return $payment->order->buyer_id === $user->id
            && $payment->isManual()
            && ! $payment->isSettled();
    }

    /**
     * Verifying or rejecting a manual transfer is an admin action; `before()`
     * grants it and denies everyone else.
     */
    public function verify(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }
}
