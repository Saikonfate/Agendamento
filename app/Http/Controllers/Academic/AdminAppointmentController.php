<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AttendantSchedule;
use App\Models\BlockedDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminAppointmentController extends Controller
{
    public function updateStatus(Request $request, Appointment $appointment): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:Cancelado,Realizado,Confirmado,Pendente'],
        ], [
            'status.required' => 'Informe o status.',
            'status.in' => 'Status inválido.',
        ]);

        $appointment->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'data' => [
                'id' => $appointment->id,
                'status' => $appointment->status,
            ],
        ]);
    }

    public function reschedule(Request $request, Appointment $appointment): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
        ], [
            'date.required' => 'Informe a nova data.',
            'date.date' => 'Data inválida.',
            'time.required' => 'Informe o novo horário.',
            'time.date_format' => 'Horário inválido.',
        ]);

        $scheduledAt = Carbon::createFromFormat('Y-m-d H:i', $validated['date'].' '.$validated['time']);

        if (! $this->isSystemWorkingDay($scheduledAt, $appointment->attendant_name)) {
            return response()->json([
                'message' => 'Esta data não está disponível no calendário do sistema para este atendente.',
            ], 422);
        }

        if ($this->isDateBlocked($scheduledAt, $appointment->attendant_name)) {
            return response()->json([
                'message' => 'Esta data está bloqueada para o atendente selecionado.',
            ], 422);
        }

        if (! $this->isSlotValidForAttendant($validated['time'], $appointment->attendant_name, $scheduledAt)) {
            return response()->json([
                'message' => 'Este horário não é válido para a configuração do atendente.',
            ], 422);
        }

        $hasConflict = Appointment::query()
            ->where('id', '!=', $appointment->id)
            ->where('attendant_name', $appointment->attendant_name)
            ->where('scheduled_at', $scheduledAt)
            ->whereIn('status', ['Confirmado', 'Pendente'])
            ->exists();

        if ($hasConflict) {
            return response()->json([
                'message' => 'Este horário já está ocupado para o atendente.',
            ], 422);
        }

        $appointment->update([
            'scheduled_at' => $scheduledAt,
            'status' => 'Confirmado',
        ]);

        $slotDuration = $this->slotDurationForAttendant($appointment->attendant_name);

        return response()->json([
            'data' => [
                'id' => $appointment->id,
                'status' => $appointment->status,
                'scheduled_time' => $appointment->scheduled_at->format('H:i'),
                'scheduled_time_range' => $appointment->scheduled_at->format('H:i').' - '.$appointment->scheduled_at->copy()->addMinutes($slotDuration)->format('H:i'),
            ],
        ]);
    }

    private function isSystemWorkingDay(Carbon $date, string $attendantName): bool
    {
        if ($date->isWeekend()) {
            return false;
        }

        $schedule = $this->resolveScheduleForDate($attendantName, $date);
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

    private function isDateBlocked(Carbon $date, string $attendantName): bool
    {
        return BlockedDate::query()
            ->whereDate('blocked_date', $date->toDateString())
            ->where(function ($query) use ($attendantName) {
                $query->whereNull('attendant_name')
                    ->orWhere('attendant_name', $attendantName);
            })
            ->exists();
    }

    private function isSlotValidForAttendant(string $time, string $attendantName, Carbon $date): bool
    {
        $schedule = $this->resolveScheduleForDate($attendantName, $date);
        $duration = (int) $schedule['slot_duration_minutes'];

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

    private function slotDurationForAttendant(string $attendantName): int
    {
        $schedule = $this->resolveSchedule($attendantName);

        return (int) $schedule['slot_duration_minutes'];
    }

    /**
     * @return array{working_days: array<int, string>, start_time: string, end_time: string, break_start: string|null, break_end: string|null, slot_duration_minutes: int}
     */
    private function resolveScheduleForDate(string $attendantName, Carbon $date): array
    {
        $schedule = $this->resolveSchedule($attendantName);

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

    /**
     * @return array{working_days: array<int, string>, day_settings: array<int|string, mixed>, start_time: string, end_time: string, break_start: string|null, break_end: string|null, slot_duration_minutes: int}
     */
    private function resolveSchedule(string $attendantName): array
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

        $schedule = AttendantSchedule::query()->where('attendant_name', $attendantName)->first();
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
}
