<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdministratorSeeder extends Seeder
{
    public const EMAIL = 'admin@mkatoliki.co.tz';

    public const PASSWORD = 'Mkatoliki@123';

    private const LEGACY_EMAIL = 'admin@example.org';

    public function run(): void
    {
        DB::transaction(function (): void {
            $role = Role::query()->where('name', 'super_admin')->firstOrFail();
            $administrator = User::query()->where('email', self::EMAIL)->first()
                ?? User::query()->where('email', self::LEGACY_EMAIL)->first()
                ?? new User;

            $administrator->forceFill([
                'name' => 'Mkatoliki General Administrator',
                'email' => self::EMAIL,
                'password' => self::PASSWORD,
                'role_id' => $role->id,
                'ecclesiastical_province_id' => null,
                'diocese_id' => null,
                'deanery_id' => null,
                'parish_id' => null,
                'zone_id' => null,
                'jumuiya_id' => null,
                'member_id' => null,
                'email_verified_at' => now(),
                'is_active' => true,
            ])->save();

            $administrator->tokens()->delete();
        });

        $this->command?->info('Super administrator account seeded for '.self::EMAIL.'.');
    }
}
