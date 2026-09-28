<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_sacraments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->date('received_on')->nullable();
            $table->string('place')->nullable();
            $table->string('status', 24)->default('verified')->index();
            $table->timestamps();
            $table->index(['member_id', 'received_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_sacraments');
    }
};
