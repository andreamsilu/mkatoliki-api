<?php

namespace App\Policies;

use App\Models\ContentPost;
use App\Models\User;

class ContentPostPolicy
{
    public function permission(User $user, string $permission, bool $write = true): bool
    {
        return $user->hasPermission($permission) && $user->tokenCan($write ? 'directory:write' : 'directory:read');
    }

    public function viewAny(User $user): bool
    {
        return $this->permission($user, 'content.manage', false);
    }

    public function view(User $user, ContentPost $post): bool
    {
        return $this->viewAny($user) && ((int) $post->author_id === (int) $user->id || $this->permission($user, 'content.review', false));
    }

    public function create(User $user): bool
    {
        return $this->permission($user, 'content.manage');
    }

    public function update(User $user, ContentPost $post): bool
    {
        return $this->create($user) && ((int) $post->author_id === (int) $user->id || $this->permission($user, 'content.review'));
    }

    public function review(User $user, ?ContentPost $post = null): bool
    {
        return $this->permission($user, 'content.review');
    }

    public function viewReviews(User $user, ?ContentPost $post = null): bool
    {
        return $this->permission($user, 'content.review', false);
    }

    public function publish(User $user, ?ContentPost $post = null): bool
    {
        return $this->permission($user, 'content.publish');
    }
}
