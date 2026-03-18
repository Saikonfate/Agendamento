<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AttendantSchedule;
use App\Models\BlockedDate;
use App\Models\User;
use App\Services\SchedulingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AdminScheduleController extends Controller
{
    private const ALLOWED_WORKING_DAYS = ['mon', 'tue', 'wed', 'thu', 'fri'];

    public function __construct(
        private SchedulingService $scheduling,
    ) {}

    public function index(Request $request): View
    {
        $blockedDates = BlockedDate::query()
            ->orderBy('blocked_date')
            ->get();

        $attendants = $this->availableAttendants();

        $selectedAttendant = $request->string('attendant')->toString();
        if ($selectedAttendant === '' || ! $attendants->contains($selectedAttendant)) {
            $selectedAttendant = (string) $attendants->first();
        }

        $schedule = AttendantSchedule::query()->where('attendant_name', $selectedAttendant)->first();

        $defaultSchedule = [
            'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'day_settings' => [],
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_start' => '12:00',
            'break_end' => '13:00',
            'slot_duration_minutes' => 30,
        ];

        $normalizedSchedule = $schedule ? [
            'working_days' => $schedule->working_days ?? $defaultSchedule['working_days'],
            'start_time' => Carbon::parse($schedule->start_time)->format('H:i'),
            'end_time' => Carbon::parse($schedule->end_time)->format('H:i'),
            'break_start' => $schedule->break_start ? Carbon::parse($schedule->break_start)->format('H:i') : '',
            'break_end' => $schedule->break_end ? Carbon::parse($schedule->break_end)->format('H:i') : '',
            'slot_duration_minutes' => $schedule->slot_duration_minutes,
        ] : $defaultSchedule;

        $normalizedSchedule['day_settings'] = $this->defaultDaySettings($normalizedSchedule, $schedule?->day_settings);

        return view('academic.admin-schedule', [
            'blockedDates' => $blockedDates,
            'attendants' => $attendants,
            'selectedAttendant' => $selectedAttendant,
            'schedule' => $normalizedSchedule,
        ]);
    }

    public function storeAttendantSchedule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'attendant_name' => ['required', 'string', 'max:255'],
            'slot_duration_minutes' => ['required', 'integer', 'in:30,45,60'],
        ], [
            'attendant_name.required' => 'Selecione o atendente.',
        ]);

        $baseSchedule = [
            'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_start' => '12:00',
            'break_end' => '13:00',
        ];

        $daySettings = $this->defaultDaySettings($baseSchedule, $request->input('day_settings', []));

        $daySettingsError = $this->validateDaySettings($daySettings, (int) $validated['slot_duration_minutes']);
        if ($daySettingsError !== null) {
            return back()->withErrors($daySettingsError)->withInput();
        }

        $workingDays = collect(self::ALLOWED_WORKING_DAYS)
            ->filter(fn (string $day) => (bool) ($daySettings[$day]['enabled'] ?? false))
            ->values()
            ->all();

        if (empty($workingDays)) {
            return back()->withErrors(['working_days' => 'Selecione ao menos um dia útil.'])->withInput();
        }

        $firstEnabledDay = collect(self::ALLOWED_WORKING_DAYS)
            ->first(fn (string $day) => (bool) ($daySettings[$day]['enabled'] ?? false));

        $firstDayConfig = $firstEnabledDay ? ($daySettings[$firstEnabledDay] ?? null) : null;
        if (! is_array($firstDayConfig)) {
            return back()->withErrors(['working_days' => 'Selecione ao menos um dia útil.'])->withInput();
        }

        $attendantIdentity = $this->scheduling->resolveAttendant($validated['attendant_name']);

        $keys = $attendantIdentity['user_id']
            ? ['attendant_user_id' => $attendantIdentity['user_id']]
            : ['attendant_name' => $validated['attendant_name']];

        AttendantSchedule::query()->updateOrCreate(
            $keys,
            [
                'attendant_name' => $validated['attendant_name'],
                'attendant_user_id' => $attendantIdentity['user_id'],
                'working_days' => $workingDays,
                'day_settings' => $daySettings,
                'start_time' => $firstDayConfig['start_time'],
                'end_time' => $firstDayConfig['end_time'],
                'break_start' => $firstDayConfig['break_start'] !== '' ? $firstDayConfig['break_start'] : null,
                'break_end' => $firstDayConfig['break_end'] !== '' ? $firstDayConfig['break_end'] : null,
                'slot_duration_minutes' => $validated['slot_duration_minutes'],
            ],
        );

        return redirect()
            ->route('academic.admin.schedule', ['attendant' => $validated['attendant_name']])
            ->with('status', 'Configuração de horários salva com sucesso.');
    }

    public function storeBlockedDate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'blocked_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:255'],
            'attendant_name' => ['nullable', 'string', 'max:255'],
        ], [
            'blocked_date.required' => 'Informe a data para bloqueio.',
            'reason.required' => 'Informe o motivo do bloqueio.',
        ]);

        $attendantName = $validated['attendant_name'] ?? null;
        if ($attendantName === '' || $attendantName === 'all') {
            $attendantName = null;
        }

        $attendantIdentity = $attendantName
            ? $this->scheduling->resolveAttendant($attendantName)
            : ['user_id' => null];

        BlockedDate::query()->updateOrCreate(
            [
                'blocked_date' => Carbon::parse($validated['blocked_date'])->toDateString(),
                'attendant_name' => $attendantName,
                'attendant_user_id' => $attendantIdentity['user_id'],
            ],
            [
                'reason' => $validated['reason'],
            ],
        );

        return back()->with('status', 'Data bloqueada com sucesso.');
    }

    public function destroyBlockedDate(BlockedDate $blockedDate): RedirectResponse
    {
        $blockedDate->delete();

        return back()->with('status', 'Bloqueio removido com sucesso.');
    }

    private function availableAttendants(): Collection
    {
        $appointmentAttendants = Appointment::query()
            ->select('attendant_name')
            ->whereNotNull('attendant_name')
            ->where('attendant_name', '!=', '')
            ->distinct()
            ->pluck('attendant_name');

        $scheduledAttendants = AttendantSchedule::query()
            ->select('attendant_name')
            ->whereNotNull('attendant_name')
            ->where('attendant_name', '!=', '')
            ->pluck('attendant_name');

        $professorAttendants = User::query()
            ->where('role', 'professor')
            ->pluck('name')
            ->map(fn (string $name) => $this->normalizeProfessorAttendantName($name));

        $attendants = $appointmentAttendants
            ->merge($scheduledAttendants)
            ->merge($professorAttendants)
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->map(fn (string $name) => trim($name))
            ->unique()
            ->sort()
            ->values();

        if ($attendants->isEmpty()) {
            return collect(['Sec. Acadêmica', 'Prof. Ramon', 'Prof. Ana Lima']);
        }

        return $attendants;
    }

    private function normalizeProfessorAttendantName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return $name;
        }

        $name = preg_replace('/^(prof\.?|professor)\s+/iu', '', $name) ?: $name;

        return 'Prof. '.trim($name);
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
    private function validateDaySettings(array $daySettings, int $slotDuration): ?array
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

                $breakMinutes = $breakStart->diffInMinutes($breakEnd);
            } else {
                $breakMinutes = 0;
            }

            $totalMinutes = $start->diffInMinutes($end);
            $effectiveMinutes = $totalMinutes - $breakMinutes;

            if ($effectiveMinutes < $slotDuration) {
                return ['working_days' => 'No dia selecionado, a janela útil é menor que a duração mínima de atendimento.'];
            }
        }

        if (! $hasEnabledDay) {
            return ['working_days' => 'Selecione ao menos um dia útil.'];
        }

        return null;
    }
}
