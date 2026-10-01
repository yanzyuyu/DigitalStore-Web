<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\StoreShopRequest;
use App\Http\Requests\Shop\UpdateShopRequest;
use App\Http\Resources\ShopResource;
use App\Http\Responses\ApiResponse;
use App\Models\Shop;
use App\Services\Shop\ShopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Seller self-service for their own shop. Route middleware guarantees the
 * caller is a seller; the policy guarantees the shop is theirs.
 */
class ShopController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ShopService $shops) {}

    /**
     * GET /api/v1/seller/shop
     */
    public function show(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        if ($shop === null) {
            return $this->error('Anda belum memiliki toko.', 'shop_not_found', 404);
        }

        $this->authorize('view', $shop);

        return $this->success(
            new ShopResource($shop->loadCount(['products' => fn ($q) => $q->published()])),
        );
    }

    /**
     * POST /api/v1/seller/shop
     */
    public function store(StoreShopRequest $request): JsonResponse
    {
        $shop = $this->shops->createForUser($request->user(), $request->validated());

        return $this->created(new ShopResource($shop), 'Toko berhasil dibuat dan menunggu verifikasi.');
    }

    /**
     * PUT /api/v1/seller/shop
     */
    public function update(UpdateShopRequest $request): JsonResponse
    {
        $shop = $request->user()->shop;

        if ($shop === null) {
            return $this->error('Anda belum memiliki toko.', 'shop_not_found', 404);
        }

        $this->authorize('update', $shop);

        $attributes = $request->safe()->except(['logo', 'banner']);

        if ($attributes !== []) {
            $shop = $this->shops->update($shop, $attributes);
        }

        if ($request->hasFile('logo')) {
            $shop = $this->shops->storeLogo($shop, $request->file('logo'));
        }

        if ($request->hasFile('banner')) {
            $shop = $this->shops->storeBanner($shop, $request->file('banner'));
        }

        return $this->success(new ShopResource($shop), 'Toko berhasil diperbarui.');
    }
}
