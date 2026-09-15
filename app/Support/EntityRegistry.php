<?php

namespace App\Support;

use App\Models\Association;
use App\Models\Choir;
use App\Models\Deanery;
use App\Models\Diocese;
use App\Models\DirectoryEntity;
use App\Models\EcclesiasticalProvince;
use App\Models\Family;
use App\Models\Jumuiya;
use App\Models\Member;
use App\Models\Ministry;
use App\Models\Outstation;
use App\Models\Parish;
use App\Models\Zone;

final class EntityRegistry
{
    public const ENTITIES = [
        'provinces' => ['model' => EcclesiasticalProvince::class, 'public' => true, 'parents' => []],
        'dioceses' => ['model' => Diocese::class, 'public' => true, 'parents' => ['ecclesiastical_province_id' => ['model' => EcclesiasticalProvince::class, 'nullable' => false]]],
        'deaneries' => ['model' => Deanery::class, 'public' => true, 'parents' => ['diocese_id' => ['model' => Diocese::class, 'nullable' => false]]],
        'parishes' => ['model' => Parish::class, 'public' => true, 'parents' => ['deanery_id' => ['model' => Deanery::class, 'nullable' => true]]],
        'outstations' => ['model' => Outstation::class, 'public' => true, 'parents' => ['parish_id' => ['model' => Parish::class, 'nullable' => false]]],
        'zones' => ['model' => Zone::class, 'public' => true, 'parents' => ['parish_id' => ['model' => Parish::class, 'nullable' => false], 'outstation_id' => ['model' => Outstation::class, 'nullable' => true]]],
        'jumuiyas' => ['model' => Jumuiya::class, 'public' => true, 'parents' => ['parish_id' => ['model' => Parish::class, 'nullable' => false], 'zone_id' => ['model' => Zone::class, 'nullable' => false], 'outstation_id' => ['model' => Outstation::class, 'nullable' => true]]],
        'families' => ['model' => Family::class, 'public' => false, 'parents' => ['parish_id' => ['model' => Parish::class, 'nullable' => false], 'outstation_id' => ['model' => Outstation::class, 'nullable' => true], 'zone_id' => ['model' => Zone::class, 'nullable' => true], 'jumuiya_id' => ['model' => Jumuiya::class, 'nullable' => true]]],
        'members' => ['model' => Member::class, 'public' => false, 'parents' => ['parish_id' => ['model' => Parish::class, 'nullable' => false], 'family_id' => ['model' => Family::class, 'nullable' => true], 'outstation_id' => ['model' => Outstation::class, 'nullable' => true], 'zone_id' => ['model' => Zone::class, 'nullable' => true], 'jumuiya_id' => ['model' => Jumuiya::class, 'nullable' => true]]],
        'associations' => ['model' => Association::class, 'public' => true, 'parents' => ['parish_id' => ['model' => Parish::class, 'nullable' => false], 'outstation_id' => ['model' => Outstation::class, 'nullable' => true]]],
        'choirs' => ['model' => Choir::class, 'public' => true, 'parents' => ['parish_id' => ['model' => Parish::class, 'nullable' => false], 'outstation_id' => ['model' => Outstation::class, 'nullable' => true]]],
        'ministries' => ['model' => Ministry::class, 'public' => true, 'parents' => ['parish_id' => ['model' => Parish::class, 'nullable' => false], 'outstation_id' => ['model' => Outstation::class, 'nullable' => true]]],
    ];

    public const STATUSES = ['active', 'inactive', 'pending', 'needs_verification', 'transferred', 'merged', 'suppressed'];

    public static function definition(string $entity): array
    {
        abort_unless(isset(self::ENTITIES[$entity]), 404);

        return self::ENTITIES[$entity];
    }

    public static function model(string $entity): DirectoryEntity
    {
        $class = self::definition($entity)['model'];

        return new $class;
    }

    public static function key(DirectoryEntity $model): string
    {
        foreach (self::ENTITIES as $key => $definition) {
            if ($model instanceof $definition['model']) {
                return $key;
            }
        }

        throw new \InvalidArgumentException('Unknown directory entity.');
    }

    public static function publicKeys(): array
    {
        return ['provinces', 'dioceses', 'deaneries', 'parishes', 'outstations', 'zones', 'jumuiyas'];
    }
}
