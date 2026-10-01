<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderItemResource;
use App\Http\Resources\OrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A seller's view of the orders that contain their products.
 *
 * Scoping is the security-critical part: a seller must see their own line
 * items and nothing else from the order — not the buyer's other purchases,
 * not the platform commission on other shops' items.
 */
class OrderController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/seller/orders
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $shopId = $request->user()->shop?->id;

        if ($shopId === null) {
            return $this->error('Anda belum memiliki toko.', 'shop_not_found', 404);
        }

        // Only orders that actually contain one of this shop's products.
        $orders = Order::query()
            ->whereHas('items', fn ($q) => $q->where('shop_id', $shopId))
            ->with([
                'buyer',
                // Load ONLY this shop's items, never the whole basket.
                'items' => fn ($q) => $q->where('shop_id', $shopId)->with('shop'),
            ])
            ->latest()
            ->paginate((int) ($validated['per_page'] ?? 15))
            ->withQueryString();

        return $this->paginated(OrderResource::collection($orders));
    }

    /**
     * GET /api/v1/seller/orders/{invoice}
     */
    public function show(Request $request, string $invoice): JsonResponse
    {
        $shopId = $request->user()->shop?->id;

        $order = Order::query()
            ->where('invoice_number', $invoice)
            ->with([
                'items' => fn ($q) => $q->where('shop_id', $shopId)->with('shop'),
            ])
            ->firstOrFail();

        // The policy confirms at least one item belongs to this seller.
        $this->authorize('view', $order);

        return $this->success(new OrderResource($order));
    }

    /**
     * GET /api/v1/seller/sales
     *
     * Line-item level sales report, restricted to this shop.
     */
    public function sales(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $shopId = $request->user()->shop?->id;

        if ($shopId === null) {
            return $this->error('Anda belum memiliki toko.', 'shop_not_found', 404);
        }

        $items = OrderItem::query()
            ->where('shop_id', $shopId)
            // Only count money that actually settled.
            ->whereHas('order', function ($q) {
                $q->whereIn('status', ['paid', 'processing', 'completed']);
            })
            ->with('order')
            ->when($validated['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($validated['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate((int) ($validated['per_page'] ?? 20));

        $summary = OrderItem::query()
            ->where('shop_id', $shopId)
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['paid', 'processing', 'completed']))
            ->when($validated['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($validated['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->selectRaw('COUNT(*) as items_sold, COALESCE(SUM(line_total), 0) as gross, COALESCE(SUM(seller_earning), 0) as net')
            ->first();

        return $this->success(
            OrderItemResource::collection($items)->collection,
            'Laporan penjualan berhasil dimuat.',
            200,
            [
                'summary' => [
                    'items_sold' => (int) ($summary->items_sold ?? 0),
                    'gross' => (float) ($summary->gross ?? 0),
                    'net' => (float) ($summary->net ?? 0),
                ],
                'pagination' => [
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                ],
            ],
        );
    }
}
