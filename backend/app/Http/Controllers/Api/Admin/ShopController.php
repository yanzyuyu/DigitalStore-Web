<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ShopStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShopResource;
use App\Http\Responses\ApiResponse;
use App\Models\Shop;
use App\Services\Shop\ShopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ShopService $shops) {}

    /**
     * GET /api/v1/admin/shops
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', ShopStatus::values())],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $shops = Shop::query()
            ->with('user')
            ->withCount('products')
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($validated['search'] ?? null, function ($q, $term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
                $q->where('name', 'like', $like);
            })
            // Default to the approval queue.
            ->when(
                ! isset($validated['status']),
                fn ($q) => $q->where('status', ShopStatus::Pending->value),
            )
            ->oldest()
            ->paginate((int) ($validated['per_page'] ?? 20))
            ->withQueryString();

        return $this->paginated(ShopResource::collection($shops));
    }

    /**
     * POST /api/v1/admin/shops/{shop}/approve
     */
    public function approve(Request $request, Shop $shop): JsonResponse
    {
        $this->authorize('moderate', $shop);

        $shop = $this->shops->approve($shop, $request->user());

        return $this->success(
            new ShopResource($shop->load('user')),
            'Toko berhasil diverifikasi dan diaktifkan.',
        );
    }

    /**
     * POST /api/v1/admin/shops/{shop}/suspend
     */
    public function suspend(Request $request, Shop $shop): JsonResponse
    {
        $this->authorize('moderate', $shop);

        $shop = $this->shops->suspend($shop, $request->user());

        return $this->success(
            new ShopResource($shop->load('user')),
            'Toko berhasil ditangguhkan.',
        );
    }
}
