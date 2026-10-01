<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderDeliverable;
use Illuminate\Support\Facades\DB;

/**
 * Owns the entitlement ledger.
 *
 * Access to a digital file is granted by writing an order_deliverables row,
 * and revoked by flipping is_active. Download authorisation is therefore a
 * single indexed lookup rather than a re-derivation of order state, which
 * keeps the hot download path cheap and the audit trail explicit.
 */
class DeliverableService
{
    /**
     * Grant access to every file attached to the order's items.
     *
     * Idempotent: re-running it (a duplicate webhook, a manual re-activation)
     * cannot create duplicate entitlements or reset download counters.
     *
     * Must be called inside the transaction that marks the order paid.
     */
    public function grantForOrder(Order $order): int
    {
        $order->loadMissing('items.productFile');

        $rows = [];
        $now = now();

        foreach ($order->items as $item) {
            // Prefer the file recorded at checkout; fall back to the product's
            // current active files so an order still delivers if the seller
            // attaches the file after the sale.
            $fileIds = $item->product_file_id !== null
                ? [$item->product_file_id]
                : $item->product->files()->active()->pluck('id')->all();

            foreach ($fileIds as $fileId) {
                $rows[] = [
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'product_file_id' => $fileId,
                    'buyer_id' => $order->buyer_id,
                    'download_count' => 0,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows === []) {
            return 0;
        }

        // insertOrIgnore leans on the (order_item_id, product_file_id) unique
        // index to make this idempotent without a read-modify-write race.
        OrderDeliverable::insertOrIgnore($rows);

        return count($rows);
    }

    /**
     * Revoke every entitlement belonging to an order, e.g. after a refund.
     * Rows are retained so the audit trail survives.
     */
    public function revokeForOrder(Order $order): int
    {
        return OrderDeliverable::query()
            ->where('order_id', $order->id)
            ->where('is_active', true)
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    /**
     * Record a download and return the deliverable that was incremented.
     *
     * The counter is bumped with an atomic SQL expression rather than
     * read-modify-write, so concurrent downloads cannot lose an increment.
     */
    public function recordDownload(OrderDeliverable $deliverable): void
    {
        DB::table('order_deliverables')
            ->where('id', $deliverable->id)
            ->update([
                'download_count' => DB::raw('download_count + 1'),
                'last_downloaded_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
