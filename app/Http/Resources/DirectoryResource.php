<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class DirectoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fields = array_merge(['id', 'created_at', 'updated_at'], $this->resource->getFillable());
        $fields = array_diff($fields, ['verification_status', 'verified_at']);
        if (! $request->attributes->get('directory_private', false)) {
            $fields = array_diff($fields, ['phone', 'email', 'address', 'description']);
        }

        return Arr::only($this->resource->attributesToArray(), $fields);
    }
}
