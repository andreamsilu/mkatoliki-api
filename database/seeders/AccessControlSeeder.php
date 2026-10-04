<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect(['directory.read', 'directory.write', 'directory.transfer', 'sources.manage', 'imports.manage', 'audit.read', 'administrators.manage'])
            ->mapWithKeys(fn (string $name) => [$name => Permission::firstOrCreate(['name' => $name])->id]);

        foreach (['super_admin', 'tec_admin', 'province_admin', 'diocesan_admin', 'deanery_admin', 'parish_admin', 'zone_leader', 'jumuiya_leader'] as $name) {
            $role = Role::firstOrCreate(['name' => $name]);
            $allowed = $name === 'super_admin'
                ? $permissions->values()
                : ($name === 'tec_admin'
                    ? $permissions->except('administrators.manage')->values()
                    : $permissions->only(in_array($name, ['zone_leader', 'jumuiya_leader'], true)
                        ? ['directory.read', 'directory.write']
                        : ['directory.read', 'directory.write', 'directory.transfer'])->values());
            $role->permissions()->sync($allowed);
        }
    }
}
