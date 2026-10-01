<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShopResource;
use App\Http\Responses\ApiResponse;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/shops
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $shops = Shop::query()
            ->active()
            ->withCount(['products' => fn ($q) => $q->published()])
            ->when($validated['search'] ?? null, function ($q, $term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

                $q->where('name', 'like', $like);
            })
            ->latest()
            ->paginate((int) ($validated['per_page'] ?? 15))
            ->withQueryString();

        return $this->paginated(ShopResource::collection($shops));
    }

    /**
     * GET /api/v1/shops/{slug}
     */
    public function show(string $slug): JsonResponse
    {
        $shop = Shop::query()
            ->where('slug', $slug)
            ->withCount(['products' => fn ($q) => $q->published()])
            ->first();

        if ($shop === null) {
            return $this->error('Toko tidak ditemukan.', 'not_found', 404);
        }

        return $this->success(new ShopResource($shop));
    }
}
