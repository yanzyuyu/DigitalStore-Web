<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderDeliverableResource;
use App\Http\Responses\ApiResponse;
use App\Models\OrderDeliverable;
use App\Services\Order\DownloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly DownloadService $downloads) {}

    /**
     * GET /api/v1/downloads
     *
     * Everything this buyer is entitled to, across all their orders.
     */
    public function index(Request $request): JsonResponse
    {
        $deliverables = OrderDeliverable::query()
            ->where('buyer_id', $request->user()->id)
            ->where('is_active', true)
            ->with(['productFile', 'orderItem.product'])
            ->latest()
            ->paginate(20);

        return $this->paginated(OrderDeliverableResource::collection($deliverables));
    }

    /**
     * GET /api/v1/downloads/{deliverable}/file
     *
     * Authorisation is re-checked on every request rather than relying on a
     * previously-issued URL, so a refund revokes access immediately.
     *
     * In production this route should additionally be registered behind a
     * signed-URL middleware, so the link itself expires; the entitlement check
     * here is the authoritative gate either way.
     */
    public function download(Request $request, OrderDeliverable $deliverable): StreamedResponse|JsonResponse
    {
        return $this->downloads->stream(
            $deliverable,
            $request->user(),
            $request->ip(),
            $request->userAgent(),
        );
    }
}
