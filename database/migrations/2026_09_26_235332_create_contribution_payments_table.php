<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contribution_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('contribution_campaign_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('payment_method', 32);
            $table->string('reference', 64)->unique();
            $table->string('status', 24)->default('pending')->index();
            $table->string('gateway_reference', 128)->nullable();
            $table->string('receipt_number', 64)->nullable()->unique();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['member_id', 'contribution_campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_payments');
    }
};
