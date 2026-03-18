<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $groups = DB::table('blocked_dates')
                ->select([
                    'blocked_date',
                    'attendant_user_id',
                    'attendant_name',
                    DB::raw('MAX(id) as keep_id'),
                    DB::raw('COUNT(*) as total_rows'),
                ])
                ->groupBy('blocked_date', 'attendant_user_id', 'attendant_name')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($groups as $group) {
                $blockedDate = \Illuminate\Support\Carbon::parse((string) $group->blocked_date)->toDateString();

                DB::table('blocked_dates')
                    ->whereDate('blocked_date', $blockedDate)
                    ->when(
                        $group->attendant_user_id !== null,
                        fn ($query) => $query->where('attendant_user_id', (int) $group->attendant_user_id),
                        fn ($query) => $query->whereNull('attendant_user_id')
                    )
                    ->when(
                        $group->attendant_name !== null,
                        fn ($query) => $query->where('attendant_name', (string) $group->attendant_name),
                        fn ($query) => $query->whereNull('attendant_name')
                    )
                    ->where('id', '!=', (int) $group->keep_id)
                    ->delete();
            }
        });
    }

    public function down(): void
    {
    }
};
