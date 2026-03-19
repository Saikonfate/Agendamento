<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\BlockedDate;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProfilePageController extends Controller
{
    public function student(Request $request): View
    {
        $registration = (string) ($request->user()?->matricula ?? '');

        $recentActivities = Appointment::query()
            ->with('attendantUser:id,name')
            ->where('student_registration', $registration)
            ->latest('scheduled_at')
            ->limit(6)
            ->get()
            ->map(fn (Appointment $appointment) => [
                'dot' => $this->statusDotClass($appointment->status, true),
                'title' => $appointment->subject,
                'meta' => $appointment->attendant_display_name.' · '.$appointment->scheduled_at->locale('pt_BR')->translatedFormat('D, d/m · H:i'),
                'status' => $appointment->status,
                'statusClass' => $this->statusBadgeClass($appointment->status, true),
            ]);

        return view('academic.student-profile', [
            'recentActivities' => $recentActivities,
        ]);
    }

    public function admin(): View
    {
        $appointmentActivities = Appointment::query()
            ->with('attendantUser:id,name')
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (Appointment $appointment) => [
                'occurred_at' => $appointment->updated_at,
                'dot' => $this->statusDotClass($appointment->status, false),
                'title' => $this->adminStatusTitle($appointment->status),
                'meta' => $appointment->student_name.' · '.$appointment->subject.' · '.$appointment->scheduled_at->format('H:i'),
                'status' => $appointment->status,
                'statusClass' => $this->statusBadgeClass($appointment->status, false),
            ]);

        $blockedActivities = BlockedDate::query()
            ->with('attendantUser:id,name')
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (BlockedDate $blockedDate) => [
                'occurred_at' => $blockedDate->updated_at,
                'dot' => 'bg-zinc-400',
                'title' => 'Data bloqueada',
                'meta' => Carbon::parse($blockedDate->blocked_date)->format('d/m/y').' · '.$blockedDate->reason.($blockedDate->attendant_display_name !== '' ? ' · '.$blockedDate->attendant_display_name : ' · Todos os atendentes'),
                'status' => 'Bloqueado',
                'statusClass' => 'bg-zinc-700 text-zinc-200',
            ]);

        $recentActivities = $appointmentActivities
            ->merge($blockedActivities)
            ->sortByDesc('occurred_at')
            ->take(8)
            ->values()
            ->map(fn (array $activity) => collect($activity)->except('occurred_at')->all());

        return view('academic.admin-profile', [
            'recentActivities' => $recentActivities,
        ]);
    }

    public function professor(Request $request): View
    {
        $user = $request->user();
        $professorName = (string) ($request->user()?->name ?? '');
        $aliases = $this->professorAliases($professorName);

        $query = Appointment::query()->latest('updated_at');
        if ($user?->id) {
            $query->where(function ($innerQuery) use ($aliases, $user) {
                $innerQuery->where('attendant_user_id', $user->id);

                if (! empty($aliases)) {
                    $innerQuery->orWhereIn('attendant_name', $aliases);
                }
            });
        } elseif (! empty($aliases)) {
            $query->whereIn('attendant_name', $aliases);
        } else {
            $query->whereRaw('1 = 0');
        }

        $recentActivities = $query
            ->with('attendantUser:id,name')
            ->limit(8)
            ->get()
            ->map(fn (Appointment $appointment) => [
                'dot' => $this->statusDotClass($appointment->status, false),
                'title' => $appointment->subject,
                'meta' => $appointment->student_name.' · '.$appointment->scheduled_at->locale('pt_BR')->translatedFormat('D, d/m · H:i'),
                'status' => $appointment->status,
                'statusClass' => $this->statusBadgeClass($appointment->status, false),
            ]);

        return view('academic.professor-profile', [
            'recentActivities' => $recentActivities,
        ]);
    }

    private function adminStatusTitle(string $status): string
    {
        return match ($status) {
            'Realizado' => 'Atendimento concluído',
            'Confirmado' => 'Atendimento confirmado',
            'Pendente' => 'Atendimento pendente',
            'Cancelado' => 'Atendimento cancelado',
            default => 'Atualização de agendamento',
        };
    }

    private function statusDotClass(string $status, bool $isStudentContext): string
    {
        return match ($status) {
            'Confirmado' => $isStudentContext ? 'bg-emerald-400' : 'bg-blue-400',
            'Pendente' => 'bg-amber-400',
            'Cancelado' => 'bg-rose-400',
            default => $isStudentContext ? 'bg-violet-300' : 'bg-emerald-400',
        };
    }

    private function statusBadgeClass(string $status, bool $isStudentContext): string
    {
        return match ($status) {
            'Confirmado' => $isStudentContext ? 'bg-emerald-500/20 text-emerald-300' : 'bg-blue-500/20 text-blue-300',
            'Pendente' => 'bg-amber-500/20 text-amber-300',
            'Cancelado' => 'bg-rose-500/20 text-rose-300',
            default => $isStudentContext ? 'bg-violet-500/20 text-violet-200' : 'bg-emerald-500/20 text-emerald-300',
        };
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
