<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $database = $cache = 'ok';
        try {
            DB::select('SELECT 1');
        } catch (Throwable) {
            $database = 'unavailable';
        }
        try {
            $key = 'health:'.Str::uuid();
            Cache::put($key, 'ok', 10);
            $cache = Cache::get($key) === 'ok' ? 'ok' : 'unavailable';
            Cache::forget($key);
        } catch (Throwable) {
            $cache = 'unavailable';
        }
        $healthy = $database === 'ok' && $cache === 'ok';

        return response()->json(['status' => $healthy ? 'ok' : 'degraded', 'database' => $database, 'cache' => $cache, 'version' => config('core.version')], $healthy ? 200 : 503)->header('Cache-Control', 'no-store');
    }
}
