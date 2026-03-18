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

        if (! $this->scheduling->isSystemWorkingDay($scheduledAt, $appointment->attendant_name, $appointment->attendant_user_id)) {
            return response()->json([
                'message' => 'Esta data não está disponível no calendário do sistema para este atendente.',
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

}
