<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateAdministrator extends Command
{
    protected $signature = 'core:create-admin {email} {--name=} {--role=super_admin} {--scope= : Diocese, deanery, or parish ID for a scoped role}';

    protected $description = 'Create an administrator with a securely prompted password and an explicit organizational scope';

    public function handle(AuditService $audit): int
    {
        $role = Role::where('name', $this->option('role'))->first();
        if (! $role) {
            $this->error('Unknown role. Run php artisan db:seed first.');

            return self::FAILURE;
        }
        $scope = match ($role->name) {
            'diocesan_admin' => ['diocese_id', 'dioceses'],
            'deanery_admin' => ['deanery_id', 'deaneries'],
            'parish_admin' => ['parish_id', 'parishes'],
            'super_admin', 'tec_admin' => null,
            default => false,
        };
        if ($scope === false || ($scope === null && $this->option('scope'))) {
            $this->error('The supplied role or scope is invalid.');

            return self::FAILURE;
        }
        $data = ['email' => strtolower($this->argument('email')), 'name' => $this->option('name') ?: $this->ask('Administrator name'), 'password' => $this->secret('Password (at least 12 characters, with mixed case, numbers, and symbols)'), 'scope' => $this->option('scope')];
        $rules = [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', Password::min(12)->mixedCase()->numbers()->symbols()],
            'scope' => $scope ? ['required', 'integer', Rule::exists($scope[1], 'id')] : ['nullable'],
        ];
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        DB::transaction(function () use ($data, $role, $scope, $audit): void {
            $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            $user->role_id = $role->id;
            if ($scope) {
                $user->{$scope[0]} = (int) $data['scope'];
            }
            $user->save();
            $audit->record(null, 'administrator.created', 'users', $user->id, new: ['role_id' => $role->id, 'scope' => $scope ? [$scope[0] => (int) $data['scope']] : null]);
        });
        $this->info('Administrator created. Use POST /api/v1/auth/login to obtain a token.');

        return self::SUCCESS;
    }
}
