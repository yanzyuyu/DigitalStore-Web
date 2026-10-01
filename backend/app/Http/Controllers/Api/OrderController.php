<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\Order;
use App\Services\Order\OrderStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Buyer-facing order history.
 */
class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly OrderStateMachine $stateMachine) {}

    /**
     * GET /api/v1/orders
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', OrderStatus::values())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $orders = Order::query()
            ->forBuyer($request->user()->id)
            ->with(['items.shop', 'payments'])
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate((int) ($validated['per_page'] ?? 15))
            ->withQueryString();

        return $this->paginated(OrderResource::collection($orders));
    }

    /**
     * GET /api/v1/orders/{invoice}
     *
     * Looked up by invoice number so the URL is shareable and does not expose
     * a sequential primary key.
     */
    public function show(Request $request, string $invoice): JsonResponse
    {
        $order = Order::query()
            ->where('invoice_number', $invoice)
            ->with(['items.shop', 'payments', 'buyer'])
            ->firstOrFail();

        $this->authorize('view', $order);

        return $this->success(new OrderResource($order));
    }

    /**
     * POST /api/v1/orders/{invoice}/cancel
     */
    public function cancel(Request $request, string $invoice): JsonResponse
    {
        $order = Order::query()->where('invoice_number', $invoice)->firstOrFail();

        $this->authorize('cancel', $order);

        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->stateMachine->transition(
            $order,
            OrderStatus::Cancelled,
            $request->input('reason') ?? 'Dibatalkan oleh pembeli.',
        );

        return $this->success(
            new OrderResource($order->fresh(['items.shop', 'payments'])),
            'Pesanan berhasil dibatalkan.',
        );
    }
}
