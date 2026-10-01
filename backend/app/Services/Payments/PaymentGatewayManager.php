<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Exceptions\PaymentException;
use App\Services\Payments\Gateways\DuitkuGateway;
use App\Services\Payments\Gateways\ManualGateway;
use App\Services\Payments\Gateways\MidtransGateway;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves a PaymentGateway by name.
 *
 * Drivers are bound here rather than resolved by class name from config, so a
 * mis-typed config value can never instantiate an arbitrary class.
 */
class PaymentGatewayManager
{
    /** @var array<string, class-string<PaymentGateway>> */
    private const DRIVERS = [
        'manual' => ManualGateway::class,
        'duitku' => DuitkuGateway::class,
        'midtrans' => MidtransGateway::class,
    ];

    public function __construct(private readonly Container $container) {}

    /**
     * The gateway configured as default.
     */
    public function driver(?string $name = null): PaymentGateway
    {
        return $this->resolve($name ?? (string) config('payment.default', 'manual'));
    }

    public function resolve(string $name): PaymentGateway
    {
        $class = self::DRIVERS[$name] ?? null;

        if ($class === null) {
            throw PaymentException::gatewayNotFound($name);
        }

        /** @var PaymentGateway $gateway */
        $gateway = $this->container->make($class);

        return $gateway;
    }

    /**
     * @return array<int, string>
     */
    public function available(): array
    {
        return array_keys(self::DRIVERS);
    }
}
