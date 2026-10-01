<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Seller;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Http\Responses\ApiResponse;
use App\Models\Product;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ProductService $products) {}

    /**
     * GET /api/v1/seller/products
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', ProductStatus::values())],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $shopId = $request->user()->shop?->id;

        if ($shopId === null) {
            return $this->error('Anda belum memiliki toko.', 'shop_not_found', 404);
        }

        $products = Product::query()
            ->forShop($shopId)
            ->with(['category', 'files'])
            ->withCount('reviews')
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($validated['search'] ?? null, fn ($q, $term) => $q->search($term))
            ->latest()
            ->paginate((int) ($validated['per_page'] ?? 15))
            ->withQueryString();

        return $this->paginated(ProductResource::collection($products));
    }

    /**
     * POST /api/v1/seller/products
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $shop = $request->user()->shop;

        if ($shop === null) {
            return $this->error('Anda belum memiliki toko.', 'shop_not_found', 404);
        }

        // The policy checks the shop is operational, so a pending seller gets
        // a clear 403 rather than silently creating unreachable products.
        $this->authorize('create', Product::class);

        $product = $this->products->create($shop, $request->safe()->except(['thumbnail', 'images']));

        if ($request->hasFile('thumbnail')) {
            $product = $this->products->storeThumbnail($product, $request->file('thumbnail'));
        }

        foreach ($request->file('images', []) as $image) {
            $this->products->storeImage($product, $image);
        }

        return $this->created(
            new ProductResource($product->load(['category', 'files'])),
            'Produk berhasil dibuat sebagai draf.',
        );
    }

    /**
     * GET /api/v1/seller/products/{product}
     */
    public function show(Request $request, Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        return $this->success(
            new ProductResource($product->load(['category', 'files', 'images'])),
        );
    }

    /**
     * PUT /api/v1/seller/products/{product}
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $product = $this->products->update(
            $product,
            $request->safe()->except(['thumbnail']),
        );

        if ($request->hasFile('thumbnail')) {
            $product = $this->products->storeThumbnail($product, $request->file('thumbnail'));
        }

        return $this->success(
            new ProductResource($product->load(['category', 'files'])),
            'Produk berhasil diperbarui.',
        );
    }

    /**
     * DELETE /api/v1/seller/products/{product}
     */
    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $this->products->delete($product);

        return $this->noContent('Produk berhasil dihapus.');
    }
}
