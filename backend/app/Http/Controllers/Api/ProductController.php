<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ReviewResource;
use App\Http\Responses\ApiResponse;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public, unauthenticated catalogue browsing.
 */
class ProductController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/products
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'gte:min_price'],
            'featured' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:newest,oldest,price_asc,price_desc,popular,rating'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $products = Product::query()
            ->published()
            // Only products from an approved shop are publicly listable.
            ->whereHas('shop', fn ($q) => $q->active())
            ->with(['category', 'shop'])
            ->when($validated['search'] ?? null, fn ($q, $term) => $q->search($term))
            ->when($validated['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($validated['shop_id'] ?? null, fn ($q, $id) => $q->forShop((int) $id))
            ->when(
                isset($validated['min_price']) || isset($validated['max_price']),
                fn ($q) => $q->inPriceRange(
                    isset($validated['min_price']) ? (float) $validated['min_price'] : null,
                    isset($validated['max_price']) ? (float) $validated['max_price'] : null,
                ),
            )
            ->when($validated['featured'] ?? null, fn ($q) => $q->where('is_featured', true))
            ->when(true, fn ($q) => $this->applySort($q, $validated['sort'] ?? 'newest'))
            ->paginate((int) ($validated['per_page'] ?? 15))
            ->withQueryString();

        return $this->paginated(ProductResource::collection($products));
    }

    /**
     * GET /api/v1/products/{slug}
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->with([
                'category',
                'shop',
                'images',
                'reviews' => fn ($q) => $q->approved()->with('user')->latest()->limit(20),
            ])
            ->first();

        if ($product === null) {
            return $this->error('Produk tidak ditemukan.', 'not_found', 404);
        }

        // A non-published product is only visible to its owner or an admin.
        if ($product->status !== ProductStatus::Published) {
            $viewer = $request->user();

            $isOwner = $viewer !== null && $product->shop->user_id === $viewer->id;
            $isAdmin = $viewer !== null && $viewer->isAdmin();

            if (! $isOwner && ! $isAdmin) {
                return $this->error('Produk tidak ditemukan.', 'not_found', 404);
            }
        }

        // Incrementing a view counter on every detail read is a write on a hot
        // path; in production this belongs in a queued/batched job. Kept
        // synchronous here for correctness of the counter.
        $product->increment('view_count');

        return $this->success(new ProductResource($product));
    }

    /**
     * GET /api/v1/products/{slug}/reviews
     */
    public function reviews(string $slug): JsonResponse
    {
        $product = Product::query()->where('slug', $slug)->published()->firstOrFail();

        $reviews = $product->reviews()
            ->approved()
            ->with('user')
            ->latest()
            ->paginate(15);

        return $this->paginated(ReviewResource::collection($reviews));
    }

    private function applySort($query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query->orderBy('published_at'),
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'popular' => $query->orderByDesc('sales_count'),
            'rating' => $query->orderByDesc('rating_average')->orderByDesc('rating_count'),
            default => $query->orderByDesc('published_at'),
        };
    }
}
