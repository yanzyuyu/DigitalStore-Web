<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Order\OrderExpiryService;
use Illuminate\Console\Command;

class ExpireOrdersCommand extends Command
{
    protected $signature = 'orders:expire {--limit=500 : Maximum orders to expire in one run}';

    protected $description = 'Kedaluwarsakan pesanan yang belum dibayar melewati batas waktu';

    public function handle(OrderExpiryService $expiry): int
    {
        $limit = (int) $this->option('limit');

        $count = $expiry->expireOverdueOrders($limit);

        $this->info(sprintf('%d pesanan berhasil dikedaluwarsakan.', $count));

        return self::SUCCESS;
    }
}
