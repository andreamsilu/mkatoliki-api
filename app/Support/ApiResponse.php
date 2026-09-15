<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function success(mixed $data, int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'meta' => (object) $meta], $status);
    }

    public static function page(LengthAwarePaginator $page, mixed $data = null): JsonResponse
    {
        return self::success($data ?? $page->items(), meta: [
            'current_page' => $page->currentPage(), 'per_page' => $page->perPage(),
            'total' => $page->total(), 'last_page' => $page->lastPage(),
        ]);
    }

    public static function error(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return response()->json(['success' => false, 'error' => array_filter([
            'code' => $code, 'message' => $message, 'details' => $details,
        ])], $status);
    }
}
