<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ParishContentResource;
use App\Services\DirectoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ParishContentController extends Controller
{
    public function __invoke(string $id, DirectoryService $directory): JsonResponse
    {
        $parish = $directory->query('parishes')
            ->with([
                'deanery:id,name',
                'massTimes' => fn ($query) => $query->where('is_published', true)->orderBy('display_order')->orderBy('id'),
                'announcements' => fn ($query) => $query->where('is_published', true)->whereNotNull('published_at')->where('published_at', '<=', now())->latest('published_at'),
                'events' => fn ($query) => $query->where('is_published', true)->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at'),
                'projects' => fn ($query) => $query->where('is_published', true)->latest('updated_at'),
            ])
            ->findOrFail($id);

        return ApiResponse::success((new ParishContentResource($parish))->resolve());
    }
}
