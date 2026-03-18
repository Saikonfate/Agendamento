<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $rows = DB::table('blocked_dates')
                ->where('reason', 'like', '%São José%')
                ->get();

            foreach ($rows as $row) {
                $blockedDate = Carbon::parse((string) $row->blocked_date);
                if ($blockedDate->month !== 3 || $blockedDate->day !== 20) {
                    continue;
                }

                $targetDate = $blockedDate->copy()->day(19)->toDateString();

                $conflict = DB::table('blocked_dates')
                    ->whereDate('blocked_date', $targetDate)
                    ->when(
                        $row->attendant_user_id !== null,
                        fn ($query) => $query->where('attendant_user_id', (int) $row->attendant_user_id),
                        fn ($query) => $query->whereNull('attendant_user_id')
                    )
                    ->when(
                        $row->attendant_name !== null && trim((string) $row->attendant_name) !== '',
                        fn ($query) => $query->where('attendant_name', (string) $row->attendant_name),
                        fn ($query) => $query->whereNull('attendant_name')
                    )
                    ->where('id', '!=', (int) $row->id)
                    ->first();

                if ($conflict) {
                    DB::table('blocked_dates')
                        ->where('id', (int) $row->id)
                        ->delete();

                    continue;
                }

                DB::table('blocked_dates')
                    ->where('id', (int) $row->id)
                    ->update([
                        'blocked_date' => $targetDate,
                    ]);
            }
        });
    }

    public function down(): void
    {
    }
};
