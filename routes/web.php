<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['name' => 'Catholic Tanzania Core API', 'version' => config('core.version'), 'documentation' => route('swagger'), 'openapi' => route('openapi'), 'consumer_documentation' => route('docs')]))->name('home');
Route::get('/health', HealthController::class)->name('health');
Route::get('/api/openapi.json', fn () => response()->file(base_path('docs/api/openapi.json'), ['Content-Type' => 'application/json']))->name('openapi');
Route::view('/swagger', 'swagger')->name('swagger');
Route::get('/docs', fn () => redirect()->route('docs.index'))->name('docs');
Route::get('/docs/index.html', fn () => response()->file(base_path('docs/api/index.html'), ['Content-Type' => 'text/html; charset=UTF-8']))->name('docs.index');
Route::get('/docs/consumer-guide.md', fn () => response()->download(base_path('docs/api/consumer-guide.md'), 'consumer-guide.md', ['Content-Type' => 'text/markdown; charset=UTF-8']))->name('docs.guide');
Route::get('/docs/openapi.json', fn () => response()->file(base_path('docs/api/openapi.json'), ['Content-Type' => 'application/json']))->name('docs.openapi');
