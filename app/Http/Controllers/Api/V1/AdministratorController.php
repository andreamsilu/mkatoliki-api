<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdministratorRequest;
use App\Http\Requests\UpdateAdministratorRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AdministratorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('administrators.manage');
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'q' => ['sometimes', 'string', 'min:2', 'max:100'],
            'parish_id' => ['sometimes', 'integer', 'exists:parishes,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $administrators = User::query()
            ->whereHas('role', fn (Builder $query) => $query->where('name', 'parish_admin'))
            ->with(['role:id,name', 'parish:id,code,name'])
            ->when(isset($validated['q']), function (Builder $query) use ($validated): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $validated['q']).'%';
                $query->where(fn (Builder $query) => $query->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when(isset($validated['parish_id']), fn (Builder $query) => $query->where('parish_id', $validated['parish_id']))
            ->when(array_key_exists('is_active', $validated), fn (Builder $query) => $query->where('is_active', $validated['is_active']))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(perPage: $request->integer('per_page', 25), page: $request->integer('page', 1));

        return ApiResponse::page($administrators, $administrators->getCollection()->map(fn (User $user): array => $this->administrator($user)));
    }

    public function store(StoreAdministratorRequest $request, AuditService $audit): JsonResponse
    {
        $attributes = $request->validated();
        $administrator = DB::transaction(function () use ($attributes, $audit, $request): User {
            $administrator = new User([
                'name' => $attributes['name'],
                'email' => mb_strtolower($attributes['email']),
                'password' => $attributes['password'],
            ]);
            $administrator->role_id = Role::query()->where('name', 'parish_admin')->value('id');
            $administrator->parish_id = $attributes['parish_id'];
            $administrator->is_active = $attributes['is_active'] ?? true;
            $administrator->save();
            $audit->record($request->user(), 'administrator.created', 'users', $administrator->id, new: [
                'email' => $administrator->email,
                'role' => 'parish_admin',
                'parish_id' => $administrator->parish_id,
                'is_active' => $administrator->is_active,
            ]);

            return $administrator;
        });

        return ApiResponse::success($this->administrator($administrator->load(['role:id,name', 'parish:id,code,name'])), 201);
    }

    public function update(UpdateAdministratorRequest $request, User $administrator, AuditService $audit): JsonResponse
    {
        abort_unless($administrator->role?->name === 'parish_admin', 404);
        $attributes = $request->validated();
        $before = ['is_active' => $administrator->is_active];

        DB::transaction(function () use ($administrator, $attributes, $audit, $before, $request): void {
            if (array_key_exists('is_active', $attributes)) {
                $administrator->is_active = $attributes['is_active'];
            }
            if (array_key_exists('password', $attributes)) {
                $administrator->password = $attributes['password'];
            }
            $administrator->save();
            if (($attributes['is_active'] ?? true) === false || array_key_exists('password', $attributes)) {
                $administrator->tokens()->delete();
            }
            $audit->record($request->user(), 'administrator.updated', 'users', $administrator->id, $before, [
                'is_active' => $administrator->is_active,
                'password' => array_key_exists('password', $attributes) ? 'changed' : 'unchanged',
            ]);
        });

        return ApiResponse::success($this->administrator($administrator->load(['role:id,name', 'parish:id,code,name'])));
    }

    /**
     * @return array<string, mixed>
     */
    private function administrator(User $administrator): array
    {
        return [
            'id' => $administrator->id,
            'name' => $administrator->name,
            'email' => $administrator->email,
            'role' => $administrator->role?->name,
            'parish_id' => $administrator->parish_id,
            'parish' => $administrator->parish ? [
                'id' => $administrator->parish->id,
                'code' => $administrator->parish->code,
                'name' => $administrator->parish->name,
            ] : null,
            'is_active' => (bool) $administrator->is_active,
            'created_at' => $administrator->created_at?->toIso8601String(),
        ];
    }
}
