<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AttendantSchedule;
use App\Models\BlockedDate;
use App\Models\User;
use DateTime;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SchedulingService
{
    public const MIN_DAYS_IN_ADVANCE = 1;

    public const MAX_SCHEDULE_DAYS_AHEAD = 90;

    public const MIN_HOURS_BEFORE_MODIFICATION = 2;

    public const MAX_ACTIVE_APPOINTMENTS_PER_STUDENT = 2;

    public const MAX_ACTIVE_APPOINTMENTS_PER_SHIFT = 6;

    public const NO_SHOW_TOLERANCE_MINUTES = 15;

    public const NO_SHOW_LOOKBACK_DAYS = 60;

    public const NO_SHOW_BLOCK_THRESHOLD = 3;

    /**
     * @var array<string, int>
     */
    private array $professorMap = [];

    /**
     * @return array{user_id:int|null,name:string,aliases:array<int,string>}
     */
    public function resolveAttendant(?string $attendantName = null, ?int $attendantUserId = null): array
    {
        $attendantName = trim((string) $attendantName);

        if ($attendantUserId) {
            $user = User::query()->find($attendantUserId);

            if ($user) {
                return [
                    'user_id' => $user->id,
                    'name' => $attendantName !== '' ? $attendantName : $this->canonicalProfessorName($user->name),
                    'aliases' => $this->professorAliases($user->name),
                ];
            }
        }

        if ($attendantName === '') {
            return ['user_id' => null, 'name' => '', 'aliases' => []];
        }

        $professorId = $this->findProfessorIdByName($attendantName);

        if (! $professorId) {
            return [
                'user_id' => null,
                'name' => $attendantName,
                'aliases' => [$attendantName],
            ];
        }

        $user = User::query()->find($professorId);

        if (! $user) {
            return [
                'user_id' => null,
                'name' => $attendantName,
                'aliases' => [$attendantName],
            ];
        }

        $aliases = $this->professorAliases($user->name);

        return [
            'user_id' => $user->id,
            'name' => $attendantName,
            'aliases' => $aliases,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function professorAliases(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return [];
        }

        $baseName = preg_replace('/^(prof\.?|professor)\s+/iu', '', $name) ?: $name;
        $baseName = trim($baseName);

        $aliases = [
            $name,
            $baseName,
            'Prof. '.$baseName,
            'Professor '.$baseName,
        ];

        return array_values(array_unique(array_filter($aliases, fn ($value) => trim((string) $value) !== '')));
    }

    public function canonicalProfessorName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        $baseName = preg_replace('/^(prof\.?|professor)\s+/iu', '', $name) ?: $name;

        return 'Prof. '.trim($baseName);
    }

    public function isSystemWorkingDay(Carbon $date, ?string $attendantName = null, ?int $attendantUserId = null): bool
    {
        if ($date->isWeekend()) {
            return false;
        }

        $schedule = $this->resolveScheduleForDate($date, $attendantName, $attendantUserId);
        $dayMap = [
            1 => 'mon',
            2 => 'tue',
            3 => 'wed',
            4 => 'thu',
            5 => 'fri',
        ];

        $weekday = $dayMap[(int) $date->isoWeekday()] ?? null;

        return $weekday ? in_array($weekday, $schedule['working_days'], true) : false;
    }

    public function isDateBlocked(Carbon $date, ?string $attendantName = null, ?int $attendantUserId = null): bool
    {
        $identity = $this->resolveAttendant($attendantName, $attendantUserId);

        return BlockedDate::query()
            ->whereDate('blocked_date', $date->toDateString())
            ->where(function (Builder $query) use ($identity) {
                $query->where(function (Builder $globalQuery) {
                    $globalQuery
                        ->whereNull('attendant_name')
                        ->whereNull('attendant_user_id');
                });

                if ($identity['user_id']) {
                    $query->orWhere('attendant_user_id', $identity['user_id']);
                }

                if ($identity['name'] !== '') {
                    $query->orWhere('attendant_name', $identity['name']);
                }

                if (! empty($identity['aliases'])) {
                    $query->orWhereIn('attendant_name', $identity['aliases']);
                }
            })
            ->exists();
    }

    public function buildSlotsForDate(Carbon $date, ?string $attendantName = null, ?int $attendantUserId = null): array
    {
        $slots = $this->systemWorkingSlots($date, $attendantName, $attendantUserId);

        if (! $this->isSystemWorkingDay($date, $attendantName, $attendantUserId) || $this->isDateBlocked($date, $attendantName, $attendantUserId)) {
            return collect($slots)
                ->map(fn (string $slot) => [
                    'time' => $slot,
                    'available' => false,
                ])
                ->all();
        }

        $identity = $this->resolveAttendant($attendantName, $attendantUserId);

        $query = Appointment::query()
            ->whereDate('scheduled_at', $date)
            ->whereIn('status', ['Confirmado', 'Pendente']);

        $this->applyAttendantFilter($query, $identity);

        $appointments = $query->get();
        $occupied = $appointments->pluck('scheduled_at')->map(fn ($dateTime) => Carbon::parse($dateTime)->format('H:i'))->all();

        $shiftCounts = [
            'morning' => $appointments->filter(function (Appointment $appointment): bool {
                return Carbon::parse($appointment->scheduled_at)->hour < 12;
            })->count(),
            'afternoon' => $appointments->filter(function (Appointment $appointment): bool {
                return Carbon::parse($appointment->scheduled_at)->hour >= 12;
            })->count(),
        ];

        return collect($slots)
            ->map(function (string $slot) use ($date, $occupied, $shiftCounts): array {
                $slotDateTime = $date->copy()->setTimeFromTimeString($slot);
                $shift = $this->shiftLabelForDateTime($slotDateTime);
                $shiftHasCapacity = ($shiftCounts[$shift] ?? 0) < self::MAX_ACTIVE_APPOINTMENTS_PER_SHIFT;

                return [
                    'time' => $slot,
                    'available' => ! in_array($slot, $occupied, true)
                        && $shiftHasCapacity
                        && $this->isSchedulableDateTime($slotDateTime),
                ];
            })
            ->all();
    }

    public function isSlotValidForAttendant(string $time, Carbon $date, ?string $attendantName = null, ?int $attendantUserId = null): bool
    {
        $schedule = $this->resolveScheduleForDate($date, $attendantName, $attendantUserId);
        $duration = (int) $schedule['slot_duration_minutes'];

        $slotDateTime = $date->copy()->setTimeFromTimeString($time);
        if (! $this->isSchedulableDateTime($slotDateTime)) {
            return false;
        }

        $start = Carbon::createFromFormat('H:i', $schedule['start_time']);
        $end = Carbon::createFromFormat('H:i', $schedule['end_time']);
        $slotStart = Carbon::createFromFormat('H:i', $time);
        $slotEnd = $slotStart->copy()->addMinutes($duration);

        if ($slotStart->lt($start) || $slotEnd->gt($end)) {
            return false;
        }

        if ($schedule['break_start'] && $schedule['break_end']) {
            $breakStart = Carbon::createFromFormat('H:i', $schedule['break_start']);
            $breakEnd = Carbon::createFromFormat('H:i', $schedule['break_end']);

            $insideBreak = $slotStart->lessThan($breakEnd) && $slotEnd->greaterThan($breakStart);
            if ($insideBreak) {
                return false;
            }
        }

        $minutesFromStart = $start->diffInMinutes($slotStart);

        return $minutesFromStart % $duration === 0;
    }

    public function hasActiveConflict(Carbon $scheduledAt, ?string $attendantName = null, ?int $attendantUserId = null, ?int $ignoreAppointmentId = null): bool
    {
        $identity = $this->resolveAttendant($attendantName, $attendantUserId);

        $query = Appointment::query()
            ->where('scheduled_at', $scheduledAt)
            ->whereIn('status', ['Confirmado', 'Pendente']);

        if ($ignoreAppointmentId) {
            $query->where('id', '!=', $ignoreAppointmentId);
        }

        $this->applyAttendantFilter($query, $identity);

        return $query->exists();
    }

    public function hasStudentActiveConflict(string $registration, Carbon $scheduledAt, ?int $ignoreAppointmentId = null): bool
    {
        $query = Appointment::query()
            ->where('student_registration', $registration)
            ->where('scheduled_at', $scheduledAt)
            ->whereIn('status', ['Confirmado', 'Pendente']);

        if ($ignoreAppointmentId) {
            $query->where('id', '!=', $ignoreAppointmentId);
        }

        return $query->exists();
    }

    public function hasReachedStudentActiveLimit(string $registration, ?int $ignoreAppointmentId = null): bool
    {
        $query = Appointment::query()
            ->where('student_registration', $registration)
            ->whereIn('status', ['Confirmado', 'Pendente']);

        if ($ignoreAppointmentId) {
            $query->where('id', '!=', $ignoreAppointmentId);
        }

        return $query->count() >= self::MAX_ACTIVE_APPOINTMENTS_PER_STUDENT;
    }

    public function hasAttendantShiftCapacity(
        Carbon $scheduledAt,
        ?string $attendantName = null,
        ?int $attendantUserId = null,
        ?int $ignoreAppointmentId = null,
    ): bool {
        $identity = $this->resolveAttendant($attendantName, $attendantUserId);
        $shift = $this->shiftLabelForDateTime($scheduledAt);

        $query = Appointment::query()
            ->whereDate('scheduled_at', $scheduledAt)
            ->whereIn('status', ['Confirmado', 'Pendente']);

        $this->applyAttendantFilter($query, $identity);

        if ($ignoreAppointmentId) {
            $query->where('id', '!=', $ignoreAppointmentId);
        }

        $count = $query->get()->filter(function (Appointment $appointment) use ($shift): bool {
            return $this->shiftLabelForDateTime(Carbon::parse($appointment->scheduled_at)) === $shift;
        })->count();

        return $count < self::MAX_ACTIVE_APPOINTMENTS_PER_SHIFT;
    }

    public function slotDurationForAttendant(?string $attendantName = null, ?int $attendantUserId = null): int
    {
        $schedule = $this->resolveSchedule($attendantName, $attendantUserId);

        return (int) $schedule['slot_duration_minutes'];
    }

    public function isDateTimeInPast(Carbon $dateTime): bool
    {
        return $dateTime->lt($this->systemNow());
    }

    public function isSchedulableDateTime(Carbon $dateTime): bool
    {
        return $this->schedulingValidationError($dateTime) === null;
    }

    public function schedulingValidationError(Carbon $dateTime): ?string
    {
        $now = $this->systemNow();

        if ($this->isDateTimeInPast($dateTime)) {
            return 'past';
        }

        if ($dateTime->lt($now->copy()->addDays(self::MIN_DAYS_IN_ADVANCE)->startOfDay())) {
            return 'day_advance';
        }

        if ($dateTime->gt($now->copy()->addDays(self::MAX_SCHEDULE_DAYS_AHEAD)->endOfDay())) {
            return 'window';
        }

        return null;
    }

    public function schedulingValidationMessage(string $error): string
    {
        return match ($error) {
            'past' => 'Não é possível agendar horários que já passaram.',
            'day_advance' => 'Agendamentos devem ser feitos com pelo menos '.self::MIN_DAYS_IN_ADVANCE.' dia de antecedência.',
            'window' => 'Não é possível agendar com mais de '.self::MAX_SCHEDULE_DAYS_AHEAD.' dias de antecedência.',
            default => 'Horário inválido para agendamento.',
        };
    }

    public function applyAutomaticNoShowRules(): void
    {
        $cutoff = $this->systemNow()->copy()->subMinutes(self::NO_SHOW_TOLERANCE_MINUTES);

        Appointment::query()
            ->whereIn('status', ['Confirmado', 'Pendente'])
            ->where('scheduled_at', '<=', $cutoff)
            ->update([
                'status' => 'Cancelado',
                'cancellation_reason' => 'No-show automático (tolerância de '.self::NO_SHOW_TOLERANCE_MINUTES.' min excedida).',
            ]);
    }

    public function studentNoShowCount(string $registration): int
    {
        return Appointment::query()
            ->where('student_registration', $registration)
            ->where('status', 'Cancelado')
            ->where('cancellation_reason', 'like', 'No-show automático%')
            ->where('scheduled_at', '>=', $this->systemNow()->copy()->subDays(self::NO_SHOW_LOOKBACK_DAYS))
            ->count();
    }

    public function isStudentBlockedByNoShow(string $registration): bool
    {
        return $this->studentNoShowCount($registration) >= self::NO_SHOW_BLOCK_THRESHOLD;
    }

    public function studentNoShowBlockMessage(): string
    {
        return 'Você atingiu o limite de no-show recente. Procure a secretaria para regularização.';
    }

    public function canModifyScheduledAppointment(DateTimeInterface $scheduledAt): bool
    {
        $scheduledAtCarbon = $scheduledAt instanceof Carbon
            ? $scheduledAt->copy()
            : Carbon::instance(DateTime::createFromInterface($scheduledAt));

        return $scheduledAtCarbon->gte($this->systemNow()->copy()->addHours(self::MIN_HOURS_BEFORE_MODIFICATION));
    }

    public function modificationWindowMessage(): string
    {
        return 'Alterações são permitidas apenas com no mínimo '.self::MIN_HOURS_BEFORE_MODIFICATION.' horas de antecedência.';
    }

    private function shiftLabelForDateTime(Carbon $dateTime): string
    {
        return $dateTime->hour < 12 ? 'morning' : 'afternoon';
    }

    private function systemNow(): Carbon
    {
        return Carbon::now(config('app.timezone'));
    }

    /**
     * @return array{working_days: array<int, string>, start_time: string, end_time: string, break_start: string|null, break_end: string|null, slot_duration_minutes: int}
     */
    public function resolveScheduleForDate(Carbon $date, ?string $attendantName = null, ?int $attendantUserId = null): array
    {
        $schedule = $this->resolveSchedule($attendantName, $attendantUserId);

        $dayMap = [
            1 => 'mon',
            2 => 'tue',
            3 => 'wed',
            4 => 'thu',
            5 => 'fri',
        ];

        $dayKey = $dayMap[(int) $date->isoWeekday()] ?? null;
        if (! $dayKey) {
            return $schedule;
        }

        $daySettings = $schedule['day_settings'] ?? [];
        $config = is_array($daySettings[$dayKey] ?? null) ? $daySettings[$dayKey] : null;
        if (! is_array($config)) {
            return $schedule;
        }

        $enabled = (bool) ($config['enabled'] ?? false);

        return [
            'working_days' => $enabled ? [$dayKey] : [],
            'start_time' => (string) ($config['start_time'] ?? $schedule['start_time']),
            'end_time' => (string) ($config['end_time'] ?? $schedule['end_time']),
            'break_start' => ($config['break_start'] ?? '') !== '' ? (string) $config['break_start'] : null,
            'break_end' => ($config['break_end'] ?? '') !== '' ? (string) $config['break_end'] : null,
            'slot_duration_minutes' => (int) $schedule['slot_duration_minutes'],
        ];
    }

    private function systemWorkingSlots(Carbon $date, ?string $attendantName = null, ?int $attendantUserId = null): array
    {
        $schedule = $this->resolveScheduleForDate($date, $attendantName, $attendantUserId);
        $start = Carbon::createFromFormat('H:i', $schedule['start_time']);
        $end = Carbon::createFromFormat('H:i', $schedule['end_time']);
        $duration = (int) $schedule['slot_duration_minutes'];

        $breakStart = $schedule['break_start'] ? Carbon::createFromFormat('H:i', $schedule['break_start']) : null;
        $breakEnd = $schedule['break_end'] ? Carbon::createFromFormat('H:i', $schedule['break_end']) : null;

        $slots = [];
        $cursor = $start->copy();

        while ($cursor->copy()->addMinutes($duration)->lessThanOrEqualTo($end)) {
            $slotStart = $cursor->copy();
            $slotEnd = $cursor->copy()->addMinutes($duration);

            $insideBreak = $breakStart && $breakEnd
                ? $slotStart->lessThan($breakEnd) && $slotEnd->greaterThan($breakStart)
                : false;

            if (! $insideBreak) {
                $slots[] = $slotStart->format('H:i');
            }

            $cursor->addMinutes($duration);
        }

        return $slots;
    }

    /**
     * @return array{working_days: array<int, string>, day_settings: array<int|string, mixed>, start_time: string, end_time: string, break_start: string|null, break_end: string|null, slot_duration_minutes: int}
     */
    private function resolveSchedule(?string $attendantName = null, ?int $attendantUserId = null): array
    {
        $default = [
            'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'day_settings' => [],
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_start' => '12:00',
            'break_end' => '13:00',
            'slot_duration_minutes' => 30,
        ];

        $identity = $this->resolveAttendant($attendantName, $attendantUserId);

        $query = AttendantSchedule::query();

        $this->applyAttendantFilter($query, $identity, true, false);

        $schedule = $query->first();
        if (! $schedule) {
            return $default;
        }

        return [
            'working_days' => $schedule->working_days ?: $default['working_days'],
            'day_settings' => is_array($schedule->day_settings) ? $schedule->day_settings : [],
            'start_time' => Carbon::parse($schedule->start_time)->format('H:i'),
            'end_time' => Carbon::parse($schedule->end_time)->format('H:i'),
            'break_start' => $schedule->break_start ? Carbon::parse($schedule->break_start)->format('H:i') : null,
            'break_end' => $schedule->break_end ? Carbon::parse($schedule->break_end)->format('H:i') : null,
            'slot_duration_minutes' => (int) ($schedule->slot_duration_minutes ?: 30),
        ];
    }

    /**
     * @param array{user_id:int|null,name:string,aliases:array<int,string>} $identity
     */
    private function applyAttendantFilter(Builder $query, array $identity, bool $allowName = true, bool $allowAliases = true): void
    {
        $query->where(function (Builder $scopedQuery) use ($identity, $allowName, $allowAliases) {
            $hasClause = false;

            if ($identity['user_id']) {
                $scopedQuery->where('attendant_user_id', $identity['user_id']);
                $hasClause = true;
            }

            if ($allowName && $identity['name'] !== '') {
                if ($hasClause) {
                    $scopedQuery->orWhere('attendant_name', $identity['name']);
                } else {
                    $scopedQuery->where('attendant_name', $identity['name']);
                }

                $hasClause = true;
            }

            if ($allowAliases && ! empty($identity['aliases'])) {
                if ($hasClause) {
                    $scopedQuery->orWhereIn('attendant_name', $identity['aliases']);
                } else {
                    $scopedQuery->whereIn('attendant_name', $identity['aliases']);
                }

                $hasClause = true;
            }

            if (! $hasClause) {
                $scopedQuery->whereRaw('1 = 0');
            }
        });
    }

    private function findProfessorIdByName(string $attendantName): ?int
    {
        $normalized = $this->normalizeAttendantName($attendantName);
        if ($normalized === '') {
            return null;
        }

        if (empty($this->professorMap)) {
            $this->hydrateProfessorMap();
        }

        return $this->professorMap[$normalized] ?? null;
    }

    private function hydrateProfessorMap(): void
    {
        $map = [];

        $professors = User::query()
            ->where('role', 'professor')
            ->get(['id', 'name']);

        foreach ($professors as $professor) {
            foreach ($this->professorAliases($professor->name) as $alias) {
                $normalized = $this->normalizeAttendantName($alias);
                if ($normalized !== '' && ! array_key_exists($normalized, $map)) {
                    $map[$normalized] = $professor->id;
                }
            }
        }

        $this->professorMap = $map;
    }

    private function normalizeAttendantName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        $baseName = preg_replace('/^(prof\.?|professor)\s+/iu', '', $name) ?: $name;

        return mb_strtolower(trim($baseName), 'UTF-8');
    }
}
