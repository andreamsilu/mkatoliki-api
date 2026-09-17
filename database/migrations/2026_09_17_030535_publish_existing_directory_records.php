<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['ecclesiastical_provinces', 'dioceses', 'deaneries', 'parishes', 'outstations', 'zones', 'jumuiyas', 'associations', 'choirs', 'ministries'] as $table) {
            DB::table($table)->whereIn('status', ['pending', 'needs_verification'])->update(['status' => 'active']);
            DB::table($table)->update(['verification_status' => 'verified', 'verified_at' => now()]);
        }

        DB::table('permissions')->where('name', 'directory.verify')->delete();
    }

    public function down(): void
    {
        /** Publication cannot be reversed without knowing each record's earlier state. */
    }
};
