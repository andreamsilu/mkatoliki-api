<?php

use App\Http\Controllers\Api\V1\AdministratorController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ContributionManagementController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DirectoryController;
use App\Http\Controllers\Api\V1\GovernanceController;
use App\Http\Controllers\Api\V1\MemberAuthController;
use App\Http\Controllers\Api\V1\MemberPortalController;
use App\Http\Controllers\Api\V1\ParishAnnouncementController;
use App\Http\Controllers\Api\V1\ParishContentController;
use App\Http\Controllers\Api\V1\ParishEventController;
use App\Http\Controllers\Api\V1\ParishMassTimeController;
use App\Http\Controllers\Api\V1\ParishNotificationController;
use App\Http\Controllers\Api\V1\ParishProjectController;
use App\Http\Controllers\Api\V1\ParishReportController;
use App\Http\Controllers\Api\V1\SacramentManagementController;
use App\Support\EntityRegistry;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');
    Route::post('member/auth/login', [MemberAuthController::class, 'login'])->middleware('throttle:login')->name('member.auth.login');

    Route::middleware('throttle:public-api')->group(function (): void {
        Route::post('search', [DirectoryController::class, 'search'])->name('search');
        Route::post('parishes/{id}/context', [DirectoryController::class, 'context'])->whereNumber('id')->name('parishes.context');
        Route::post('parishes/{id}/structure', [DirectoryController::class, 'structure'])->whereNumber('id')->name('parishes.structure');
        Route::get('parishes', [DirectoryController::class, 'index'])->defaults('entity', 'parishes')->name('parishes.list');
        Route::get('parishes/{id}', [DirectoryController::class, 'show'])->whereNumber('id')->defaults('entity', 'parishes')->name('parishes.detail');
        Route::get('parishes/{id}/content', ParishContentController::class)->whereNumber('id')->name('parishes.content');
        Route::get('parishes/{id}/zones', [DirectoryController::class, 'children'])->whereNumber('id')->defaults('entity', 'parishes')->defaults('child', 'zones')->name('parishes.zones.list');
        Route::get('zones/{id}', [DirectoryController::class, 'show'])->whereNumber('id')->defaults('entity', 'zones')->name('zones.detail');
        Route::get('zones/{id}/jumuiyas', [DirectoryController::class, 'children'])->whereNumber('id')->defaults('entity', 'zones')->defaults('child', 'jumuiyas')->name('zones.jumuiyas.list');
        Route::get('jumuiyas/{id}', [DirectoryController::class, 'show'])->whereNumber('id')->defaults('entity', 'jumuiyas')->name('jumuiyas.detail');
        foreach (['provinces' => ['dioceses'], 'dioceses' => ['deaneries'], 'deaneries' => ['parishes'], 'parishes' => ['outstations', 'jumuiyas']] as $parent => $children) {
            foreach ($children as $child) {
                Route::post("$parent/{id}/$child", [DirectoryController::class, 'children'])->whereNumber('id')->defaults('entity', $parent)->defaults('child', $child)->name("$parent.$child");
            }
        }
        foreach (array_keys(array_filter(EntityRegistry::ENTITIES, fn (array $definition) => $definition['public'])) as $entity) {
            Route::post("$entity/search", [DirectoryController::class, 'index'])->defaults('entity', $entity)->name("$entity.index");
            Route::post("$entity/{id}", [DirectoryController::class, 'show'])->whereNumber('id')->defaults('entity', $entity)->name("$entity.show");
        }
    });

    Route::prefix('member')->name('member.')->middleware(['auth:sanctum', 'member', 'throttle:authenticated-api'])->group(function (): void {
        Route::get('auth/me', [MemberAuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [MemberAuthController::class, 'logout'])->name('auth.logout');
        Route::get('dashboard', [MemberPortalController::class, 'dashboard'])->name('dashboard');
        Route::match(['put', 'patch'], 'profile', [MemberPortalController::class, 'updateProfile'])->name('profile.update');
        Route::put('password', [MemberPortalController::class, 'updatePassword'])->name('password.update');
        Route::patch('notifications/{notification}/read', [MemberPortalController::class, 'markNotificationRead'])->whereNumber('notification')->name('notifications.read');
        Route::post('contributions/{campaign}/payment-requests', [MemberPortalController::class, 'requestContributionPayment'])->whereNumber('campaign')->name('contributions.payment-requests.store');
        Route::post('sacraments/{sacrament}/requests', [MemberPortalController::class, 'requestSacramentService'])->whereNumber('sacrament')->name('sacraments.requests.store');
        Route::get('reports/{type}', [MemberPortalController::class, 'report'])->whereIn('type', ['contributions', 'sacraments', 'membership', 'annual'])->name('reports.show');
    });

    Route::middleware(['auth:sanctum', 'administrator', 'throttle:authenticated-api'])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('admin/dashboard', DashboardController::class)->name('admin.dashboard');

        Route::post('parishes', [DirectoryController::class, 'store'])->defaults('entity', 'parishes')->name('operations.parishes.store');
        Route::match(['put', 'patch'], 'parishes/{id}', [DirectoryController::class, 'update'])->whereNumber('id')->defaults('entity', 'parishes')->name('operations.parishes.update');
        Route::delete('parishes/{id}', [DirectoryController::class, 'destroy'])->whereNumber('id')->defaults('entity', 'parishes')->name('operations.parishes.destroy');
        Route::post('parishes/{id}/zones', [DirectoryController::class, 'storeChild'])->whereNumber('id')->defaults('parent', 'parishes')->defaults('entity', 'zones')->name('operations.parishes.zones.store');
        Route::match(['put', 'patch'], 'zones/{id}', [DirectoryController::class, 'update'])->whereNumber('id')->defaults('entity', 'zones')->name('operations.zones.update');
        Route::delete('zones/{id}', [DirectoryController::class, 'destroy'])->whereNumber('id')->defaults('entity', 'zones')->name('operations.zones.destroy');
        Route::post('zones/{id}/jumuiyas', [DirectoryController::class, 'storeChild'])->whereNumber('id')->defaults('parent', 'zones')->defaults('entity', 'jumuiyas')->name('operations.zones.jumuiyas.store');
        Route::match(['put', 'patch'], 'jumuiyas/{id}', [DirectoryController::class, 'update'])->whereNumber('id')->defaults('entity', 'jumuiyas')->name('operations.jumuiyas.update');
        Route::delete('jumuiyas/{id}', [DirectoryController::class, 'destroy'])->whereNumber('id')->defaults('entity', 'jumuiyas')->name('operations.jumuiyas.destroy');

        Route::get('jumuiyas/{id}/families', [DirectoryController::class, 'children'])->whereNumber('id')->defaults('entity', 'jumuiyas')->defaults('child', 'families')->name('operations.jumuiyas.families.list');
        Route::post('jumuiyas/{id}/families', [DirectoryController::class, 'storeChild'])->whereNumber('id')->defaults('parent', 'jumuiyas')->defaults('entity', 'families')->name('operations.jumuiyas.families.store');
        Route::get('jumuiyas/{id}/members', [DirectoryController::class, 'children'])->whereNumber('id')->defaults('entity', 'jumuiyas')->defaults('child', 'members')->name('operations.jumuiyas.members.list');
        Route::get('families/{id}', [DirectoryController::class, 'show'])->whereNumber('id')->defaults('entity', 'families')->name('operations.families.detail');
        Route::get('families/{id}/members', [DirectoryController::class, 'children'])->whereNumber('id')->defaults('entity', 'families')->defaults('child', 'members')->name('operations.families.members.list');
        Route::post('families/{id}/members', [DirectoryController::class, 'storeChild'])->whereNumber('id')->defaults('parent', 'families')->defaults('entity', 'members')->name('operations.families.members.store');
        Route::delete('families/{id}', [DirectoryController::class, 'destroy'])->whereNumber('id')->defaults('entity', 'families')->name('operations.families.destroy');
        Route::get('parishes/{id}/members', [DirectoryController::class, 'children'])->whereNumber('id')->defaults('entity', 'parishes')->defaults('child', 'members')->name('operations.parishes.members.list');
        Route::post('parishes/{id}/members', [DirectoryController::class, 'storeChild'])->whereNumber('id')->defaults('parent', 'parishes')->defaults('entity', 'members')->name('operations.parishes.members.store');
        Route::get('members/{id}', [DirectoryController::class, 'show'])->whereNumber('id')->defaults('entity', 'members')->name('operations.members.detail');

        foreach (['families', 'members'] as $entity) {
            Route::post("$entity/search", [DirectoryController::class, 'index'])->defaults('entity', $entity)->name("$entity.index");
            Route::post("$entity/{id}", [DirectoryController::class, 'show'])->whereNumber('id')->defaults('entity', $entity)->name("$entity.show");
            Route::post($entity, [DirectoryController::class, 'store'])->defaults('entity', $entity)->name("$entity.store");
            Route::match(['put', 'patch'], "$entity/{id}", [DirectoryController::class, 'update'])->whereNumber('id')->defaults('entity', $entity)->name("$entity.update");
        }
        Route::prefix('admin')->name('admin.')->group(function (): void {
            Route::get('administrators', [AdministratorController::class, 'index'])->name('administrators.index');
            Route::post('administrators', [AdministratorController::class, 'store'])->name('administrators.store');
            Route::patch('administrators/{administrator}', [AdministratorController::class, 'update'])->whereNumber('administrator')->name('administrators.update');

            Route::get('parishes/{parish}/mass-times', [ParishMassTimeController::class, 'index'])->whereNumber('parish')->name('parishes.mass-times.index');
            Route::post('parishes/{parish}/mass-times', [ParishMassTimeController::class, 'store'])->whereNumber('parish')->name('parishes.mass-times.store');
            Route::patch('mass-times/{massTime}', [ParishMassTimeController::class, 'update'])->whereNumber('massTime')->name('mass-times.update');
            Route::delete('mass-times/{massTime}', [ParishMassTimeController::class, 'destroy'])->whereNumber('massTime')->name('mass-times.destroy');

            Route::get('parishes/{parish}/announcements', [ParishAnnouncementController::class, 'index'])->whereNumber('parish')->name('parishes.announcements.index');
            Route::post('parishes/{parish}/announcements', [ParishAnnouncementController::class, 'store'])->whereNumber('parish')->name('parishes.announcements.store');
            Route::patch('announcements/{announcement}', [ParishAnnouncementController::class, 'update'])->whereNumber('announcement')->name('announcements.update');
            Route::delete('announcements/{announcement}', [ParishAnnouncementController::class, 'destroy'])->whereNumber('announcement')->name('announcements.destroy');

            Route::get('parishes/{parish}/events', [ParishEventController::class, 'index'])->whereNumber('parish')->name('parishes.events.index');
            Route::post('parishes/{parish}/events', [ParishEventController::class, 'store'])->whereNumber('parish')->name('parishes.events.store');
            Route::patch('events/{event}', [ParishEventController::class, 'update'])->whereNumber('event')->name('events.update');
            Route::delete('events/{event}', [ParishEventController::class, 'destroy'])->whereNumber('event')->name('events.destroy');

            Route::get('parishes/{parish}/projects', [ParishProjectController::class, 'index'])->whereNumber('parish')->name('parishes.projects.index');
            Route::post('parishes/{parish}/projects', [ParishProjectController::class, 'store'])->whereNumber('parish')->name('parishes.projects.store');
            Route::patch('projects/{project}', [ParishProjectController::class, 'update'])->whereNumber('project')->name('projects.update');
            Route::delete('projects/{project}', [ParishProjectController::class, 'destroy'])->whereNumber('project')->name('projects.destroy');

            Route::get('parishes/{parish}/contribution-campaigns', [ContributionManagementController::class, 'campaigns'])->whereNumber('parish')->name('parishes.contribution-campaigns.index');
            Route::post('parishes/{parish}/contribution-campaigns', [ContributionManagementController::class, 'storeCampaign'])->whereNumber('parish')->name('parishes.contribution-campaigns.store');
            Route::patch('contribution-campaigns/{campaign}', [ContributionManagementController::class, 'updateCampaign'])->whereNumber('campaign')->name('contribution-campaigns.update');
            Route::delete('contribution-campaigns/{campaign}', [ContributionManagementController::class, 'destroyCampaign'])->whereNumber('campaign')->name('contribution-campaigns.destroy');
            Route::get('parishes/{parish}/contribution-payments', [ContributionManagementController::class, 'payments'])->whereNumber('parish')->name('parishes.contribution-payments.index');
            Route::patch('contribution-payments/{payment}', [ContributionManagementController::class, 'reviewPayment'])->whereNumber('payment')->name('contribution-payments.review');

            Route::get('parishes/{parish}/sacraments', [SacramentManagementController::class, 'sacraments'])->whereNumber('parish')->name('parishes.sacraments.index');
            Route::post('parishes/{parish}/sacraments', [SacramentManagementController::class, 'storeSacrament'])->whereNumber('parish')->name('parishes.sacraments.store');
            Route::patch('sacraments/{sacrament}', [SacramentManagementController::class, 'updateSacrament'])->whereNumber('sacrament')->name('sacraments.update');
            Route::delete('sacraments/{sacrament}', [SacramentManagementController::class, 'destroySacrament'])->whereNumber('sacrament')->name('sacraments.destroy');
            Route::get('parishes/{parish}/service-requests', [SacramentManagementController::class, 'serviceRequests'])->whereNumber('parish')->name('parishes.service-requests.index');
            Route::patch('service-requests/{serviceRequest}', [SacramentManagementController::class, 'reviewServiceRequest'])->whereNumber('serviceRequest')->name('service-requests.review');

            Route::get('parishes/{parish}/notifications', [ParishNotificationController::class, 'index'])->whereNumber('parish')->name('parishes.notifications.index');
            Route::post('parishes/{parish}/notifications', [ParishNotificationController::class, 'store'])->whereNumber('parish')->name('parishes.notifications.store');
            Route::delete('notifications/{notification}', [ParishNotificationController::class, 'destroy'])->whereNumber('notification')->name('notifications.destroy');
            Route::get('parishes/{parish}/reports/{type}', ParishReportController::class)->whereNumber('parish')->whereIn('type', ['membership', 'contributions', 'sacraments', 'annual'])->name('parishes.reports.show');

            Route::post('parishes/{id}/structure', [DirectoryController::class, 'structure'])->whereNumber('id')->name('parishes.structure');
            Route::post('data-sources/search', [GovernanceController::class, 'sources'])->name('sources.index');
            Route::post('data-sources', [GovernanceController::class, 'storeSource'])->name('sources.store');
            Route::post('audit-logs/search', [GovernanceController::class, 'audits'])->name('audits');
            Route::post('imports/search', [GovernanceController::class, 'imports'])->name('imports.index');
            Route::post('imports', [GovernanceController::class, 'stageImport'])->name('imports.store');
            Route::post('imports/{id}', [GovernanceController::class, 'showImport'])->whereNumber('id')->name('imports.show');
            Route::post('imports/{id}/commit', [GovernanceController::class, 'commitImport'])->whereNumber('id')->name('imports.commit');
            Route::post('parishes/{id}/transfer', [GovernanceController::class, 'transfer'])->whereNumber('id')->name('parishes.transfer');
            Route::post('parishes/{id}/history', [GovernanceController::class, 'history'])->whereNumber('id')->name('parishes.history');
            foreach (array_keys(EntityRegistry::ENTITIES) as $entity) {
                Route::post("$entity/search", [DirectoryController::class, 'index'])->defaults('entity', $entity)->name("$entity.index");
                Route::post("$entity/{id}", [DirectoryController::class, 'show'])->whereNumber('id')->defaults('entity', $entity)->name("$entity.show");
                Route::post($entity, [DirectoryController::class, 'store'])->defaults('entity', $entity)->name("$entity.store");
                Route::match(['put', 'patch'], "$entity/{id}", [DirectoryController::class, 'update'])->whereNumber('id')->defaults('entity', $entity)->name("$entity.update");
            }
        });
    });
});
