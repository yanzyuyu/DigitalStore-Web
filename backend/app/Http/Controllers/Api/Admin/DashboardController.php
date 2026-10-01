<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\ShopStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/admin/dashboard
     *
     * Counts only — no row data — so this stays a cheap set of indexed
     * aggregate queries regardless of catalogue size.
     */
    public function index(): JsonResponse
    {
        $revenue = Order::query()
            ->whereIn('status', [
                OrderStatus::Paid->value,
                OrderStatus::Processing->value,
                OrderStatus::Completed->value,
            ])
            ->selectRaw('COALESCE(SUM(grand_total), 0) as gross, COALESCE(SUM(commission_total), 0) as commission')
            ->first();

        return $this->success([
            'users' => [
                'total' => User::count(),
                'sellers' => User::role(UserRole::Seller)->count(),
                'buyers' => User::role(UserRole::Buyer)->count(),
                'suspended' => User::where('is_active', false)->count(),
            ],
            'shops' => [
                'total' => Shop::count(),
                'pending' => Shop::where('status', ShopStatus::Pending->value)->count(),
                'active' => Shop::where('status', ShopStatus::Active->value)->count(),
                'suspended' => Shop::where('status', ShopStatus::Suspended->value)->count(),
            ],
            'products' => [
                'total' => Product::count(),
                'pending_review' => Product::where('status', ProductStatus::Pending->value)->count(),
                'published' => Product::where('status', ProductStatus::Published->value)->count(),
            ],
            'orders' => [
                'total' => Order::count(),
                'awaiting_payment' => Order::where('status', OrderStatus::PendingPayment->value)->count(),
                'paid' => Order::where('status', OrderStatus::Paid->value)->count(),
                'completed' => Order::where('status', OrderStatus::Completed->value)->count(),
            ],
            'revenue' => [
                'gross' => (float) ($revenue->gross ?? 0),
                'commission' => (float) ($revenue->commission ?? 0),
            ],
            'queues' => [
                // The two numbers an admin actually acts on.
                'shops_awaiting_approval' => Shop::where('status', ShopStatus::Pending->value)->count(),
                'payments_awaiting_verification' => Payment::awaitingVerification()->count(),
                'products_awaiting_review' => Product::where('status', ProductStatus::Pending->value)->count(),
            ],
        ]);
    }
}
