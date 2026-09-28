<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contribution_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parish_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('target_amount', 14, 2)->default(0);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
            $table->index(['parish_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_campaigns');
    }
};
