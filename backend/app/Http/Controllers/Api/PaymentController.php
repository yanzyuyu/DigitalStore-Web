<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\UploadProofRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PaymentService $payments,
        private readonly PaymentGatewayManager $gateways,
    ) {}

    /**
     * GET /api/v1/orders/{invoice}/payment-instructions
     *
     * What the buyer needs in order to pay: bank details for a manual
     * transfer, or a redirect/VA for an online gateway. The response shape is
     * driven by the gateway, so switching gateways needs no frontend change
     * beyond rendering the instruction type.
     */
    public function instructions(string $invoice): JsonResponse
    {
        $order = Order::query()->where('invoice_number', $invoice)->firstOrFail();

        $this->authorize('pay', $order);

        $payment = $order->latestPayment();

        if ($payment === null) {
            return $this->error('Data pembayaran tidak ditemukan.', 'payment_not_found', 404);
        }

        $gateway = $this->gateways->resolve($payment->method->value);

        $initiation = $gateway->initiate($order, $payment);

        return $this->success([
            'payment' => new PaymentResource($payment),
            'gateway' => $gateway->name(),
            'reference' => $initiation->reference,
            'redirect_url' => $initiation->redirectUrl,
            'qr_string' => $initiation->qrString,
            'expires_at' => $initiation->expiresAt,
            'instructions' => $initiation->instructions,
        ]);
    }

    /**
     * POST /api/v1/orders/{invoice}/payment/proof
     *
     * Manual-transfer flow: the buyer uploads a receipt for an admin to review.
     * The proof goes to the private disk so it is never publicly enumerable.
     */
    public function uploadProof(UploadProofRequest $request, string $invoice): JsonResponse
    {
        $order = Order::query()->where('invoice_number', $invoice)->firstOrFail();

        $this->authorize('pay', $order);

        $payment = $order->latestPayment();

        if ($payment === null) {
            return $this->error('Data pembayaran tidak ditemukan.', 'payment_not_found', 404);
        }

        $this->authorize('uploadProof', $payment);

        $path = $request->file('proof')->store('payments/proofs', 'private');

        $payment = $this->payments->submitProof($payment, $path);

        return $this->success(
            new PaymentResource($payment),
            'Bukti pembayaran berhasil diunggah. Menunggu verifikasi admin.',
        );
    }

    /**
     * GET /api/v1/payments/{reference}
     */
    public function show(Payment $payment): JsonResponse
    {
        $this->authorize('view', $payment);

        return $this->success(new PaymentResource($payment));
    }
}
