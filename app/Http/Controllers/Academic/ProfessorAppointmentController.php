<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfessorAppointmentController extends Controller
{
    public function updateStatus(Request $request, Appointment $appointment): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        $aliases = $this->professorAliases((string) ($user?->name ?? ''));

        if (! in_array($appointment->attendant_name, $aliases, true)) {
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

        return array_values(array_unique(array_filter($aliases)));
    }
}
