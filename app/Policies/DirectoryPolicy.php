<?php

namespace App\Policies;

use App\Models\DirectoryEntity;
use App\Models\User;
use App\Services\OrganizationScope;

class DirectoryPolicy
{
    public function __construct(private OrganizationScope $scope) {}

    public function view(User $user, DirectoryEntity $entity): bool
    {
        return $user->hasPermission('directory.read') && $user->tokenCan('directory:read') && $this->scope->contains($user, $entity);
    }

    public function create(User $user, DirectoryEntity $entity): bool
    {
        return $user->hasPermission('directory.write') && $user->tokenCan('directory:write') && $this->scope->canCreate($user, $entity);
    }

    public function update(User $user, DirectoryEntity $entity): bool
    {
        return $user->hasPermission('directory.write') && $user->tokenCan('directory:write') && $this->scope->contains($user, $entity);
    }

    public function transfer(User $user, DirectoryEntity $entity): bool
    {
        return $user->hasPermission('directory.transfer') && $this->update($user, $entity);
    }
}
