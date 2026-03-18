<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AttendantSchedule;
use App\Services\SchedulingService;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ProfessorDashboardController extends Controller
{
    private const ALLOWED_WORKING_DAYS = ['mon', 'tue', 'wed', 'thu', 'fri'];

    public function __construct(
        private SchedulingService $scheduling,
    ) {}

    public function __invoke(Request $request): View
    {
        /** @var User|null $user */
        $user = $request->user();
        $aliases = $this->professorAliases((string) ($user?->name ?? ''));
        $primaryAlias = $this->scheduling->canonicalProfessorName((string) ($user?->name ?? ''));

        $today = Carbon::today();

        $baseQuery = Appointment::query();
        if ($user?->id) {
            $baseQuery->where(function ($query) use ($aliases, $user) {
                $query->where('attendant_user_id', $user->id);

                if (! empty($aliases)) {
                    $query->orWhereIn('attendant_name', $aliases);
                }
            });
        } elseif (! empty($aliases)) {
            $baseQuery->whereIn('attendant_name', $aliases);
        } else {
            $baseQuery->whereRaw('1 = 0');
        }

        $todayAppointments = (clone $baseQuery)
            ->whereDate('scheduled_at', $today)
            ->orderBy('scheduled_at')
            ->get();

        $agendaAppointments = (clone $baseQuery)
            ->whereDate('scheduled_at', '>=', $today)
            ->orderBy('scheduled_at')
            ->get();

        $todayCount = $todayAppointments
            ->whereIn('status', ['Confirmado', 'Pendente', 'Realizado'])
            ->count();

        $pendingCount = $todayAppointments->where('status', 'Pendente')->count();

        $completedMonthCount = (clone $baseQuery)
            ->where('status', 'Realizado')
            ->whereBetween('scheduled_at', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])
            ->count();

        $defaultSchedule = [
            'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_start' => '12:00',
            'break_end' => '13:00',
            'slot_duration_minutes' => 30,
        ];

        $scheduleModel = AttendantSchedule::query()
            ->where(function ($query) use ($primaryAlias, $aliases, $user) {
                if ($user?->id) {
                    $query->where('attendant_user_id', $user->id);
                }

                $query->orWhere('attendant_name', $primaryAlias);

                if (! empty($aliases)) {
                    $query->orWhereIn('attendant_name', $aliases);
                }
            })
            ->first();

        $schedule = $scheduleModel ? [
            'working_days' => collect($scheduleModel->working_days ?? $defaultSchedule['working_days'])
                ->filter(fn ($day) => in_array($day, self::ALLOWED_WORKING_DAYS, true))
                ->values()
                ->all(),
            'start_time' => Carbon::parse($scheduleModel->start_time)->format('H:i'),
            'end_time' => Carbon::parse($scheduleModel->end_time)->format('H:i'),
            'break_start' => $scheduleModel->break_start ? Carbon::parse($scheduleModel->break_start)->format('H:i') : '',
            'break_end' => $scheduleModel->break_end ? Carbon::parse($scheduleModel->break_end)->format('H:i') : '',
            'slot_duration_minutes' => (int) $scheduleModel->slot_duration_minutes,
        ] : $defaultSchedule;

        $schedule['day_settings'] = $this->defaultDaySettings($schedule, $scheduleModel?->day_settings);

        return view('academic.professor-dashboard', [
            'appointments' => $agendaAppointments,
            'todayCount' => $todayCount,
            'pendingCount' => $pendingCount,
            'completedMonthCount' => $completedMonthCount,
            'todayLabel' => $today->locale('pt_BR')->translatedFormat('d/m/Y'),
            'attendantAlias' => $primaryAlias,
            'schedule' => $schedule,
        ]);
    }

    public function storeAvailability(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        $aliases = $this->professorAliases((string) ($user?->name ?? ''));

        if (empty($aliases)) {
            return back()->with('status', 'Não foi possível identificar o professor para salvar a disponibilidade.');
        }

        $validated = $request->validate([
            'slot_duration_minutes' => ['required', 'integer', 'in:30,45,60'],
        ]);

        $daySettings = $this->defaultDaySettings($validated, $request->input('day_settings', []));

        $daySettingsError = $this->validateDaySettings($daySettings);
        if ($daySettingsError !== null) {
            return back()->withErrors($daySettingsError)->withInput();
        }

        $workingDays = collect(self::ALLOWED_WORKING_DAYS)
            ->filter(fn (string $day) => (bool) ($daySettings[$day]['enabled'] ?? false))
            ->values()
            ->all();

        $firstEnabledDay = collect(self::ALLOWED_WORKING_DAYS)
            ->first(fn (string $day) => (bool) ($daySettings[$day]['enabled'] ?? false));

        $firstDayConfig = $firstEnabledDay ? ($daySettings[$firstEnabledDay] ?? null) : null;
        if (! is_array($firstDayConfig)) {
            return back()->withErrors(['working_days' => 'Selecione ao menos um dia útil.'])->withInput();
        }

        $payload = [
            'working_days' => $workingDays,
            'day_settings' => $daySettings,
            'start_time' => $firstDayConfig['start_time'],
            'end_time' => $firstDayConfig['end_time'],
            'break_start' => $firstDayConfig['break_start'] !== '' ? $firstDayConfig['break_start'] : null,
            'break_end' => $firstDayConfig['break_end'] !== '' ? $firstDayConfig['break_end'] : null,
            'slot_duration_minutes' => (int) $validated['slot_duration_minutes'],
        ];

        if (empty($payload['working_days'])) {
            return back()->withErrors(['working_days' => 'Selecione ao menos um dia útil.'])->withInput();
        }

        $primaryAlias = $this->scheduling->canonicalProfessorName((string) ($user?->name ?? ''));

        if ($user?->id) {
            AttendantSchedule::query()->updateOrCreate(
                ['attendant_user_id' => $user->id],
                $payload + ['attendant_name' => $primaryAlias],
            );
        } else {
            foreach ($aliases as $alias) {
                AttendantSchedule::query()->updateOrCreate(
                    ['attendant_name' => $alias],
                    $payload,
                );
            }
        }

        return back()->with('status', 'Disponibilidade salva com sucesso.');
    }

    /**
     * @return array<int, string>
     */
    private function professorAliases(string $name): array
    {
        return $this->scheduling->professorAliases($name);
    }

    /**
     * @param array<string, mixed> $baseSchedule
     * @param mixed $rawDaySettings
     * @return array<string, array{enabled: bool, start_time: string, end_time: string, break_start: string, break_end: string}>
     */
    private function defaultDaySettings(array $baseSchedule, mixed $rawDaySettings): array
    {
        $input = is_array($rawDaySettings) ? $rawDaySettings : [];

        $settings = [];
        foreach (self::ALLOWED_WORKING_DAYS as $day) {
            $item = is_array($input[$day] ?? null) ? $input[$day] : [];

            $enabledRaw = $item['enabled'] ?? in_array($day, $baseSchedule['working_days'] ?? [], true);
            $enabled = in_array((string) $enabledRaw, ['1', 'true', 'on'], true) || $enabledRaw === true;

            $settings[$day] = [
                'enabled' => $enabled,
                'start_time' => (string) (array_key_exists('start_time', $item) ? ($item['start_time'] ?? '') : ($baseSchedule['start_time'] ?? '08:00')),
                'end_time' => (string) (array_key_exists('end_time', $item) ? ($item['end_time'] ?? '') : ($baseSchedule['end_time'] ?? '17:00')),
                'break_start' => (string) (array_key_exists('break_start', $item) ? ($item['break_start'] ?? '') : ($baseSchedule['break_start'] ?? '12:00')),
                'break_end' => (string) (array_key_exists('break_end', $item) ? ($item['break_end'] ?? '') : ($baseSchedule['break_end'] ?? '13:00')),
            ];
        }

        return $settings;
    }

    /**
     * @param array<string, array{enabled: bool, start_time: string, end_time: string, break_start: string, break_end: string}> $daySettings
     * @return array<string, string>|null
     */
    private function validateDaySettings(array $daySettings): ?array
    {
        $hasEnabledDay = false;

        foreach (self::ALLOWED_WORKING_DAYS as $day) {
            $config = $daySettings[$day] ?? null;
            if (! is_array($config) || ! ($config['enabled'] ?? false)) {
                continue;
            }

            $hasEnabledDay = true;

            if (! preg_match('/^\d{2}:\d{2}$/', (string) ($config['start_time'] ?? '')) || ! preg_match('/^\d{2}:\d{2}$/', (string) ($config['end_time'] ?? ''))) {
                return ['working_days' => 'Informe horários válidos (HH:MM) para os dias habilitados.'];
            }

            $start = Carbon::createFromFormat('H:i', (string) ($config['start_time'] ?? ''));
            $end = Carbon::createFromFormat('H:i', (string) ($config['end_time'] ?? ''));

            if (! $start->lt($end)) {
                return ['working_days' => 'No dia selecionado, o horário de fim deve ser maior que o de início.'];
            }

            $breakStartValue = (string) ($config['break_start'] ?? '');
            $breakEndValue = (string) ($config['break_end'] ?? '');
            $hasBreakStart = $breakStartValue !== '';
            $hasBreakEnd = $breakEndValue !== '';

            if ($hasBreakStart xor $hasBreakEnd) {
                return ['working_days' => 'Em cada dia habilitado, informe início e fim do intervalo ou deixe ambos vazios.'];
            }

            if ($hasBreakStart && $hasBreakEnd) {
                if (! preg_match('/^\d{2}:\d{2}$/', $breakStartValue) || ! preg_match('/^\d{2}:\d{2}$/', $breakEndValue)) {
                    return ['working_days' => 'Informe horários válidos (HH:MM) para o intervalo dos dias habilitados.'];
                }

                $breakStart = Carbon::createFromFormat('H:i', $breakStartValue);
                $breakEnd = Carbon::createFromFormat('H:i', $breakEndValue);

                if (! $breakStart->lt($breakEnd)) {
                    return ['working_days' => 'No dia selecionado, o fim do intervalo deve ser maior que o início.'];
                }

                if (! $breakStart->gt($start) || ! $breakEnd->lt($end)) {
                    return ['working_days' => 'No dia selecionado, o intervalo deve ficar dentro da janela de atendimento.'];
                }
            }
        }

        if (! $hasEnabledDay) {
            return ['working_days' => 'Selecione ao menos um dia útil.'];
        }

        return null;
    }
}
