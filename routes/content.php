<?php

use App\Http\Controllers\Api\V1\CatholicContentController as PublicContent;
use App\Http\Controllers\Api\V1\ContentEditorialController as Editorial;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.content.')->group(function (): void {
    Route::prefix('content')->middleware('throttle:content-public')->group(function (): void {
        foreach (['feed', 'posts', 'search', 'featured'] as $path) {
            Route::get($path, [PublicContent::class, 'index'])->name($path);
        }
        Route::get('categories', [PublicContent::class, 'categories'])->name('categories');
        Route::get('categories/{slug}/posts', [PublicContent::class, 'category'])->name('category');
        Route::get('daily-reflection', [PublicContent::class, 'daily'])->name('daily');
        Route::get('posts/{slug}', [PublicContent::class, 'show'])->name('show');
        Route::get('posts/{slug}/related', [PublicContent::class, 'related'])->name('related');
        Route::post('posts/{slug}/reports', [PublicContent::class, 'report'])->middleware('throttle:content-report')->name('report');
        Route::get('media/{media}', [PublicContent::class, 'media'])->whereNumber('media')->withoutMiddleware('throttle:content-public')->middleware('throttle:content-media')->name('media');
    });
    Route::prefix('me/bookmarks')->middleware(['auth:sanctum', 'content-reader', 'throttle:authenticated-api'])->group(function (): void {
        Route::get('', [PublicContent::class, 'bookmarks'])->name('bookmarks');
        Route::get('{postId}', [PublicContent::class, 'bookmarkStatus'])->whereNumber('postId')->name('bookmark.status');
        Route::post('', [PublicContent::class, 'bookmark'])->name('bookmark');
        Route::delete('{postId}', [PublicContent::class, 'unbookmark'])->whereNumber('postId')->name('unbookmark');
    });
    Route::prefix('admin/content')->middleware(['auth:sanctum', 'administrator', 'throttle:authenticated-api'])->group(function (): void {
        Route::get('posts', [Editorial::class, 'index'])->name('admin.index');
        Route::post('posts', [Editorial::class, 'store'])->name('admin.store');
        Route::get('posts/{post}', [Editorial::class, 'show'])->whereNumber('post')->name('admin.show');
        Route::patch('posts/{post}', [Editorial::class, 'update'])->whereNumber('post')->name('admin.update');
        Route::delete('posts/{post}', [Editorial::class, 'destroy'])->whereNumber('post')->name('admin.destroy');
        Route::post('posts/{post}/transition', [Editorial::class, 'transition'])->whereNumber('post')->name('admin.transition');
        Route::get('taxonomy', [Editorial::class, 'taxonomy'])->name('admin.taxonomy');
        Route::post('taxonomy/{kind}', [Editorial::class, 'saveTaxonomy'])->name('admin.taxonomy.store');
        Route::patch('taxonomy/{kind}/{id}', [Editorial::class, 'saveTaxonomy'])->whereNumber('id')->name('admin.taxonomy.update');
        Route::get('media', [Editorial::class, 'mediaIndex'])->name('admin.media');
        Route::get('media/{media}/preview', [Editorial::class, 'previewMedia'])->whereNumber('media')->name('admin.media.preview');
        Route::post('media', [Editorial::class, 'upload'])->name('admin.media.upload');
        Route::get('reports', [Editorial::class, 'reports'])->name('admin.reports');
        Route::patch('reports/{report}', [Editorial::class, 'resolve'])->whereNumber('report')->name('admin.reports.resolve');
    });
});
