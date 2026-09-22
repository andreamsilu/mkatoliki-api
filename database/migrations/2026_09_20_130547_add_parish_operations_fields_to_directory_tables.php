<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parishes', function (Blueprint $table) {
            $table->string('patron_saint')->nullable()->after('name_en');
            $table->date('established_at')->nullable()->after('patron_saint');
            $table->softDeletes();
        });

        Schema::table('zones', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->after('description');
            $table->string('email')->nullable()->after('phone');
            $table->unsignedBigInteger('leader_member_id')->nullable()->after('email');
            $table->softDeletes();
            $table->foreign(['leader_member_id', 'parish_id'], 'zones_leader_member_parish_fk')
                ->references(['id', 'parish_id'])->on('members')->restrictOnDelete();
        });

        Schema::table('jumuiyas', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->after('description');
            $table->string('email')->nullable()->after('phone');
            $table->unsignedBigInteger('leader_member_id')->nullable()->after('email');
            $table->unsignedBigInteger('secretary_member_id')->nullable()->after('leader_member_id');
            $table->softDeletes();
            $table->foreign(['leader_member_id', 'parish_id'], 'jumuiyas_leader_member_parish_fk')
                ->references(['id', 'parish_id'])->on('members')->restrictOnDelete();
            $table->foreign(['secretary_member_id', 'parish_id'], 'jumuiyas_secretary_member_parish_fk')
                ->references(['id', 'parish_id'])->on('members')->restrictOnDelete();
        });

        Schema::table('families', function (Blueprint $table) {
            $table->string('email')->nullable()->after('phone');
            $table->unsignedBigInteger('head_member_id')->nullable()->after('email');
            $table->softDeletes();
            $table->foreign(['head_member_id', 'parish_id'], 'families_head_member_parish_fk')
                ->references(['id', 'parish_id'])->on('members')->restrictOnDelete();
        });

        Schema::table('members', function (Blueprint $table) {
            $table->string('family_relationship', 64)->nullable()->after('family_id');
            $table->date('membership_started_at')->nullable()->after('date_of_birth');
        });
    }

    public function down(): void
    {
        Schema::table('families', function (Blueprint $table) {
            $table->dropForeign('families_head_member_parish_fk');
            $table->dropColumn(['email', 'head_member_id', 'deleted_at']);
        });

        Schema::table('jumuiyas', function (Blueprint $table) {
            $table->dropForeign('jumuiyas_leader_member_parish_fk');
            $table->dropForeign('jumuiyas_secretary_member_parish_fk');
            $table->dropColumn(['phone', 'email', 'leader_member_id', 'secretary_member_id', 'deleted_at']);
        });

        Schema::table('zones', function (Blueprint $table) {
            $table->dropForeign('zones_leader_member_parish_fk');
            $table->dropColumn(['phone', 'email', 'leader_member_id', 'deleted_at']);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['family_relationship', 'membership_started_at']);
        });

        Schema::table('parishes', function (Blueprint $table) {
            $table->dropColumn(['patron_saint', 'established_at', 'deleted_at']);
        });
    }
};
