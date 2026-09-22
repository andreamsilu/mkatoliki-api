<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('ecclesiastical_province_id')->nullable()->after('role_id')->constrained('ecclesiastical_provinces')->restrictOnDelete();
            $table->foreignId('zone_id')->nullable()->after('parish_id')->constrained()->restrictOnDelete();
            $table->foreignId('jumuiya_id')->nullable()->after('zone_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('jumuiya_id');
            $table->dropConstrainedForeignId('zone_id');
            $table->dropConstrainedForeignId('ecclesiastical_province_id');
        });
    }
};
