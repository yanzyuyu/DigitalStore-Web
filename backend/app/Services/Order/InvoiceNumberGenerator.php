<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Generates the human-facing invoice number, e.g. INV-20261001-8F3K2Q.
 *
 * The format is date-prefixed so that invoices sort naturally and the date is
 * legible to support staff. Uniqueness is ultimately guaranteed by the unique
 * index on orders.invoice_number; the retry loop here only turns the rare
 * collision into a transparent retry instead of a 500.
 */
class InvoiceNumberGenerator
{
    private const MAX_ATTEMPTS = 5;

    public function generate(): string
    {
        $prefix = (string) config('marketplace.invoice.prefix', 'INV');
        $date = Carbon::now()->format((string) config('marketplace.invoice.date_format', 'Ymd'));

        return sprintf('%s-%s-%s', $prefix, $date, Str::upper(Str::random(8)));
    }

    /**
     * Generate a number and persist the order, retrying on the (vanishingly
     * unlikely) event of a collision.
     *
     * The caller must already be inside a transaction so a failed attempt
     * rolls back cleanly.
     */
    public function assignTo(Order $order): Order
    {
        $attempts = 0;

        while (true) {
            $attempts++;
            $order->invoice_number = $this->generate();

            try {
                $order->save();

                return $order;
            } catch (QueryException $e) {
                if ($attempts >= self::MAX_ATTEMPTS || ! $this->isUniqueViolation($e)) {
                    throw $e;
                }
            }
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        // SQLSTATE 23000 covers MySQL (1062) and SQLite (19) unique conflicts.
        return ($e->errorInfo[0] ?? null) === '23000';
    }
}
