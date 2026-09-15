<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 60);
            $table->string('publisher')->nullable();
            $table->text('reference')->nullable();
            $table->string('version', 40)->nullable();
            $table->date('publication_date')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['name', 'version']);
        });

        Schema::create('ecclesiastical_provinces', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name')->index();
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->foreignId('source_id')->nullable()->constrained('data_sources')->restrictOnDelete();
            $table->string('verification_status', 24)->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dioceses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecclesiastical_province_id')->constrained('ecclesiastical_provinces')->restrictOnDelete();
            $table->string('code', 64)->unique();
            $table->string('name')->index();
            $table->string('name_en')->nullable();
            $table->string('type', 32);
            $table->date('established_at')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->foreignId('source_id')->nullable()->constrained('data_sources')->restrictOnDelete();
            $table->string('verification_status', 24)->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('deaneries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->restrictOnDelete();
            $table->string('code', 64)->unique();
            $table->string('name')->index();
            $table->string('name_en')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->foreignId('source_id')->nullable()->constrained('data_sources')->restrictOnDelete();
            $table->string('verification_status', 24)->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('parishes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deanery_id')->constrained('deaneries')->restrictOnDelete();
            $table->string('code', 64)->unique();
            $table->string('name')->index();
            $table->string('name_en')->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->foreignId('source_id')->nullable()->constrained('data_sources')->restrictOnDelete();
            $table->string('verification_status', 24)->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('outstations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parish_id')->constrained('parishes')->restrictOnDelete();
            $table->string('code', 64)->unique();
            $table->string('name')->index();
            $table->string('name_en')->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->foreignId('source_id')->nullable()->constrained('data_sources')->restrictOnDelete();
            $table->string('verification_status', 24)->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'parish_id']);
            $table->index(['parish_id', 'status']);
        });

        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parish_id')->constrained('parishes')->restrictOnDelete();
            $table->foreignId('outstation_id')->nullable()->constrained('outstations')->restrictOnDelete();
            $table->string('code', 64)->unique();
            $table->string('name')->index();
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->foreignId('source_id')->nullable()->constrained('data_sources')->restrictOnDelete();
            $table->string('verification_status', 24)->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'parish_id']);
            $table->index(['parish_id', 'status']);
            $table->foreign(['outstation_id', 'parish_id'], 'zones_outstation_id_parish_fk')->references(['id', 'parish_id'])->on('outstations')->restrictOnDelete();
        });

        Schema::create('jumuiyas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parish_id')->constrained('parishes')->restrictOnDelete();
            $table->foreignId('zone_id')->constrained('zones')->restrictOnDelete();
            $table->foreignId('outstation_id')->nullable()->constrained('outstations')->restrictOnDelete();
            $table->string('code', 64)->unique();
            $table->string('name')->index();
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->foreignId('source_id')->nullable()->constrained('data_sources')->restrictOnDelete();
            $table->string('verification_status', 24)->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'parish_id']);
            $table->index(['parish_id', 'status']);
            $table->foreign(['zone_id', 'parish_id'], 'jumuiyas_zone_id_parish_fk')->references(['id', 'parish_id'])->on('zones')->restrictOnDelete();
            $table->foreign(['outstation_id', 'parish_id'], 'jumuiyas_outstation_id_parish_fk')->references(['id', 'parish_id'])->on('outstations')->restrictOnDelete();
        });

        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parish_id')->constrained('parishes')->restrictOnDelete();
            $table->foreignId('outstation_id')->nullable()->constrained('outstations')->restrictOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->restrictOnDelete();
            $table->foreignId('jumuiya_id')->nullable()->constrained('jumuiyas')->restrictOnDelete();
            $table->string('family_code', 64)->unique();
            $table->string('family_name')->index();
            $table->text('address')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
            $table->unique(['id', 'parish_id']);
            $table->index(['parish_id', 'status']);
            $table->foreign(['outstation_id', 'parish_id'], 'families_outstation_id_parish_fk')->references(['id', 'parish_id'])->on('outstations')->restrictOnDelete();
            $table->foreign(['zone_id', 'parish_id'], 'families_zone_id_parish_fk')->references(['id', 'parish_id'])->on('zones')->restrictOnDelete();
            $table->foreign(['jumuiya_id', 'parish_id'], 'families_jumuiya_id_parish_fk')->references(['id', 'parish_id'])->on('jumuiyas')->restrictOnDelete();
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parish_id')->constrained('parishes')->restrictOnDelete();
            $table->foreignId('family_id')->nullable()->constrained('families')->restrictOnDelete();
            $table->foreignId('outstation_id')->nullable()->constrained('outstations')->restrictOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->restrictOnDelete();
            $table->foreignId('jumuiya_id')->nullable()->constrained('jumuiyas')->restrictOnDelete();
            $table->string('member_code', 64)->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name')->index();
            $table->string('gender', 32);
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
            $table->unique(['id', 'parish_id']);
            $table->index(['parish_id', 'status']);
            $table->foreign(['family_id', 'parish_id'], 'members_family_id_parish_fk')->references(['id', 'parish_id'])->on('families')->restrictOnDelete();
            $table->foreign(['outstation_id', 'parish_id'], 'members_outstation_id_parish_fk')->references(['id', 'parish_id'])->on('outstations')->restrictOnDelete();
            $table->foreign(['zone_id', 'parish_id'], 'members_zone_id_parish_fk')->references(['id', 'parish_id'])->on('zones')->restrictOnDelete();
            $table->foreign(['jumuiya_id', 'parish_id'], 'members_jumuiya_id_parish_fk')->references(['id', 'parish_id'])->on('jumuiyas')->restrictOnDelete();
        });

        Schema::create('associations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parish_id')->constrained('parishes')->restrictOnDelete();
            $table->foreignId('outstation_id')->nullable()->constrained('outstations')->restrictOnDelete();
            $table->string('name')->index();
            $table->string('code', 64)->unique();
            $table->text('description')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->foreignId('source_id')->nullable()->constrained('data_sources')->restrictOnDelete();
            $table->string('verification_status', 24)->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'parish_id']);
            $table->index(['parish_id', 'status']);
            $table->foreign(['outstation_id', 'parish_id'], 'associations_outstation_id_parish_fk')->references(['id', 'parish_id'])->on('outstations')->restrictOnDelete();
        });

        Schema::create('choirs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parish_id')->constrained('parishes')->restrictOnDelete();
            $table->foreignId('outstation_id')->nullable()->constrained('outstations')->restrictOnDelete();
            $table->string('name')->index();
            $table->string('code', 64)->unique();
            $table->text('description')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->foreignId('source_id')->nullable()->constrained('data_sources')->restrictOnDelete();
            $table->string('verification_status', 24)->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'parish_id']);
            $table->index(['parish_id', 'status']);
            $table->foreign(['outstation_id', 'parish_id'], 'choirs_outstation_id_parish_fk')->references(['id', 'parish_id'])->on('outstations')->restrictOnDelete();
        });

        Schema::create('ministries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parish_id')->constrained('parishes')->restrictOnDelete();
            $table->foreignId('outstation_id')->nullable()->constrained('outstations')->restrictOnDelete();
            $table->string('name')->index();
            $table->string('code', 64)->unique();
            $table->text('description')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->foreignId('source_id')->nullable()->constrained('data_sources')->restrictOnDelete();
            $table->string('verification_status', 24)->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'parish_id']);
            $table->index(['parish_id', 'status']);
            $table->foreign(['outstation_id', 'parish_id'], 'ministries_outstation_id_parish_fk')->references(['id', 'parish_id'])->on('outstations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ministries');
        Schema::dropIfExists('choirs');
        Schema::dropIfExists('associations');
        Schema::dropIfExists('members');
        Schema::dropIfExists('families');
        Schema::dropIfExists('jumuiyas');
        Schema::dropIfExists('zones');
        Schema::dropIfExists('outstations');
        Schema::dropIfExists('parishes');
        Schema::dropIfExists('deaneries');
        Schema::dropIfExists('dioceses');
        Schema::dropIfExists('ecclesiastical_provinces');
        Schema::dropIfExists('data_sources');
    }
};
