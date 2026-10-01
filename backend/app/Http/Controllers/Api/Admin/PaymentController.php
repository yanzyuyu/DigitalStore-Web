<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\RejectPaymentRequest;
use App\Http\Requests\Payment\VerifyPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manual payment verification queue.
 *
 * With no gateway in play, this is the endpoint that turns a buyer's uploaded
 * receipt into a settled order — and therefore the one place where a mistake
 * directly costs money. It is admin-only and every decision is recorded.
 */
class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly PaymentService $payments) {}

    /**
     * GET /api/v1/admin/payments/pending
     */
    public function pending(Request $request): JsonResponse
    {
        $payments = Payment::query()
            ->awaitingVerification()
            ->with(['order.buyer', 'order.items'])
            ->oldest()
            ->paginate((int) $request->integer('per_page', 20));

        return $this->paginated(PaymentResource::collection($payments));
    }

    /**
     * POST /api/v1/admin/payments/{payment}/verify
     */
    public function verify(VerifyPaymentRequest $request, Payment $payment): JsonResponse
    {
        $this->authorize('verify', $payment);

        $payment = $this->payments->verifyManually(
            $payment,
            $request->user(),
            $request->input('note'),
        );

        return $this->success(
            new PaymentResource($payment),
            'Pembayaran berhasil diverifikasi. Akses unduhan telah diberikan.',
        );
    }

    /**
     * POST /api/v1/admin/payments/{payment}/reject
     */
    public function reject(RejectPaymentRequest $request, Payment $payment): JsonResponse
    {
        $this->authorize('verify', $payment);

        $payment = $this->payments->rejectManually(
            $payment,
            $request->user(),
            $request->validated('reason'),
        );

        return $this->success(
            new PaymentResource($payment),
            'Pembayaran ditolak dan pesanan dibatalkan.',
        );
    }
}
