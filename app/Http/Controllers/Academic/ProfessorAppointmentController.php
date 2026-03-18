<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use App\Services\SchedulingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfessorAppointmentController extends Controller
{
    public function __construct(
        private SchedulingService $scheduling,
    ) {}

    public function updateStatus(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->scheduling->applyAutomaticNoShowRules();

        /** @var User|null $user */
        $user = $request->user();
        $aliases = $this->professorAliases((string) ($user?->name ?? ''));

        $belongsToProfessor = ($user?->id !== null && $appointment->attendant_user_id === $user->id)
            || in_array($appointment->attendant_name, $aliases, true);

        if (! $belongsToProfessor) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:Realizado,Cancelado'],
            'cancellation_reason' => ['nullable', 'string', 'max:500', 'required_if:status,Cancelado'],
        ], [
            'status.required' => 'Informe o status.',
            'status.in' => 'Status inválido para professor.',
            'cancellation_reason.required_if' => 'Informe o motivo da rejeição.',
            'cancellation_reason.max' => 'O motivo da rejeição deve ter no máximo :max caracteres.',
        ]);

        if (! in_array($appointment->status, ['Confirmado', 'Pendente'], true)) {
            return back()->with('status', 'Este agendamento não pode mais ser atualizado.');
        }

        if ($validated['status'] === 'Cancelado' && ! $this->scheduling->canModifyScheduledAppointment($appointment->scheduled_at)) {
            return back()->with('status', $this->scheduling->modificationWindowMessage());
        }

        $appointment->update([
            'status' => $validated['status'],
            'cancellation_reason' => $validated['status'] === 'Cancelado'
                ? trim((string) ($validated['cancellation_reason'] ?? ''))
                : null,
        ]);

        return back()->with('status', 'Status do atendimento atualizado com sucesso.');
    }

    /**
     * @return array<int, string>
     */
    private function professorAliases(string $name): array
    {
        return $this->scheduling->professorAliases($name);
    }
}
