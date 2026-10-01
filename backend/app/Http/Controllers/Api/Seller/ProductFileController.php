<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\UploadProductFileRequest;
use App\Http\Resources\ProductFileResource;
use App\Http\Responses\ApiResponse;
use App\Models\Product;
use App\Models\ProductFile;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;

/**
 * Management of the actual downloadable files — the payload of a digital
 * product. Every action is scoped to the seller who owns the product.
 */
class ProductFileController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ProductService $products) {}

    /**
     * GET /api/v1/seller/products/{product}/files
     */
    public function index(Product $product): JsonResponse
    {
        $this->authorize('manageFiles', $product);

        return $this->success(
            ProductFileResource::collection($product->files()->orderBy('sort_order')->get()),
        );
    }

    /**
     * POST /api/v1/seller/products/{product}/files
     */
    public function store(UploadProductFileRequest $request, Product $product): JsonResponse
    {
        $this->authorize('manageFiles', $product);

        $file = $this->products->storeFile(
            $product,
            $request->file('file'),
            $request->safe()->only(['name', 'version', 'is_primary']),
        );

        return $this->created(
            new ProductFileResource($file),
            'File produk berhasil diunggah.',
        );
    }

    /**
     * DELETE /api/v1/seller/products/{product}/files/{file}
     */
    public function destroy(Product $product, ProductFile $file): JsonResponse
    {
        $this->authorize('manageFiles', $product);

        // Guard against a file id from another product being passed in, which
        // would otherwise let a seller delete another seller's file.
        if ($file->product_id !== $product->id) {
            return $this->error('File tidak ditemukan pada produk ini.', 'not_found', 404);
        }

        $this->products->deleteFile($file);

        return $this->noContent('File produk berhasil dihapus.');
    }
}
