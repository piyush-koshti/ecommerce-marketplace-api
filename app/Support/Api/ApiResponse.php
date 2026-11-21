<?php

namespace App\Support\Api;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(
        mixed $data = [],
        int $status = 200,
        array $meta = []
    ): JsonResponse {
        return response()->json([
            'data' => $data,
            'meta' => array_merge($meta, [
                'request_id' => request()->attributes->get('request_id'),
            ]),
        ], $status);
    }

    public static function error(
        string $code,
        string $message,
        int $status = 400,
        array $fields = []
    ): JsonResponse {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'fields' => (object) $fields,
                'request_id' => request()->attributes->get('request_id'),
            ],
        ], $status);
    }
}
