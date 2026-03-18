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
            ->with('attendantUser:id,name')
            ->orderBy('blocked_date')
            ->get();

        $attendants = $this->availableAttendants();

        $selectedAttendantKey = $request->string('attendant')->toString();

        if ($selectedAttendantKey === '' && $request->filled('attendant_user_id')) {
            $selectedAttendantKey = 'user:'.$request->integer('attendant_user_id');
        }

        if ($selectedAttendantKey === '' && $request->filled('attendant_name')) {
            $selectedAttendantKey = 'name:'.$request->string('attendant_name')->toString();
        }

        if ($selectedAttendantKey === '' || ! $attendants->contains(fn (array $attendant) => $attendant['key'] === $selectedAttendantKey)) {
            $firstAttendant = $attendants->first();
            $selectedAttendantKey = is_array($firstAttendant) ? (string) ($firstAttendant['key'] ?? '') : '';
        }

        $selectedAttendant = $attendants->first(fn (array $attendant) => $attendant['key'] === $selectedAttendantKey);
        $selectedAttendantName = is_array($selectedAttendant) ? (string) ($selectedAttendant['name'] ?? '') : '';
        $selectedAttendantUserId = is_array($selectedAttendant) ? ($selectedAttendant['user_id'] ?? null) : null;

        $scheduleQuery = AttendantSchedule::query();

        if ($selectedAttendantUserId) {
            $scheduleQuery->where(function ($query) use ($selectedAttendantUserId, $selectedAttendantName) {
                $query->where('attendant_user_id', $selectedAttendantUserId)
                    ->orWhere('attendant_name', $selectedAttendantName);
            });
        } else {
            $scheduleQuery->where('attendant_name', $selectedAttendantName);
        }

        $schedule = $scheduleQuery->first();

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
            'selectedAttendantKey' => $selectedAttendantKey,
            'selectedAttendantName' => $selectedAttendantName,
            'schedule' => $normalizedSchedule,
        ]);
    }

    public function storeAttendantSchedule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'attendant_key' => ['nullable', 'string', 'required_without:attendant_name'],
            'attendant_name' => ['nullable', 'string', 'max:255', 'required_without:attendant_key'],
            'slot_duration_minutes' => ['required', 'integer', 'in:30,45,60'],
        ], [
            'attendant_key.required_without' => 'Selecione o atendente.',
            'attendant_name.required_without' => 'Selecione o atendente.',
        ]);

        $attendantName = null;
        $attendantUserId = null;

        if (! empty($validated['attendant_key'] ?? null)) {
            $selectedAttendant = $this->availableAttendants()
                ->first(fn (array $attendant) => $attendant['key'] === $validated['attendant_key']);

            if (is_array($selectedAttendant)) {
                $attendantName = (string) ($selectedAttendant['name'] ?? '');
                $attendantUserId = $selectedAttendant['user_id'] ?? null;
            }
        }

        if (! $attendantName && ! empty($validated['attendant_name'] ?? null)) {
            $identity = $this->scheduling->resolveAttendant($validated['attendant_name']);
            $attendantName = $validated['attendant_name'];
            $attendantUserId = $identity['user_id'];
        }

        if (! $attendantName) {
            return back()->withErrors(['attendant_key' => 'Selecione um atendente válido.'])->withInput();
        }

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

        $keys = $attendantUserId
            ? ['attendant_user_id' => $attendantUserId]
            : ['attendant_name' => $attendantName];

        AttendantSchedule::query()->updateOrCreate(
            $keys,
            [
                'attendant_name' => $attendantName,
                'attendant_user_id' => $attendantUserId,
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
            ->route('academic.admin.schedule', ['attendant' => $attendantUserId ? 'user:'.$attendantUserId : 'name:'.$attendantName])
            ->with('status', 'Configuração de horários salva com sucesso.');
    }

    public function storeBlockedDate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'blocked_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:255'],
            'attendant_key' => ['nullable', 'string', 'max:255'],
            'attendant_name' => ['nullable', 'string', 'max:255'],
        ], [
            'blocked_date.required' => 'Informe a data para bloqueio.',
            'reason.required' => 'Informe o motivo do bloqueio.',
        ]);

        $attendantName = null;
        $attendantUserId = null;

        $attendantKey = (string) ($validated['attendant_key'] ?? '');

        if ($attendantKey !== '' && $attendantKey !== 'all') {
            $selectedAttendant = $this->availableAttendants()
                ->first(fn (array $attendant) => $attendant['key'] === $attendantKey);

            if (is_array($selectedAttendant)) {
                $attendantName = (string) ($selectedAttendant['name'] ?? '');
                $attendantUserId = $selectedAttendant['user_id'] ?? null;
            }
        }

        if (! $attendantName && ! empty($validated['attendant_name'] ?? null)) {
            $identity = $this->scheduling->resolveAttendant($validated['attendant_name']);
            $attendantName = $validated['attendant_name'];
            $attendantUserId = $identity['user_id'];
        }

        if ($attendantKey === 'all') {
            $attendantName = null;
            $attendantUserId = null;
        }

        BlockedDate::query()->updateOrCreate(
            [
                'blocked_date' => Carbon::parse($validated['blocked_date'])->toDateString(),
                'attendant_name' => $attendantName,
                'attendant_user_id' => $attendantUserId,
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
        $attendants = [];

        $addAttendant = function (string $name, ?int $userId = null) use (&$attendants): void {
            $name = trim($name);
            if ($name === '') {
                return;
            }

            $key = $userId ? 'user:'.$userId : 'name:'.$name;
            if (array_key_exists($key, $attendants)) {
                return;
            }

            $attendants[$key] = [
                'key' => $key,
                'name' => $name,
                'user_id' => $userId,
            ];
        };

        User::query()
            ->where('role', 'professor')
            ->get(['id', 'name'])
            ->each(function (User $user) use ($addAttendant): void {
                $addAttendant($this->normalizeProfessorAttendantName($user->name), $user->id);
            });

        AttendantSchedule::query()
            ->where(function ($query) {
                $query->whereNotNull('attendant_user_id')
                    ->orWhere(function ($innerQuery) {
                        $innerQuery->whereNotNull('attendant_name')->where('attendant_name', '!=', '');
                    });
            })
            ->get(['attendant_name', 'attendant_user_id'])
            ->each(function (AttendantSchedule $schedule) use ($addAttendant): void {
                $resolved = $this->scheduling->resolveAttendant($schedule->attendant_name ?? '', $schedule->attendant_user_id);
                $resolvedName = trim((string) ($resolved['name'] ?? $schedule->attendant_name ?? ''));

                if ($resolvedName !== '') {
                    $addAttendant($resolvedName, $resolved['user_id'] ?? null);
                }
            });

        Appointment::query()
            ->where(function ($query) {
                $query->whereNotNull('attendant_user_id')
                    ->orWhere(function ($innerQuery) {
                        $innerQuery->whereNotNull('attendant_name')->where('attendant_name', '!=', '');
                    });
            })
            ->get(['attendant_name', 'attendant_user_id'])
            ->each(function (Appointment $appointment) use ($addAttendant): void {
                $resolved = $this->scheduling->resolveAttendant($appointment->attendant_name ?? '', $appointment->attendant_user_id);
                $resolvedName = trim((string) ($resolved['name'] ?? $appointment->attendant_name ?? ''));

                if ($resolvedName !== '') {
                    $addAttendant($resolvedName, $resolved['user_id'] ?? null);
                }
            });

        if (empty($attendants)) {
            $addAttendant('Sec. Acadêmica');
            $addAttendant('Prof. Ramon');
            $addAttendant('Prof. Ana Lima');
        }

        return collect(array_values($attendants))
            ->sortBy('name')
            ->values();
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
