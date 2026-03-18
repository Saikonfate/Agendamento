<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $keepIds = DB::table('blocked_dates')
                ->selectRaw('MAX(id) as keep_id')
                ->groupByRaw('DATE(blocked_date), COALESCE(attendant_user_id, -1), COALESCE(attendant_name, \'\')')
                ->pluck('keep_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values();

            if ($keepIds->isEmpty()) {
                return;
            }

            DB::table('blocked_dates')
                ->whereNotIn('id', $keepIds->all())
                ->delete();
        });
    }

    public function down(): void
    {
    }
};
