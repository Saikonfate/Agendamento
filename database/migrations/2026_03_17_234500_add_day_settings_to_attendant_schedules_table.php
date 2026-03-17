<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendant_schedules', function (Blueprint $table) {
            $table->json('day_settings')->nullable()->after('working_days');
        });
    }

    public function down(): void
    {
        Schema::table('attendant_schedules', function (Blueprint $table) {
            $table->dropColumn('day_settings');
        });
    }
};
