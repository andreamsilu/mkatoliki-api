<?php

namespace App\Providers;

use App\Policies\DirectoryPolicy;
use App\Support\EntityRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        foreach (EntityRegistry::ENTITIES as $definition) {
            Gate::policy($definition['model'], DirectoryPolicy::class);
        }
        foreach (['sources.manage', 'imports.manage', 'audit.read'] as $permission) {
            Gate::define($permission, fn ($user) => $user->hasPermission($permission) && $user->tokenCan($permission === 'audit.read' ? 'directory:read' : 'directory:write'));
        }
        RateLimiter::for('public-api', fn (Request $request) => Limit::perMinute(config('core.public_rate_limit'))->by($request->ip()));
        RateLimiter::for('authenticated-api', fn (Request $request) => Limit::perMinute(config('core.authenticated_rate_limit'))->by($request->user()?->id ?? $request->ip()));
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by('login:'.$request->ip().':'.hash('sha256', strtolower((string) $request->input('email')))),
            Limit::perMinute(20)->by('login-ip:'.$request->ip()),
        ]);
    }
}
