<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/categories
     *
     * Returns the tree in one query rather than one per level.
     */
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->active()
            ->withCount(['products' => fn ($q) => $q->published()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Nest children under parents in memory, avoiding N+1 lazy loads.
        $grouped = $categories->groupBy('parent_id');

        $tree = $categories
            ->whereNull('parent_id')
            ->map(function (Category $category) use ($grouped) {
                $category->setRelation(
                    'children',
                    $grouped->get($category->id, collect())->values(),
                );

                return $category;
            })
            ->values();

        return $this->success(CategoryResource::collection($tree));
    }
}
