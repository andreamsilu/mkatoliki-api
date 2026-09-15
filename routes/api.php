<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DirectoryController;
use App\Http\Controllers\Api\V1\GovernanceController;
use App\Support\EntityRegistry;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');

    Route::middleware('throttle:public-api')->group(function (): void {
        Route::get('search', [DirectoryController::class, 'search'])->name('search');
        Route::get('parishes/{id}/context', [DirectoryController::class, 'context'])->whereNumber('id')->name('parishes.context');
        Route::get('parishes/{id}/structure', [DirectoryController::class, 'structure'])->whereNumber('id')->name('parishes.structure');
        foreach (['provinces' => ['dioceses'], 'dioceses' => ['deaneries'], 'deaneries' => ['parishes'], 'parishes' => ['outstations', 'zones', 'jumuiyas'], 'zones' => ['jumuiyas']] as $parent => $children) {
            foreach ($children as $child) {
                Route::get("$parent/{id}/$child", [DirectoryController::class, 'children'])->whereNumber('id')->defaults('entity', $parent)->defaults('child', $child)->name("$parent.$child");
            }
        }
        foreach (array_keys(array_filter(EntityRegistry::ENTITIES, fn (array $definition) => $definition['public'])) as $entity) {
            Route::get($entity, [DirectoryController::class, 'index'])->defaults('entity', $entity)->name("$entity.index");
            Route::get("$entity/{id}", [DirectoryController::class, 'show'])->whereNumber('id')->defaults('entity', $entity)->name("$entity.show");
        }
    });

    Route::middleware(['auth:sanctum', 'administrator', 'throttle:authenticated-api'])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        foreach (['families', 'members'] as $entity) {
            Route::get($entity, [DirectoryController::class, 'index'])->defaults('entity', $entity)->name("$entity.index");
            Route::get("$entity/{id}", [DirectoryController::class, 'show'])->whereNumber('id')->defaults('entity', $entity)->name("$entity.show");
            Route::post($entity, [DirectoryController::class, 'store'])->defaults('entity', $entity)->name("$entity.store");
            Route::match(['put', 'patch'], "$entity/{id}", [DirectoryController::class, 'update'])->whereNumber('id')->defaults('entity', $entity)->name("$entity.update");
        }
        Route::prefix('admin')->name('admin.')->group(function (): void {
            Route::get('data-sources', [GovernanceController::class, 'sources'])->name('sources.index');
            Route::post('data-sources', [GovernanceController::class, 'storeSource'])->name('sources.store');
            Route::get('audit-logs', [GovernanceController::class, 'audits'])->name('audits');
            Route::get('imports', [GovernanceController::class, 'imports'])->name('imports.index');
            Route::post('imports', [GovernanceController::class, 'stageImport'])->name('imports.store');
            Route::get('imports/{id}', [GovernanceController::class, 'showImport'])->whereNumber('id')->name('imports.show');
            Route::post('imports/{id}/commit', [GovernanceController::class, 'commitImport'])->whereNumber('id')->name('imports.commit');
            Route::post('parishes/{id}/transfer', [GovernanceController::class, 'transfer'])->whereNumber('id')->name('parishes.transfer');
            Route::get('parishes/{id}/history', [GovernanceController::class, 'history'])->whereNumber('id')->name('parishes.history');
            foreach (EntityRegistry::ENTITIES as $entity => $definition) {
                Route::get($entity, [DirectoryController::class, 'index'])->defaults('entity', $entity)->name("$entity.index");
                Route::get("$entity/{id}", [DirectoryController::class, 'show'])->whereNumber('id')->defaults('entity', $entity)->name("$entity.show");
                Route::post($entity, [DirectoryController::class, 'store'])->defaults('entity', $entity)->name("$entity.store");
                Route::match(['put', 'patch'], "$entity/{id}", [DirectoryController::class, 'update'])->whereNumber('id')->defaults('entity', $entity)->name("$entity.update");
                if ($definition['public']) {
                    Route::post("$entity/{id}/verify", [GovernanceController::class, 'verify'])->whereNumber('id')->defaults('entity', $entity)->name("$entity.verify");
                    Route::get("$entity/{id}/verifications", [GovernanceController::class, 'verifications'])->whereNumber('id')->defaults('entity', $entity)->name("$entity.verifications");
                }
            }
        });
    });
});
