<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveContentReader
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->member_id && $request->user()->tokenCan('member:read')) {
            return app(RequireActiveMember::class)->handle($request, $next);
        }
        return app(RequireActiveAdministrator::class)->handle($request, $next);
    }
}
