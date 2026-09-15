<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('parishes', function (Blueprint $table): void {
            $table->dropForeign(['deanery_id']);
            $table->foreignId('deanery_id')->nullable()->change();
            $table->foreign('deanery_id')->references('id')->on('deaneries')->restrictOnDelete();
        });

        Schema::table('parish_history', function (Blueprint $table): void {
            $table->dropForeign(['old_diocese_id']);
            $table->dropForeign(['old_deanery_id']);
            $table->foreignId('old_diocese_id')->nullable()->change();
            $table->foreignId('old_deanery_id')->nullable()->change();
            $table->foreign('old_diocese_id')->references('id')->on('dioceses')->restrictOnDelete();
            $table->foreign('old_deanery_id')->references('id')->on('deaneries')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parish_history', function (Blueprint $table): void {
            $table->dropForeign(['old_diocese_id']);
            $table->dropForeign(['old_deanery_id']);
            $table->foreignId('old_diocese_id')->nullable(false)->change();
            $table->foreignId('old_deanery_id')->nullable(false)->change();
            $table->foreign('old_diocese_id')->references('id')->on('dioceses')->restrictOnDelete();
            $table->foreign('old_deanery_id')->references('id')->on('deaneries')->restrictOnDelete();
        });
        Schema::table('parishes', function (Blueprint $table): void {
            $table->dropForeign(['deanery_id']);
            $table->foreignId('deanery_id')->nullable(false)->change();
            $table->foreign('deanery_id')->references('id')->on('deaneries')->restrictOnDelete();
        });
    }
};
