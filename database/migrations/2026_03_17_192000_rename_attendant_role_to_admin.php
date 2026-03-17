<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'attendant')
            ->update(['role' => 'admin']);

        $hasLegacyAdminEmail = DB::table('users')->where('email', 'atendente@unifap.edu.br')->exists();
        $hasNewAdminEmail = DB::table('users')->where('email', 'admin@unifap.edu.br')->exists();

        if ($hasLegacyAdminEmail && ! $hasNewAdminEmail) {
            DB::table('users')
                ->where('email', 'atendente@unifap.edu.br')
                ->update(['email' => 'admin@unifap.edu.br']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasNewAdminEmail = DB::table('users')->where('email', 'admin@unifap.edu.br')->exists();
        $hasLegacyAdminEmail = DB::table('users')->where('email', 'atendente@unifap.edu.br')->exists();

        if ($hasNewAdminEmail && ! $hasLegacyAdminEmail) {
            DB::table('users')
                ->where('email', 'admin@unifap.edu.br')
                ->update(['email' => 'atendente@unifap.edu.br']);
        }

        DB::table('users')
            ->where('role', 'admin')
            ->update(['role' => 'attendant']);
    }
};
