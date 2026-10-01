<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateProductStatusRequest;
use App\Http\Resources\ProductResource;
use App\Http\Responses\ApiResponse;
use App\Models\Product;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Product moderation. Publishing is an admin decision, which is what keeps
 * the catalogue free of prohibited or infringing listings.
 */
class ProductController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ProductService $products) {}

    /**
     * GET /api/v1/admin/products
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', ProductStatus::values())],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $products = Product::query()
            ->with(['shop', 'category'])
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($validated['shop_id'] ?? null, fn ($q, $id) => $q->forShop((int) $id))
            ->when($validated['search'] ?? null, fn ($q, $term) => $q->search($term))
            // Default to the moderation queue: the work that needs attention.
            ->when(
                ! isset($validated['status']),
                fn ($q) => $q->where('status', ProductStatus::Pending->value),
            )
            ->oldest()
            ->paginate((int) ($validated['per_page'] ?? 20))
            ->withQueryString();

        return $this->paginated(ProductResource::collection($products));
    }

    /**
     * PATCH /api/v1/admin/products/{product}/status
     */
    public function updateStatus(UpdateProductStatusRequest $request, Product $product): JsonResponse
    {
        $this->authorize('moderate', $product);

        if ($request->wantsRejectionReason() && blank($request->input('reason'))) {
            throw ValidationException::withMessages([
                'reason' => ['Alasan penolakan wajib diisi.'],
            ]);
        }

        $product = $this->products->changeStatus(
            $product,
            ProductStatus::from($request->validated('status')),
            $request->input('reason'),
        );

        return $this->success(
            new ProductResource($product->load(['shop', 'category'])),
            'Status produk berhasil diperbarui.',
        );
    }
}
