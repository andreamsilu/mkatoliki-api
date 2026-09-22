<?php

namespace App\Services;

use App\Models\User;
use App\Support\EntityRegistry;

final class DashboardService
{
    public function __construct(private DirectoryService $directory) {}

    /**
     * @return array{counts: array<string, int>, scope: array{role: ?string, ecclesiastical_province_id: ?int, diocese_id: ?int, deanery_id: ?int, parish_id: ?int, zone_id: ?int, jumuiya_id: ?int}}
     */
    public function summary(User $user): array
    {
        $counts = [];

        foreach (array_keys(EntityRegistry::ENTITIES) as $entity) {
            $counts[$entity] = $this->directory->query($entity, user: $user)->count();
        }

        return [
            'counts' => $counts,
            'scope' => [
                'role' => $user->role?->name,
                'ecclesiastical_province_id' => $user->ecclesiastical_province_id,
                'diocese_id' => $user->diocese_id,
                'deanery_id' => $user->deanery_id,
                'parish_id' => $user->parish_id,
                'zone_id' => $user->zone_id,
                'jumuiya_id' => $user->jumuiya_id,
            ],
        ];
    }
}
