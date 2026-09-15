<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['name' => 'Catholic Tanzania Core API', 'version' => config('core.version'), 'documentation' => url('/api/openapi.json')]))->name('home');
Route::get('/health', HealthController::class)->name('health');
Route::get('/api/openapi.json', fn () => response()->file(base_path('docs/api/openapi.json'), ['Content-Type' => 'application/json']))->name('openapi');
