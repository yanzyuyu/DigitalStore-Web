<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Exceptions\CheckoutException;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\Order\InvoiceNumberGenerator;
use App\Support\CheckoutItem;
use App\Support\LineItemTotals;
use App\Support\OrderTotals;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a basket into an order.
 *
 * Everything below happens inside a single database transaction. If any step
 * fails — a product went off sale, the coupon was exhausted, the invoice
 * number collided — the whole thing rolls back and the buyer is left with no
 * half-written order and no orphaned payment.
 */
class CheckoutService
{
    public function __construct(
        private readonly MoneyCalculator $calculator,
        private readonly InvoiceNumberGenerator $invoiceNumbers,
    ) {}

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $lines
     *
     * @throws CheckoutException
     */
    public function checkout(
        User $buyer,
        array $lines,
        ?string $couponCode = null,
        array $customer = [],
        ?string $notes = null,
    ): Order {
        if ($lines === []) {
            throw CheckoutException::emptyCart();
        }

        return DB::transaction(function () use ($buyer, $lines, $couponCode, $customer, $notes): Order {
            $items = $this->resolveItems($buyer, $lines);

            $coupon = $this->resolveCoupon($couponCode, $items);

            $subtotal = array_sum(array_map(
                static fn (CheckoutItem $item): float => $item->grossLineTotal(),
                $items,
            ));

            $discount = $coupon?->calculateDiscount($subtotal) ?? 0.0;

            $totals = $this->calculator->calculate($items, $discount);

            $order = $this->persistOrder($buyer, $totals, $coupon, $customer, $notes);

            $this->persistItems($order, $totals);

            if ($coupon !== null) {
                // Incremented inside the same transaction as the order, so a
                // rollback cannot burn a coupon redemption.
                $coupon->increment('used_count');
            }

            $this->createPayment($order);

            return $order->load(['items', 'payments']);
        }, attempts: 3);
    }

    /**
     * Load and validate every product, taking a row lock so that a concurrent
     * price change or unpublish cannot slip in between validation and insert.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $lines
     * @return array<int, CheckoutItem>
     */
    private function resolveItems(User $buyer, array $lines): array
    {
        $quantities = [];

        foreach ($lines as $line) {
            $productId = (int) $line['product_id'];
            $quantity = (int) ($line['quantity'] ?? 1);

            if ($quantity < 1) {
                $quantity = 1;
            }

            $quantities[$productId] = ($quantities[$productId] ?? 0) + $quantity;
        }

        // Lock in a deterministic order (ascending id). Two concurrent
        // checkouts that touch the same products in opposite orders would
        // otherwise be able to deadlock each other.
        ksort($quantities);

        $products = Product::query()
            ->with('shop')
            ->whereIn('id', array_keys($quantities))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $items = [];

        foreach ($quantities as $productId => $quantity) {
            /** @var Product|null $product */
            $product = $products->get($productId);

            if ($product === null) {
                throw CheckoutException::productNotPurchasable('#'.$productId);
            }

            if (! $product->isPurchasable() || $product->status !== ProductStatus::Published) {
                throw CheckoutException::productNotPurchasable($product->name);
            }

            if (! $product->shop->isOperational()) {
                throw CheckoutException::shopNotOperational($product->shop->name);
            }

            // A seller buying from their own shop would let them inflate
            // sales counters and launder the commission fee.
            if ($product->shop->user_id === $buyer->id) {
                throw CheckoutException::cannotBuyOwnProduct();
            }

            $items[] = new CheckoutItem($product, $quantity);
        }

        return $items;
    }

    /**
     * @param  array<int, CheckoutItem>  $items
     */
    private function resolveCoupon(?string $code, array $items): ?Coupon
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        // Lock the coupon so two simultaneous checkouts cannot both pass the
        // usage_limit check and exceed the limit together.
        $coupon = Coupon::query()
            ->byCode($code)
            ->lockForUpdate()
            ->first();

        if ($coupon === null) {
            throw CheckoutException::invalidCoupon('Kode kupon tidak ditemukan.');
        }

        if (! $coupon->isRedeemable()) {
            throw CheckoutException::invalidCoupon('Kode kupon sudah tidak berlaku.');
        }

        // A shop-scoped coupon may only discount that shop's products.
        if (! $coupon->isPlatformWide()) {
            foreach ($items as $item) {
                if ($item->product->shop_id !== $coupon->shop_id) {
                    throw CheckoutException::invalidCoupon(
                        'Kode kupon hanya berlaku untuk produk dari toko tertentu.'
                    );
                }
            }
        }

        return $coupon;
    }

    private function persistOrder(
        User $buyer,
        OrderTotals $totals,
        ?Coupon $coupon,
        array $customer,
        ?string $notes,
    ): Order {
        $order = new Order([
            'buyer_id' => $buyer->id,
            'coupon_id' => $coupon?->id,
            'status' => OrderStatus::PendingPayment,
            'currency' => 'IDR',
            'customer_name' => $customer['name'] ?? $buyer->name,
            'customer_email' => $customer['email'] ?? $buyer->email,
            'customer_phone' => $customer['phone'] ?? $buyer->phone,
            'notes' => $notes,
        ]);

        // Totals are assigned directly, never through mass assignment, so they
        // can only ever come from the calculator.
        $order->subtotal = $totals->subtotal;
        $order->discount_total = $totals->discountTotal;
        $order->tax_total = 0;
        $order->grand_total = $totals->grandTotal;
        $order->commission_total = $totals->commissionTotal;
        $order->seller_net_total = $totals->sellerNetTotal;
        $order->expires_at = Carbon::now()->addMinutes(
            (int) config('payment.expiry_minutes', 1440)
        );

        return $this->invoiceNumbers->assignTo($order);
    }

    private function persistItems(Order $order, OrderTotals $totals): void
    {
        $now = now();

        $rows = array_map(static function (LineItemTotals $line) use ($order, $now): array {
            $product = $line->item->product;

            return [
                'order_id' => $order->id,
                'shop_id' => $product->shop_id,
                'product_id' => $product->id,
                // Record the file that was current at sale time.
                'product_file_id' => $product->files()->where('is_active', true)->value('id'),
                'product_name' => $product->name,
                'product_slug' => $product->slug,
                'product_thumbnail_path' => $product->thumbnail_path,
                'quantity' => $line->item->quantity,
                'unit_price' => $line->unitPrice,
                'discount_amount' => $line->discountAmount,
                'line_total' => $line->lineTotal,
                'commission_percent' => $line->commissionPercent,
                'commission_amount' => $line->commissionAmount,
                'seller_earning' => $line->sellerEarning,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $totals->lines);

        $order->items()->insert($rows);

        // Keep the denormalised sales counters in step with the order.
        foreach ($totals->lines as $line) {
            Product::whereKey($line->item->product->id)
                ->update(['sales_count' => DB::raw('sales_count + 1')]);
        }
    }

    private function createPayment(Order $order): Payment
    {
        $method = PaymentMethod::tryFrom((string) config('payment.default'))
            ?? PaymentMethod::Manual;

        return Payment::create([
            'order_id' => $order->id,
            'reference' => $this->generateReference(),
            'method' => $method,
            'status' => PaymentStatus::Pending,
            'amount' => $order->grand_total,
            'currency' => $order->currency,
            'expires_at' => $order->expires_at,
        ]);
    }

    private function generateReference(): string
    {
        return sprintf(
            'PAY-%s-%s',
            Carbon::now()->format('Ymd'),
            Str::upper(Str::random(10)),
        );
    }
}
