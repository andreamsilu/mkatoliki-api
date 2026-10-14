<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class ParishBannerStorage
{
    public function store(UploadedFile $banner, int $parishId, string $collection): string
    {
        $path = $banner->store("parishes/{$parishId}/{$collection}", 'public');

        if ($path === false) {
            throw new RuntimeException('The banner could not be stored.');
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk('public')->delete($path);
        }
    }
}
