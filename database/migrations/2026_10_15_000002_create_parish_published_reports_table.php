<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parish_published_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parish_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('summary');
            $table->longText('body')->nullable();
            $table->date('report_date')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->unsignedBigInteger('document_size')->nullable();
            $table->timestamps();
            $table->index(['parish_id', 'is_published', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parish_published_reports');
    }
};
