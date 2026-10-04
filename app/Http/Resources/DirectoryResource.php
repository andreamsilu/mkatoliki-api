<?php

namespace App\Http\Resources;

use App\Models\Deanery;
use App\Models\Diocese;
use App\Models\DirectoryEntity;
use App\Models\EcclesiasticalProvince;
use App\Models\Parish;
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
            $fields = array_diff($fields, ['phone', 'email', 'address', 'description', 'leader_member_id', 'secretary_member_id', 'head_member_id']);
        }

        $data = Arr::only($this->resource->attributesToArray(), $fields);
        $hierarchy = $this->hierarchy();

        return $hierarchy === [] ? $data : [...$data, 'hierarchy' => $hierarchy];
    }

    /**
     * @return array<string, array{id: int, code: ?string, name: string}>
     */
    private function hierarchy(): array
    {
        $hierarchy = [];
        $province = match (true) {
            $this->resource instanceof EcclesiasticalProvince => $this->resource,
            $this->resource instanceof Diocese && $this->resource->relationLoaded('province') => $this->resource->province,
            $this->resource instanceof Deanery && $this->resource->relationLoaded('diocese') => $this->resource->diocese?->province,
            $this->resource instanceof Parish && $this->resource->relationLoaded('deanery') => $this->resource->deanery?->diocese?->province,
            default => null,
        };
        $diocese = match (true) {
            $this->resource instanceof Diocese => $this->resource,
            $this->resource instanceof Deanery && $this->resource->relationLoaded('diocese') => $this->resource->diocese,
            $this->resource instanceof Parish && $this->resource->relationLoaded('deanery') => $this->resource->deanery?->diocese,
            default => null,
        };
        $deanery = match (true) {
            $this->resource instanceof Deanery => $this->resource,
            $this->resource instanceof Parish && $this->resource->relationLoaded('deanery') => $this->resource->deanery,
            default => null,
        };

        foreach (['province' => $province, 'diocese' => $diocese, 'deanery' => $deanery, 'parish' => $this->resource instanceof Parish ? $this->resource : null] as $level => $record) {
            if ($record instanceof DirectoryEntity) {
                $hierarchy[$level] = ['id' => $record->id, 'code' => $record->code, 'name' => $record->name];
            }
        }

        return $hierarchy;
    }
}
