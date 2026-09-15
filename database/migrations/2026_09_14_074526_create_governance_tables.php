<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('diocese_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('deanery_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('parish_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
        });
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('verification_records', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 64);
            $table->unsignedBigInteger('entity_id');
            $table->foreignId('source_id')->constrained('data_sources')->restrictOnDelete();
            $table->string('status', 24);
            $table->foreignId('verified_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
        });
        Schema::create('parish_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parish_id')->constrained()->restrictOnDelete();
            $table->foreignId('old_diocese_id')->constrained('dioceses')->restrictOnDelete();
            $table->foreignId('new_diocese_id')->constrained('dioceses')->restrictOnDelete();
            $table->foreignId('old_deanery_id')->constrained('deaneries')->restrictOnDelete();
            $table->foreignId('new_deanery_id')->constrained('deaneries')->restrictOnDelete();
            $table->date('effective_date');
            $table->text('reason');
            $table->foreignId('source_id')->constrained('data_sources')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('action', 64);
            $table->string('entity_type', 64);
            $table->unsignedBigInteger('entity_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->uuid('request_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['entity_type', 'entity_id']);
        });
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('data_sources')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('entity_type', 64);
            $table->string('checksum', 64);
            $table->string('status', 24)->default('staged')->index();
            $table->json('rows');
            $table->json('report');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['source_id', 'entity_type', 'checksum']);
        });
    }

    public function down(): void
    {
        foreach (['import_batches', 'audit_logs', 'parish_history', 'verification_records', 'personal_access_tokens'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            foreach (['role_id', 'diocese_id', 'deanery_id', 'parish_id'] as $column) {
                $table->dropConstrainedForeignId($column);
            }
            $table->dropColumn('is_active');
        });
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
