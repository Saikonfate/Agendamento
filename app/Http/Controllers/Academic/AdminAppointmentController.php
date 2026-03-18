<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\SchedulingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminAppointmentController extends Controller
{
    public function __construct(
        private SchedulingService $scheduling,
    ) {}

    public function updateStatus(Request $request, Appointment $appointment): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:Cancelado,Realizado,Confirmado,Pendente'],
            'cancellation_reason' => ['nullable', 'string', 'max:500', 'required_if:status,Cancelado'],
        ], [
            'status.required' => 'Informe o status.',
            'status.in' => 'Status inválido.',
            'cancellation_reason.required_if' => 'Informe o motivo do cancelamento.',
        ]);

        $this->scheduling->applyAutomaticNoShowRules();

        if (! $this->canTransitionStatus($appointment->status, $validated['status'])) {
            return response()->json([
                'message' => 'Transição de status inválida para este agendamento.',
            ], 422);
        }

        if ($validated['status'] === 'Cancelado' && ! $this->scheduling->canModifyScheduledAppointment($appointment->scheduled_at)) {
            return response()->json([
                'message' => $this->scheduling->modificationWindowMessage(),
            ], 422);
        }

        $appointment->update([
            'status' => $validated['status'],
            'cancellation_reason' => $validated['status'] === 'Cancelado'
                ? trim((string) ($validated['cancellation_reason'] ?? ''))
                : null,
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
            'reschedule_reason' => ['required', 'string', 'max:500'],
        ], [
            'date.required' => 'Informe a nova data.',
            'date.date' => 'Data inválida.',
            'time.required' => 'Informe o novo horário.',
            'time.date_format' => 'Horário inválido.',
            'reschedule_reason.required' => 'Informe o motivo do reagendamento.',
        ]);

        $this->scheduling->applyAutomaticNoShowRules();

        if (! $this->scheduling->canModifyScheduledAppointment($appointment->scheduled_at)) {
            return response()->json([
                'message' => $this->scheduling->modificationWindowMessage(),
            ], 422);
        }

        $scheduledAt = Carbon::createFromFormat('Y-m-d H:i', $validated['date'].' '.$validated['time']);

        $schedulingError = $this->scheduling->schedulingValidationError($scheduledAt);
        if ($schedulingError !== null) {
            return response()->json([
                'message' => $this->scheduling->schedulingValidationMessage($schedulingError),
            ], 422);
        }

        $studentConflict = $this->scheduling->hasStudentActiveConflict(
            $appointment->student_registration,
            $scheduledAt,
            $appointment->id,
        );

        if ($studentConflict) {
            return response()->json([
                'message' => 'O aluno já possui um agendamento ativo nesse mesmo horário.',
            ], 422);
        }

        if (! $this->scheduling->hasAttendantShiftCapacity($scheduledAt, $appointment->attendant_name, $appointment->attendant_user_id, $appointment->id)) {
            return response()->json([
                'message' => 'Capacidade máxima do atendente para este turno foi atingida.',
            ], 422);
        }

        if (! $this->scheduling->isSystemWorkingDay($scheduledAt, $appointment->attendant_name, $appointment->attendant_user_id)) {
            return response()->json([
                'message' => 'Esta data está indisponível por regra do calendário do sistema para este atendente.',
            ], 422);
        }

        if ($this->scheduling->isDateBlocked($scheduledAt, $appointment->attendant_name, $appointment->attendant_user_id)) {
            return response()->json([
                'message' => 'Esta data está bloqueada para o atendente selecionado.',
            ], 422);
        }

        if (! $this->scheduling->isSlotValidForAttendant($validated['time'], $scheduledAt, $appointment->attendant_name, $appointment->attendant_user_id)) {
            return response()->json([
                'message' => 'Este horário não é válido para a configuração do atendente.',
            ], 422);
        }

        $hasConflict = $this->scheduling->hasActiveConflict(
            $scheduledAt,
            $appointment->attendant_name,
            $appointment->attendant_user_id,
            $appointment->id,
        );

        if ($hasConflict) {
            return response()->json([
                'message' => 'Este horário já está ocupado para o atendente.',
            ], 422);
        }

        $appointment->update([
            'scheduled_at' => $scheduledAt,
            'status' => 'Confirmado',
        ]);

        $slotDuration = $this->scheduling->slotDurationForAttendant($appointment->attendant_name, $appointment->attendant_user_id);

        return response()->json([
            'data' => [
                'id' => $appointment->id,
                'status' => $appointment->status,
                'scheduled_time' => $appointment->scheduled_at->format('H:i'),
                'scheduled_time_range' => $appointment->scheduled_at->format('H:i').' - '.$appointment->scheduled_at->copy()->addMinutes($slotDuration)->format('H:i'),
            ],
        ]);
    }

    public function slots(Request $request, Appointment $appointment): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
        ], [
            'date.required' => 'Informe a data para carregar os horários.',
            'date.date' => 'Data inválida para consulta de horários.',
        ]);

        $date = Carbon::parse($validated['date']);

        $identity = $this->scheduling->resolveAttendant($appointment->attendant_name, $appointment->attendant_user_id);
        $slots = collect($this->scheduling->buildSlotsForDate($date, $appointment->attendant_name, $appointment->attendant_user_id));
        $slotDuration = $this->scheduling->slotDurationForAttendant($appointment->attendant_name, $appointment->attendant_user_id);

        $occupiedQuery = Appointment::query()
            ->whereDate('scheduled_at', $date)
            ->whereIn('status', ['Confirmado', 'Pendente']);

        if ($appointment->id) {
            $occupiedQuery->where('id', '!=', $appointment->id);
        }

        $occupiedQuery->where(function ($query) use ($identity) {
            if ($identity['user_id']) {
                $query->where('attendant_user_id', $identity['user_id']);
            }

            if ($identity['name'] !== '') {
                $method = $identity['user_id'] ? 'orWhere' : 'where';
                $query->{$method}('attendant_name', $identity['name']);
            }

            if (! empty($identity['aliases'])) {
                $method = ($identity['user_id'] || $identity['name'] !== '') ? 'orWhereIn' : 'whereIn';
                $query->{$method}('attendant_name', $identity['aliases']);
            }
        });

        $occupiedByTime = $occupiedQuery
            ->get(['scheduled_at', 'student_name'])
            ->mapWithKeys(fn (Appointment $item) => [
                Carbon::parse($item->scheduled_at)->format('H:i') => (string) $item->student_name,
            ]);

        $payload = $slots->map(function (array $slot) use ($date, $slotDuration, $occupiedByTime) {
            $time = (string) ($slot['time'] ?? '');
            $start = Carbon::createFromFormat('H:i', $time);
            $end = $start->copy()->addMinutes($slotDuration);

            return [
                'time' => $time,
                'time_range' => $start->format('H:i').' - '.$end->format('H:i'),
                'available' => (bool) ($slot['available'] ?? false),
                'occupied_by' => $occupiedByTime->get($time),
            ];
        })->values();

        return response()->json([
            'data' => $payload,
        ]);
    }

    private function canTransitionStatus(string $currentStatus, string $targetStatus): bool
    {
        if ($currentStatus === $targetStatus) {
            return true;
        }

        $allowedTransitions = [
            'Pendente' => ['Confirmado', 'Cancelado'],
            'Confirmado' => ['Realizado', 'Cancelado'],
            'Realizado' => [],
            'Cancelado' => [],
        ];

        return in_array($targetStatus, $allowedTransitions[$currentStatus] ?? [], true);
    }

}
