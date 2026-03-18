<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('attendant_user_id')->nullable()->after('attendant_name')->constrained('users')->nullOnDelete();
            $table->index(['attendant_user_id', 'scheduled_at']);
        });

        Schema::table('attendant_schedules', function (Blueprint $table) {
            $table->foreignId('attendant_user_id')->nullable()->after('attendant_name')->constrained('users')->nullOnDelete();
            $table->index('attendant_user_id');
        });

        Schema::table('blocked_dates', function (Blueprint $table) {
            $table->foreignId('attendant_user_id')->nullable()->after('attendant_name')->constrained('users')->nullOnDelete();
            $table->index(['blocked_date', 'attendant_user_id']);
        });

        $this->backfillAttendantUserIds();
    }

    public function down(): void
    {
        Schema::table('blocked_dates', function (Blueprint $table) {
            $table->dropIndex(['blocked_date', 'attendant_user_id']);
            $table->dropConstrainedForeignId('attendant_user_id');
        });

        Schema::table('attendant_schedules', function (Blueprint $table) {
            $table->dropIndex(['attendant_user_id']);
            $table->dropConstrainedForeignId('attendant_user_id');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['attendant_user_id', 'scheduled_at']);
            $table->dropConstrainedForeignId('attendant_user_id');
        });
    }

    private function backfillAttendantUserIds(): void
    {
        $professors = DB::table('users')
            ->where('role', 'professor')
            ->select('id', 'name')
            ->get();

        $map = [];

        foreach ($professors as $professor) {
            $aliases = $this->aliases((string) $professor->name);

            foreach ($aliases as $alias) {
                $normalized = $this->normalizeName($alias);

                if ($normalized !== '' && ! array_key_exists($normalized, $map)) {
                    $map[$normalized] = (int) $professor->id;
                }
            }
        }

        $this->backfillTable('appointments', $map);
        $this->backfillTable('attendant_schedules', $map);
        $this->backfillTable('blocked_dates', $map);
    }

    /**
     * @param array<string, int> $map
     */
    private function backfillTable(string $table, array $map): void
    {
        DB::table($table)
            ->whereNull('attendant_user_id')
            ->whereNotNull('attendant_name')
            ->select('id', 'attendant_name')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table, $map): void {
                foreach ($rows as $row) {
                    $normalized = $this->normalizeName((string) ($row->attendant_name ?? ''));
                    $userId = $map[$normalized] ?? null;

                    if (! $userId) {
                        continue;
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['attendant_user_id' => $userId]);
                }
            });
    }

    /**
     * @return array<int, string>
     */
    private function aliases(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return [];
        }

        $baseName = preg_replace('/^(prof\.?|professor)\s+/iu', '', $name) ?: $name;
        $baseName = trim($baseName);

        return array_values(array_unique([
            $name,
            $baseName,
            'Prof. '.$baseName,
            'Professor '.$baseName,
        ]));
    }

    private function normalizeName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        $baseName = preg_replace('/^(prof\.?|professor)\s+/iu', '', $name) ?: $name;

        return mb_strtolower(trim($baseName), 'UTF-8');
    }
};
