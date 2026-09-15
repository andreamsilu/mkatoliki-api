<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->hasPermission('directory.read') && $request->user()->tokenCan('directory:read'), 403);
        $request->attributes->set('directory_private', true);

        return $next($request);
    }
}
