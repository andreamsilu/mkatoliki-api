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
        Schema::create('parish_mass_times', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parish_id')->constrained()->cascadeOnDelete();
            $table->string('day_label', 80);
            $table->string('time_label', 160);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_published')->default(true)->index();
            $table->timestamps();
            $table->index(['parish_id', 'display_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parish_mass_times');
    }
};
