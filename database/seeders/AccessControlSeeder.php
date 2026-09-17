<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect(['directory.read', 'directory.write', 'directory.transfer', 'sources.manage', 'imports.manage', 'audit.read'])
            ->mapWithKeys(fn (string $name) => [$name => Permission::firstOrCreate(['name' => $name])->id]);

        foreach (['super_admin', 'tec_admin', 'diocesan_admin', 'deanery_admin', 'parish_admin'] as $name) {
            $role = Role::firstOrCreate(['name' => $name]);
            $allowed = in_array($name, ['super_admin', 'tec_admin'], true)
                ? $permissions->values()
                : $permissions->only(['directory.read', 'directory.write', 'directory.transfer'])->values();
            $role->permissions()->sync($allowed);
        }
    }
}
