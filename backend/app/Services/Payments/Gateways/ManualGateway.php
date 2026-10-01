<?php

declare(strict_types=1);

namespace App\Services\Payments\Gateways;

use App\Contracts\Payments\PaymentGateway;
use App\Exceptions\PaymentException;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Payments\PaymentInitiation;
use App\Support\Payments\WebhookResult;
use Illuminate\Http\Request;

/**
 * Bank-transfer gateway with no third-party integration.
 *
 * The buyer transfers to the platform's account and uploads proof; an admin
 * confirms it. There is no webhook, so parseWebhook() is unreachable by
 * design — the admin verification endpoint is the equivalent entry point.
 */
class ManualGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'manual';
    }

    public function isConfigured(): bool
    {
        $bank = $this->bank();

        return filled($bank['name'])
            && filled($bank['account_number'])
            && filled($bank['account_holder']);
    }

    public function initiate(Order $order, Payment $payment): PaymentInitiation
    {
        if (! $this->isConfigured()) {
            throw PaymentException::gatewayDisabled($this->name());
        }

        return PaymentInitiation::manual($order, $this->bank());
    }

    public function parseWebhook(Request $request): WebhookResult
    {
        // Accepting a webhook here would be a vulnerability: with no shared
        // secret there is nothing to verify a caller against, so anyone could
        // mark an order paid.
        throw PaymentException::gatewayDisabled($this->name());
    }

    public function webhookAcknowledgement(): array
    {
        return [];
    }

    /**
     * @return array{name: ?string, account_number: ?string, account_holder: ?string}
     */
    private function bank(): array
    {
        /** @var array{name: ?string, account_number: ?string, account_holder: ?string} $bank */
        $bank = config('payment.gateways.manual.bank', []);

        return $bank;
    }
}
