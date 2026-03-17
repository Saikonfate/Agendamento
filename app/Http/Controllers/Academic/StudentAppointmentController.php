<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AttendantSchedule;
use App\Models\BlockedDate;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class StudentAppointmentController extends Controller
{
    public function dashboard(Request $request): View
    {
        $user = $request->user();
        $registration = (string) ($user->matricula ?? '');

        $appointments = Appointment::query()
            ->where('student_registration', $registration)
            ->orderBy('scheduled_at')
            ->get();

        $activeCount = $appointments->whereIn('status', ['Confirmado', 'Pendente'])->count();
        $completedCount = $appointments->where('status', 'Realizado')->count();

        $today = Carbon::today();
        $todayAvailableCount = $this->availableSlotsCountForDate($today);

        $upcomingAppointments = $appointments
            ->filter(fn (Appointment $appointment) => $appointment->scheduled_at->greaterThanOrEqualTo(now()))
            ->whereIn('status', ['Confirmado', 'Pendente'])
            ->take(4)
            ->values();

        $notices = Notice::query()
            ->latest()
            ->limit(5)
            ->get(['message', 'tone']);

        $availableSlotsToday = $this->buildSlotsForDate($today);

        return view('academic.student-dashboard', [
            'activeCount' => $activeCount,
            'completedCount' => $completedCount,
            'todayAvailableCount' => $todayAvailableCount,
            'upcomingAppointments' => $upcomingAppointments,
            'notices' => $notices,
            'availableSlotsToday' => $availableSlotsToday,
            'todayLabel' => $today->format('d/m/Y'),
        ]);
    }

    public function create(Request $request): View
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->string('date')->toString())
            : Carbon::today()->addDay();

        $attendantFilter = old('attendant_name', $request->string('attendant')->toString());
        $subject = $request->string('subject')->toString();

        $attendants = $this->availableAttendants();

        if ($attendantFilter === '' || ! $attendants->contains($attendantFilter)) {
            $attendantFilter = (string) $attendants->first();
        }

        $attendantName = $attendantFilter !== '' ? $attendantFilter : null;
        $slots = $this->buildSlotsForDate($date, $attendantName);

        $monthStart = $date->copy()->startOfMonth();
        $daysInMonth = $monthStart->daysInMonth;
        $slotsByDate = [];
        $calendarDays = [];

        $slotsByDateByAttendant = [];
        $calendarDaysByAttendant = [];

        foreach ($attendants as $attendant) {
            $attendantDateSlots = [];
            $attendantCalendarDays = [];

            foreach (range(1, $daysInMonth) as $day) {
                $dayDate = $monthStart->copy()->day($day);
                $dateKey = $dayDate->format('Y-m-d');
                $dateSlots = $this->buildSlotsForDate($dayDate, $attendant);
                $availableCount = collect($dateSlots)->where('available', true)->count();

                $attendantDateSlots[$dateKey] = $dateSlots;
                $attendantCalendarDays[] = [
                    'date' => $dateKey,
                    'day' => $day,
                    'isToday' => $dayDate->isToday(),
                    'isSelected' => $dayDate->isSameDay($date),
                    'hasAvailability' => $availableCount > 0,
                    'isSystemDay' => $this->isSystemWorkingDay($dayDate, $attendant),
                ];
            }

            $slotsByDateByAttendant[$attendant] = $attendantDateSlots;
            $calendarDaysByAttendant[$attendant] = $attendantCalendarDays;
        }

        foreach (range(1, $daysInMonth) as $day) {
            $dayDate = $monthStart->copy()->day($day);
            $dateKey = $dayDate->format('Y-m-d');
            $dateSlots = $this->buildSlotsForDate($dayDate, $attendantName);
            $availableCount = collect($dateSlots)->where('available', true)->count();

            $slotsByDate[$dateKey] = $dateSlots;
            $calendarDays[] = [
                'date' => $dateKey,
                'day' => $day,
                'isToday' => $dayDate->isToday(),
                'isSelected' => $dayDate->isSameDay($date),
                'hasAvailability' => $availableCount > 0,
                'isSystemDay' => $this->isSystemWorkingDay($dayDate),
            ];
        }

        return view('academic.student-new', [
            'attendants' => $attendants,
            'selectedDate' => $date,
            'selectedAttendant' => $attendantName,
            'subject' => $subject,
            'slots' => $slots,
            'slotsByDate' => $slotsByDate,
            'slotsByDateByAttendant' => $slotsByDateByAttendant,
            'calendarDays' => $calendarDays,
            'calendarDaysByAttendant' => $calendarDaysByAttendant,
            'calendarMonthLabel' => $date->copy()->locale('pt_BR')->translatedFormat('F/Y'),
            'selectedTime' => $request->string('time')->toString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $registration = (string) ($user->matricula ?? '');

        $validated = $request->validate([
            'attendant_name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
        ], [
            'attendant_name.required' => 'Selecione o atendente.',
            'subject.required' => 'Informe o motivo do atendimento.',
            'date.required' => 'Selecione a data.',
            'time.required' => 'Selecione o horário.',
        ]);

        $scheduledAt = Carbon::createFromFormat('Y-m-d H:i', $validated['date'].' '.$validated['time']);

        if (! $this->isSystemWorkingDay($scheduledAt)) {
            return back()->withInput()->withErrors([
                'date' => 'Esta data não está disponível no calendário do sistema.',
            ]);
        }

        $availableSlots = $this->buildSlotsForDate($scheduledAt, $validated['attendant_name']);
        $slotAvailable = collect($availableSlots)
            ->contains(fn (array $slot) => $slot['time'] === $validated['time'] && $slot['available'] === true);

        if (! $slotAvailable) {
            return back()->withInput()->withErrors([
                'time' => 'Este horário não está disponível no calendário do sistema.',
            ]);
        }

        $conflict = Appointment::query()
            ->where('attendant_name', $validated['attendant_name'])
            ->where('scheduled_at', $scheduledAt)
            ->whereIn('status', ['Confirmado', 'Pendente'])
            ->exists();

        if ($conflict) {
            return back()->withInput()->withErrors([
                'time' => 'Este horário já foi ocupado. Selecione outro horário.',
            ]);
        }

        Appointment::query()->create([
            'student_name' => $user->name,
            'student_registration' => $registration,
            'attendant_name' => $validated['attendant_name'],
            'subject' => $validated['subject'],
            'scheduled_at' => $scheduledAt,
            'status' => 'Pendente',
        ]);

        return redirect()->route('academic.student.mine')->with('status', 'Agendamento criado com sucesso.');
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $registration = (string) ($user->matricula ?? '');

        $statusFilter = $request->string('status')->toString();
        $search = $request->string('q')->toString();

        $query = Appointment::query()
            ->where('student_registration', $registration)
            ->orderByDesc('scheduled_at');

        if ($statusFilter !== '' && $statusFilter !== 'Todos') {
            $normalizedStatus = ucfirst(strtolower($statusFilter));
            $query->where('status', $normalizedStatus);
        }

        if ($search !== '') {
            $query->where(function ($innerQuery) use ($search) {
                $innerQuery
                    ->where('subject', 'like', '%'.$search.'%')
                    ->orWhere('attendant_name', 'like', '%'.$search.'%')
                    ->orWhere('student_name', 'like', '%'.$search.'%');
            });
        }

        $appointments = $query->get();

        return view('academic.student-appointments', [
            'appointments' => $appointments,
            'statusFilter' => $statusFilter !== '' ? $statusFilter : 'Todos',
            'search' => $search,
        ]);
    }

    public function cancel(Request $request, Appointment $appointment): RedirectResponse
    {
        /** @var Appointment $appointment */
        $registration = (string) ($request->user()?->matricula ?? '');

        if ($appointment->student_registration !== $registration) {
            abort(403);
        }

        if (! in_array($appointment->status, ['Confirmado', 'Pendente'], true)) {
            return back()->with('status', 'Este agendamento não pode mais ser cancelado.');
        }

        $appointment->update([
            'status' => 'Cancelado',
        ]);

        return back()->with('status', 'Agendamento cancelado com sucesso.');
    }

    private function buildSlotsForDate(Carbon $date, ?string $attendantFilter = null): array
    {
        $slots = $this->systemWorkingSlots($date, $attendantFilter);

        if (! $this->isSystemWorkingDay($date, $attendantFilter) || $this->isDateBlocked($date, $attendantFilter)) {
            return collect($slots)
                ->map(fn (string $slot) => [
                    'time' => $slot,
                    'available' => false,
                ])
                ->all();
        }

        $query = Appointment::query()
            ->whereDate('scheduled_at', $date)
            ->whereIn('status', ['Confirmado', 'Pendente']);

        if ($attendantFilter) {
            $query->where('attendant_name', $attendantFilter);
        }

        $occupied = $query->get()->pluck('scheduled_at')->map(fn ($dateTime) => Carbon::parse($dateTime)->format('H:i'))->all();

        return collect($slots)
            ->map(fn (string $slot) => [
                'time' => $slot,
                'available' => ! in_array($slot, $occupied, true),
            ])
            ->all();
    }

    private function availableSlotsCountForDate(Carbon $date, ?string $attendantFilter = null): int
    {
        return collect($this->buildSlotsForDate($date, $attendantFilter))
            ->where('available', true)
            ->count();
    }

    private function systemWorkingSlots(Carbon $date, ?string $attendantName = null): array
    {
        $schedule = $this->resolveScheduleForDate($attendantName, $date);
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

    private function isSystemWorkingDay(Carbon $date, ?string $attendantName = null): bool
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

    /**
     * @return array{working_days: array<int, string>, start_time: string, end_time: string, break_start: string|null, break_end: string|null, slot_duration_minutes: int}
     */
    private function resolveScheduleForDate(?string $attendantName, Carbon $date): array
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

    private function isDateBlocked(Carbon $date, ?string $attendantName = null): bool
    {
        $query = BlockedDate::query()
            ->whereDate('blocked_date', $date->toDateString())
            ->where(function ($innerQuery) use ($attendantName) {
                $innerQuery->whereNull('attendant_name');

                if ($attendantName) {
                    $innerQuery->orWhere('attendant_name', $attendantName);
                }
            });

        return $query->exists();
    }

    private function resolveSchedule(?string $attendantName = null): array
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

        if (! $attendantName) {
            return $default;
        }

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
}
