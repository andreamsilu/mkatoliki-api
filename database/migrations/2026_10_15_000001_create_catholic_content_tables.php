<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon', 50);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('content_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });
        Schema::create('content_media', function (Blueprint $table): void {
            $table->id();
            $table->string('media_type', 20);
            $table->string('storage_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('alt_text')->nullable();
            $table->string('source_name')->nullable();
            $table->text('source_url')->nullable();
            $table->string('rights_status', 30);
            $table->text('license_details')->nullable();
            $table->text('attribution')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('content_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('content_type', 30)->index();
            $table->string('format', 20)->default('article');
            $table->string('status', 20)->default('draft')->index();
            $table->boolean('featured')->default(false);
            $table->string('season', 30)->nullable();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('cover_media_id')->nullable()->constrained('content_media')->restrictOnDelete();
            $table->foreignId('audio_media_id')->nullable()->constrained('content_media')->restrictOnDelete();
            $table->string('source_type', 20)->default('editorial');
            $table->string('source_name')->nullable();
            $table->text('source_url')->nullable();
            $table->string('rights_status', 30)->default('original');
            $table->text('license_details')->nullable();
            $table->string('composer')->nullable();
            $table->string('choir')->nullable();
            $table->unsignedSmallInteger('reading_time_minutes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'published_at', 'id']);
            $table->index(['status', 'scheduled_at']);
        });
        Schema::create('content_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_post_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('title');
            $table->text('summary');
            $table->longText('body');
            $table->string('scripture_reference')->nullable();
            $table->text('practical_action')->nullable();
            $table->text('closing_prayer')->nullable();
            $table->timestamps();
            $table->unique(['content_post_id', 'locale']);
            $table->index(['locale', 'content_post_id']);
        });
        foreach (['categories' => 'category', 'tags' => 'tag'] as $plural => $singular) {
            Schema::create('content_post_'.$plural, function (Blueprint $table) use ($plural, $singular): void {
                $table->foreignId('content_post_id')->constrained()->cascadeOnDelete();
                $table->foreignId('content_'.$singular.'_id')->constrained('content_'.$plural)->restrictOnDelete();
                $table->primary(['content_post_id', 'content_'.$singular.'_id']);
            });
        }
        Schema::create('content_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 30);
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at');
            $table->timestamps();
        });
        Schema::create('daily_reflections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_post_id')->constrained()->cascadeOnDelete();
            $table->date('reflection_date');
            $table->string('locale', 10);
            $table->timestamps();
            $table->unique(['reflection_date', 'locale']);
            $table->unique(['content_post_id', 'locale']);
        });
        Schema::create('content_bookmarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_post_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'content_post_id']);
        });
        Schema::create('content_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_post_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['content_reports', 'content_bookmarks', 'daily_reflections', 'content_reviews', 'content_post_tags', 'content_post_categories', 'content_translations', 'content_posts', 'content_media', 'content_tags', 'content_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
