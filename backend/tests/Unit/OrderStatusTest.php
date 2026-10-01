<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    public function test_a_pending_order_can_be_paid(): void
    {
        $this->assertTrue(OrderStatus::PendingPayment->canTransitionTo(OrderStatus::Paid));
    }

    public function test_a_pending_order_can_be_cancelled_or_expired(): void
    {
        $this->assertTrue(OrderStatus::PendingPayment->canTransitionTo(OrderStatus::Cancelled));
        $this->assertTrue(OrderStatus::PendingPayment->canTransitionTo(OrderStatus::Expired));
    }

    public function test_a_paid_order_can_be_completed_or_refunded(): void
    {
        $this->assertTrue(OrderStatus::Paid->canTransitionTo(OrderStatus::Completed));
        $this->assertTrue(OrderStatus::Paid->canTransitionTo(OrderStatus::Refunded));
    }

    public function test_a_cancelled_order_cannot_be_paid(): void
    {
        // This is the transition a stale webhook would attempt.
        $this->assertFalse(OrderStatus::Cancelled->canTransitionTo(OrderStatus::Paid));
    }

    public function test_an_expired_order_cannot_be_paid(): void
    {
        $this->assertFalse(OrderStatus::Expired->canTransitionTo(OrderStatus::Paid));
    }

    public function test_a_completed_order_cannot_go_back_to_pending(): void
    {
        $this->assertFalse(OrderStatus::Completed->canTransitionTo(OrderStatus::PendingPayment));
    }

    public function test_only_settled_statuses_grant_download_access(): void
    {
        $this->assertTrue(OrderStatus::Paid->grantsAccess());
        $this->assertTrue(OrderStatus::Completed->grantsAccess());
        $this->assertTrue(OrderStatus::Processing->grantsAccess());

        $this->assertFalse(OrderStatus::PendingPayment->grantsAccess());
        $this->assertFalse(OrderStatus::Cancelled->grantsAccess());
        $this->assertFalse(OrderStatus::Expired->grantsAccess());
        $this->assertFalse(OrderStatus::Refunded->grantsAccess());
    }

    public function test_terminal_statuses_have_no_outgoing_transitions(): void
    {
        $this->assertSame([], OrderStatus::Cancelled->allowedTransitions());
        $this->assertSame([], OrderStatus::Expired->allowedTransitions());
        $this->assertSame([], OrderStatus::Refunded->allowedTransitions());
    }

    public function test_every_status_has_a_label(): void
    {
        foreach (OrderStatus::cases() as $status) {
            $this->assertNotSame('', $status->label());
        }
    }
}
