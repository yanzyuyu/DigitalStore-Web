<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A single, predictable response envelope for the whole API.
 *
 * Every endpoint returns the same top-level shape, so the frontend never has
 * to guess where the payload lives or special-case one controller.
 */
trait ApiResponse
{
    protected function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    protected function created(mixed $data = null, string $message = 'Data berhasil dibuat.'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function error(
        string $message,
        string $errorCode = 'error',
        int $status = 400,
        array $errors = [],
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
            'error_code' => $errorCode,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * Wrap a paginated resource collection, lifting the pagination metadata
     * out of the data payload and into `meta`.
     */
    protected function paginated(ResourceCollection $collection, string $message = 'OK'): JsonResponse
    {
        $response = $collection->response()->getData(true);

        return $this->success(
            $response['data'] ?? [],
            $message,
            200,
            $response['meta'] ?? [],
        );
    }

    protected function noContent(string $message = 'Data berhasil dihapus.'): JsonResponse
    {
        return $this->success(null, $message, 200);
    }
}
