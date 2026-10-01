<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\PaymentException;
use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Payment gateway callbacks.
 *
 * This endpoint is unauthenticated by necessity — the provider cannot hold a
 * Sanctum token. Trust therefore comes entirely from the signature check in
 * the gateway driver, which runs BEFORE any state is touched.
 *
 * Two properties matter most here:
 *
 *   1. A forged request must be rejected. The signature is recomputed from the
 *      body with a shared secret and compared with hash_equals().
 *   2. A duplicate delivery must be harmless. The provider will retry until it
 *      gets the acknowledgement shape it expects, so the handler is idempotent
 *      and always acknowledges an event it has already processed.
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly PaymentService $payments,
    ) {}

    /**
     * POST /api/v1/webhooks/payments/{gateway}
     */
    public function handle(Request $request, string $gateway): JsonResponse
    {
        try {
            $driver = $this->gateways->resolve($gateway);
        } catch (PaymentException) {
            // Do not confirm the existence of gateway names to a prober.
            return response()->json(['message' => 'Not found'], 404);
        }

        try {
            // Throws unless the signature verifies against the raw body.
            $result = $driver->parseWebhook($request);

            $this->payments->applyWebhook($result);

            return response()->json($driver->webhookAcknowledgement());
        } catch (PaymentException $e) {
            Log::warning('Payment webhook rejected', [
                'gateway' => $gateway,
                'error_code' => $e->errorCode(),
                'message' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            // 401 for a bad signature, 422 for a payload we could not apply.
            return response()->json(
                ['message' => $e->getMessage()],
                $e->statusCode(),
            );
        } catch (\Throwable $e) {
            // Log the detail but return a generic body: an unhandled error must
            // not leak internals to an unauthenticated caller. Returning 500
            // also tells the provider to retry, which is what we want.
            Log::error('Payment webhook failed', [
                'gateway' => $gateway,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Internal error'], 500);
        }
    }
}
