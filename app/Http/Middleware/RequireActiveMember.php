<?php

namespace App\Http\Middleware;

use App\Models\Parish;
use App\Services\DirectoryService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveMember
{
    public function __construct(private DirectoryService $directory) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $member = $user?->member;
        $parish = Parish::query()->whereKey($member?->parish_id);
        $this->directory->publiclyVisible($parish);

        abort_unless(
            $user?->is_active
                && $user->tokenCan('member:read')
                && $member?->status === 'active'
                && $parish->exists(),
            403,
        );
        $request->attributes->set('active_member', $member);

        return $next($request);
    }
}
