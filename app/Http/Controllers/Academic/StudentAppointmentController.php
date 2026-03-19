<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AttendantSchedule;
use App\Models\BlockedDate;
use App\Models\Notice;
use App\Models\User;
use App\Services\SchedulingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class StudentAppointmentController extends Controller
{
    public function __construct(
        private SchedulingService $scheduling,
    ) {}

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        $registration = (string) ($user->matricula ?? '');
        $attendants = $this->availableAttendants();

        $selectedAttendantKey = $request->string('attendant')->toString();

        if ($selectedAttendantKey === '' && $request->filled('attendant_user_id')) {
            $selectedAttendantKey = 'user:'.$request->integer('attendant_user_id');
        }

        if ($selectedAttendantKey === '' && $request->filled('attendant_name')) {
            $selectedAttendantKey = 'name:'.$request->string('attendant_name')->toString();
        }

        if ($selectedAttendantKey === '' || ! $attendants->contains(fn (array $attendant) => $attendant['key'] === $selectedAttendantKey)) {
            $selectedAttendantKey = '';
        }

        $selectedAttendant = $attendants
            ->first(fn (array $attendant) => $attendant['key'] === $selectedAttendantKey);

        $selectedAttendantName = is_array($selectedAttendant) ? (string) ($selectedAttendant['name'] ?? '') : '';
        $selectedAttendantUserId = is_array($selectedAttendant) ? ($selectedAttendant['user_id'] ?? null) : null;

        $appointments = Appointment::query()
            ->with('attendantUser:id,name')
            ->where('student_registration', $registration)
            ->orderBy('scheduled_at')
            ->get();

        $activeCount = $appointments->whereIn('status', ['Confirmado', 'Pendente'])->count();
        $completedCount = $appointments->where('status', 'Realizado')->count();

        $today = Carbon::today();
        $minimumReferenceDate = $today->copy()->addDays(SchedulingService::MIN_DAYS_IN_ADVANCE);
        $todayAvailableCount = $selectedAttendantName !== ''
            ? $this->availableSlotsCountForDate($minimumReferenceDate, $selectedAttendantName, $selectedAttendantUserId)
            : 0;

        $monthStart = $today->copy()->startOfMonth();
        $daysInMonth = $monthStart->daysInMonth;
        $calendarMonthLabel = ucfirst($monthStart->copy()->locale('pt_BR')->translatedFormat('M/Y'));
        $blockedDates = BlockedDate::query()
            ->whereBetween('blocked_date', [$monthStart->copy()->startOfMonth(), $monthStart->copy()->endOfMonth()])
            ->get(['blocked_date', 'reason', 'attendant_name', 'attendant_user_id']);

        $calendarDays = [];

        $leadingDays = (int) $monthStart->dayOfWeek;
        for ($index = 0; $index < $leadingDays; $index++) {
            $calendarDays[] = [
                'day' => null,
                'isToday' => false,
                'status' => 'empty',
            ];
        }

        foreach (range(1, $daysInMonth) as $day) {
            $dayDate = $monthStart->copy()->day($day);
            $status = 'neutral';
            $unavailabilityReason = null;

            if ($selectedAttendantName !== '') {
                $isSystemDay = $this->isSystemWorkingDay($dayDate, $selectedAttendantName, $selectedAttendantUserId);
                $availableCount = $this->availableSlotsCountForDate($dayDate, $selectedAttendantName, $selectedAttendantUserId);
                $unavailabilityReason = $this->calendarUnavailabilityReason($dayDate, $selectedAttendantName, $selectedAttendantUserId, $blockedDates);

                if (! $isSystemDay) {
                    $status = 'unavailable';
                } elseif ($availableCount > 0) {
                    $status = 'available';
                } else {
                    $status = 'full';
                }

                if ($unavailabilityReason === null && $status === 'full') {
                    $unavailabilityReason = 'Sem horários disponíveis neste dia.';
                }
            } else {
                $isAnySystemDay = false;
                $hasAnyAvailability = false;

                $globalBlockedReasons = $blockedDates
                    ->filter(fn (BlockedDate $blockedDate): bool => $blockedDate->blocked_date->isSameDay($dayDate))
                    ->filter(fn (BlockedDate $blockedDate): bool => $blockedDate->attendant_user_id === null && trim((string) ($blockedDate->attendant_name ?? '')) === '')
                    ->pluck('reason')
                    ->filter(fn (?string $reason): bool => trim((string) $reason) !== '')
                    ->unique()
                    ->values();

                foreach ($attendants as $attendant) {
                    $attendantName = (string) ($attendant['name'] ?? '');
                    $attendantUserId = $attendant['user_id'] ?? null;

                    if ($attendantName === '') {
                        continue;
                    }

                    $isSystemDay = $this->isSystemWorkingDay($dayDate, $attendantName, $attendantUserId);

                    if (! $isSystemDay) {
                        continue;
                    }

                    $isAnySystemDay = true;

                    $availableCount = $this->availableSlotsCountForDate($dayDate, $attendantName, $attendantUserId);
                    if ($availableCount > 0) {
                        $hasAnyAvailability = true;
                        break;
                    }
                }

                if (! $isAnySystemDay) {
                    $status = 'unavailable';
                    $unavailabilityReason = $globalBlockedReasons->isNotEmpty()
                        ? $globalBlockedReasons->implode(' · ')
                        : 'Dia sem expediente para os atendentes disponíveis.';
                } elseif ($hasAnyAvailability) {
                    $status = 'available';
                } else {
                    $status = 'full';
                    $unavailabilityReason = $globalBlockedReasons->isNotEmpty()
                        ? $globalBlockedReasons->implode(' · ')
                        : 'Sem horários disponíveis neste dia.';
                }
            }

            $calendarDays[] = [
                'day' => $day,
                'isToday' => $dayDate->isToday(),
                'status' => $status,
                'unavailability_reason' => $unavailabilityReason,
            ];
        }

        $upcomingAppointments = $appointments
            ->filter(fn (Appointment $appointment) => $appointment->scheduled_at->greaterThanOrEqualTo(now()))
            ->whereIn('status', ['Confirmado', 'Pendente'])
            ->take(4)
            ->values();

        $notices = Notice::query()
            ->latest()
            ->limit(5)
            ->get(['message', 'tone']);

        $slotsReferenceDate = $minimumReferenceDate->copy();
        $availableSlotsToday = [];

        if ($selectedAttendantName !== '') {
            $availableSlotsToday = $this->buildSlotsForDate($slotsReferenceDate, $selectedAttendantName, $selectedAttendantUserId);
            $hasAvailabilityOnReferenceDate = collect($availableSlotsToday)->contains(fn (array $slot) => $slot['available'] === true);

            if (! $hasAvailabilityOnReferenceDate) {
                $nextAvailableDate = $this->nextAvailableDate($slotsReferenceDate->copy()->addDay(), $selectedAttendantName, $selectedAttendantUserId);

                if ($nextAvailableDate) {
                    $slotsReferenceDate = $nextAvailableDate;
                    $availableSlotsToday = $this->buildSlotsForDate($slotsReferenceDate, $selectedAttendantName, $selectedAttendantUserId);
                }
            }
        }

        return view('academic.student-dashboard', [
            'activeCount' => $activeCount,
            'completedCount' => $completedCount,
            'todayAvailableCount' => $todayAvailableCount,
            'attendants' => $attendants,
            'selectedAttendantKey' => $selectedAttendantKey,
            'selectedAttendantName' => $selectedAttendantName,
            'calendarMonthLabel' => $calendarMonthLabel,
            'calendarDays' => $calendarDays,
            'upcomingAppointments' => $upcomingAppointments,
            'notices' => $notices,
            'availableSlotsToday' => $availableSlotsToday,
            'todayLabel' => $today->format('d/m/Y'),
            'slotsReferenceDateLabel' => $slotsReferenceDate->format('d/m/Y'),
            'isSlotsReferenceToday' => $slotsReferenceDate->isToday(),
        ]);
    }

    public function create(Request $request): View
    {
        $rescheduleAppointmentId = $request->integer('appointment_id');

        $dateWasProvided = $request->filled('date');

        $date = $request->filled('date')
            ? Carbon::parse($request->string('date')->toString())
            : Carbon::today();

        $attendantFilter = old('attendant_key', $request->string('attendant')->toString());

        if ($attendantFilter === '' && $request->filled('attendant_user_id')) {
            $attendantFilter = 'user:'.$request->integer('attendant_user_id');
        }

        if ($attendantFilter === '' && $request->filled('attendant_name')) {
            $attendantFilter = 'name:'.$request->string('attendant_name')->toString();
        }

        $subject = $request->string('subject')->toString();

        $attendants = $this->availableAttendants();

        if ($attendantFilter === '' || ! $attendants->contains(fn (array $attendant) => $attendant['key'] === $attendantFilter)) {
            $firstAttendant = $attendants->first();
            $attendantFilter = is_array($firstAttendant) ? (string) ($firstAttendant['key'] ?? '') : '';
        }

        $selectedAttendant = $attendants
            ->first(fn (array $attendant) => $attendant['key'] === $attendantFilter);

        $attendantName = is_array($selectedAttendant) ? (string) ($selectedAttendant['name'] ?? '') : null;
        $attendantUserId = is_array($selectedAttendant) ? ($selectedAttendant['user_id'] ?? null) : null;

        if ($attendantName !== null && $date->isToday() && (! $dateWasProvided || $request->string('date')->toString() === Carbon::today()->toDateString())) {
            $todaySlots = $this->buildSlotsForDate($date, $attendantName, $attendantUserId);
            $hasFutureAvailabilityToday = collect($todaySlots)->contains(fn (array $slot) => $slot['available'] === true);

            if (! $hasFutureAvailabilityToday) {
                $nextAvailableDate = $this->nextAvailableDate($date->copy()->addDay(), $attendantName, $attendantUserId);

                if ($nextAvailableDate) {
                    $date = $nextAvailableDate;
                }
            }
        }

        $slots = $this->buildSlotsForDate($date, $attendantName, $attendantUserId);

        $monthStart = $date->copy()->startOfMonth();
        $daysInMonth = $monthStart->daysInMonth;
        $blockedDates = BlockedDate::query()
            ->whereBetween('blocked_date', [$monthStart->copy()->startOfMonth(), $monthStart->copy()->endOfMonth()])
            ->get(['blocked_date', 'reason', 'attendant_name', 'attendant_user_id']);

        $slotsByDate = [];
        $calendarDays = [];

        $slotsByDateByAttendant = [];
        $calendarDaysByAttendant = [];

        foreach ($attendants as $attendant) {
            $attendantKey = (string) ($attendant['key'] ?? '');
            $attendantNameInLoop = (string) ($attendant['name'] ?? '');
            $attendantUserIdInLoop = $attendant['user_id'] ?? null;

            if ($attendantKey === '' || $attendantNameInLoop === '') {
                continue;
            }

            $attendantDateSlots = [];
            $attendantCalendarDays = [];

            $leadingDays = (int) $monthStart->dayOfWeek;
            for ($index = 0; $index < $leadingDays; $index++) {
                $attendantCalendarDays[] = [
                    'date' => null,
                    'day' => null,
                    'isToday' => false,
                    'isSelected' => false,
                    'hasAvailability' => false,
                    'isSystemDay' => false,
                    'unavailability_reason' => null,
                    'status' => 'empty',
                ];
            }

            foreach (range(1, $daysInMonth) as $day) {
                $dayDate = $monthStart->copy()->day($day);
                $dateKey = $dayDate->format('Y-m-d');
                $dateSlots = $this->buildSlotsForDate($dayDate, $attendantNameInLoop, $attendantUserIdInLoop);
                $availableCount = collect($dateSlots)->where('available', true)->count();
                $isSystemDay = $this->isSystemWorkingDay($dayDate, $attendantNameInLoop, $attendantUserIdInLoop);
                $unavailabilityReason = $this->calendarUnavailabilityReason($dayDate, $attendantNameInLoop, $attendantUserIdInLoop, $blockedDates);

                if ($unavailabilityReason === null && $availableCount === 0) {
                    $unavailabilityReason = 'Sem horários disponíveis neste dia.';
                }

                $attendantDateSlots[$dateKey] = $dateSlots;
                $attendantCalendarDays[] = [
                    'date' => $dateKey,
                    'day' => $day,
                    'isToday' => $dayDate->isToday(),
                    'isSelected' => $dayDate->isSameDay($date),
                    'hasAvailability' => $availableCount > 0,
                    'isSystemDay' => $isSystemDay,
                    'unavailability_reason' => $unavailabilityReason,
                    'status' => 'filled',
                ];
            }

            $slotsByDateByAttendant[$attendantKey] = $attendantDateSlots;
            $calendarDaysByAttendant[$attendantKey] = $attendantCalendarDays;
        }

        $leadingDays = (int) $monthStart->dayOfWeek;
        for ($index = 0; $index < $leadingDays; $index++) {
            $calendarDays[] = [
                'date' => null,
                'day' => null,
                'isToday' => false,
                'isSelected' => false,
                'hasAvailability' => false,
                'isSystemDay' => false,
                'unavailability_reason' => null,
                'status' => 'empty',
            ];
        }

        foreach (range(1, $daysInMonth) as $day) {
            $dayDate = $monthStart->copy()->day($day);
            $dateKey = $dayDate->format('Y-m-d');
            $dateSlots = $this->buildSlotsForDate($dayDate, $attendantName, $attendantUserId);
            $availableCount = collect($dateSlots)->where('available', true)->count();
            $isSystemDay = $this->isSystemWorkingDay($dayDate, $attendantName, $attendantUserId);
            $unavailabilityReason = $this->calendarUnavailabilityReason($dayDate, $attendantName, $attendantUserId, $blockedDates);

            if ($unavailabilityReason === null && $availableCount === 0) {
                $unavailabilityReason = 'Sem horários disponíveis neste dia.';
            }

            $slotsByDate[$dateKey] = $dateSlots;
            $calendarDays[] = [
                'date' => $dateKey,
                'day' => $day,
                'isToday' => $dayDate->isToday(),
                'isSelected' => $dayDate->isSameDay($date),
                'hasAvailability' => $availableCount > 0,
                'isSystemDay' => $isSystemDay,
                'unavailability_reason' => $unavailabilityReason,
                'status' => 'filled',
            ];
        }

        return view('academic.student-new', [
            'attendants' => $attendants,
            'rescheduleAppointmentId' => $rescheduleAppointmentId > 0 ? $rescheduleAppointmentId : null,
            'selectedDate' => $date,
            'selectedAttendantKey' => $attendantFilter,
            'selectedAttendantName' => $attendantName,
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

        $this->scheduling->applyAutomaticNoShowRules();

        $validated = $request->validate([
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'attendant_key' => ['nullable', 'string', 'required_without:attendant_name'],
            'attendant_name' => ['nullable', 'string', 'max:255', 'required_without:attendant_key'],
            'subject' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
        ], [
            'attendant_key.required_without' => 'Selecione o atendente.',
            'attendant_name.required_without' => 'Selecione o atendente.',
            'subject.required' => 'Informe o motivo do atendimento.',
            'date.required' => 'Selecione a data.',
            'time.required' => 'Selecione o horário.',
        ]);

        $attendantName = null;
        $attendantUserId = null;
        $appointmentToReschedule = null;

        $appointmentId = (int) ($validated['appointment_id'] ?? 0);

        if ($appointmentId > 0) {
            $appointmentToReschedule = Appointment::query()
                ->whereKey($appointmentId)
                ->where('student_registration', $registration)
                ->first();

            if (! $appointmentToReschedule) {
                return back()->withInput()->withErrors([
                    'time' => 'Agendamento para reagendamento não encontrado.',
                ]);
            }

            if (! in_array($appointmentToReschedule->status, ['Confirmado', 'Pendente'], true)) {
                return back()->withInput()->withErrors([
                    'time' => 'Este agendamento não pode mais ser reagendado.',
                ]);
            }

            if (! $this->scheduling->canModifyScheduledAppointment($appointmentToReschedule->scheduled_at)) {
                return back()->withInput()->withErrors([
                    'time' => $this->scheduling->modificationWindowMessage(),
                ]);
            }
        }

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
            return back()->withInput()->withErrors([
                'attendant_key' => 'Selecione um atendente válido.',
            ]);
        }

        $scheduledAt = Carbon::createFromFormat('Y-m-d H:i', $validated['date'].' '.$validated['time']);

        $schedulingError = $this->scheduling->schedulingValidationError($scheduledAt);
        if ($schedulingError !== null) {
            return back()->withInput()->withErrors([
                'time' => $this->scheduling->schedulingValidationMessage($schedulingError),
            ]);
        }

        if ($this->scheduling->isStudentBlockedByNoShow($registration)) {
            return back()->withInput()->withErrors([
                'time' => $this->scheduling->studentNoShowBlockMessage(),
            ]);
        }

        if ($this->scheduling->hasReachedStudentActiveLimit($registration, $appointmentToReschedule?->id)) {
            return back()->withInput()->withErrors([
                'time' => 'Você já atingiu o limite de '.SchedulingService::MAX_ACTIVE_APPOINTMENTS_PER_STUDENT.' agendamentos ativos.',
            ]);
        }

        $studentConflict = $this->scheduling->hasStudentActiveConflict($registration, $scheduledAt, $appointmentToReschedule?->id);

        if ($studentConflict) {
            return back()->withInput()->withErrors([
                'time' => 'Você já possui um agendamento ativo nesse mesmo horário.',
            ]);
        }

        if (! $this->scheduling->hasAttendantShiftCapacity($scheduledAt, $attendantName, $attendantUserId, $appointmentToReschedule?->id)) {
            return back()->withInput()->withErrors([
                'time' => 'Capacidade máxima do atendente para este turno foi atingida.',
            ]);
        }

        if (! $this->isSystemWorkingDay($scheduledAt, $attendantName, $attendantUserId)) {
            return back()->withInput()->withErrors([
                'date' => 'Esta data está indisponível por regra do calendário do sistema.',
            ]);
        }

        $availableSlots = $this->buildSlotsForDate($scheduledAt, $attendantName, $attendantUserId);
        $slotAvailable = collect($availableSlots)
            ->contains(fn (array $slot) => $slot['time'] === $validated['time'] && $slot['available'] === true);

        if (! $slotAvailable) {
            return back()->withInput()->withErrors([
                'time' => 'Este horário está indisponível por regra do calendário do sistema.',
            ]);
        }

        $conflict = $this->scheduling->hasActiveConflict(
            $scheduledAt,
            $attendantName,
            $attendantUserId,
            $appointmentToReschedule?->id,
        );

        if ($conflict) {
            return back()->withInput()->withErrors([
                'time' => 'Este horário já foi ocupado. Selecione outro horário.',
            ]);
        }

        if ($appointmentToReschedule) {
            $appointmentToReschedule->update([
                'student_name' => $user->name,
                'attendant_name' => $attendantName,
                'attendant_user_id' => $attendantUserId,
                'subject' => $validated['subject'],
                'scheduled_at' => $scheduledAt,
                'status' => 'Pendente',
            ]);

            return redirect()->route('academic.student.mine')->with('status', 'Agendamento reagendado com sucesso.');
        }

        Appointment::query()->create([
            'student_name' => $user->name,
            'student_registration' => $registration,
            'attendant_name' => $attendantName,
            'attendant_user_id' => $attendantUserId,
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
            ->with('attendantUser:id,name')
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

        if (! $this->scheduling->canModifyScheduledAppointment($appointment->scheduled_at)) {
            return back()->with('status', $this->scheduling->modificationWindowMessage());
        }

        $appointment->update([
            'status' => 'Cancelado',
            'cancellation_reason' => 'Cancelado pelo aluno',
        ]);

        return back()->with('status', 'Agendamento cancelado com sucesso.');
    }

    private function buildSlotsForDate(Carbon $date, ?string $attendantFilter = null, ?int $attendantUserId = null): array
    {
        return $this->scheduling->buildSlotsForDate($date, $attendantFilter, $attendantUserId);
    }

    private function availableSlotsCountForDate(Carbon $date, ?string $attendantFilter = null, ?int $attendantUserId = null): int
    {
        return collect($this->buildSlotsForDate($date, $attendantFilter, $attendantUserId))
            ->where('available', true)
            ->count();
    }

    private function systemWorkingSlots(Carbon $date, ?string $attendantName = null, ?int $attendantUserId = null): array
    {
        return collect($this->scheduling->buildSlotsForDate($date, $attendantName, $attendantUserId))
            ->pluck('time')
            ->all();
    }

    private function isSystemWorkingDay(Carbon $date, ?string $attendantName = null, ?int $attendantUserId = null): bool
    {
        return $this->scheduling->isSystemWorkingDay($date, $attendantName, $attendantUserId);
    }

    /**
     * @return array{working_days: array<int, string>, start_time: string, end_time: string, break_start: string|null, break_end: string|null, slot_duration_minutes: int}
     */
    private function resolveScheduleForDate(?string $attendantName, Carbon $date, ?int $attendantUserId = null): array
    {
        return $this->scheduling->resolveScheduleForDate($date, $attendantName, $attendantUserId);
    }

    private function isDateBlocked(Carbon $date, ?string $attendantName = null, ?int $attendantUserId = null): bool
    {
        return $this->scheduling->isDateBlocked($date, $attendantName, $attendantUserId);
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

    private function nextAvailableDate(Carbon $startDate, string $attendantName, ?int $attendantUserId = null, int $maxDaysAhead = 60): ?Carbon
    {
        for ($offset = 0; $offset <= $maxDaysAhead; $offset++) {
            $candidate = $startDate->copy()->addDays($offset);

            if (! $this->isSystemWorkingDay($candidate, $attendantName, $attendantUserId)) {
                continue;
            }

            $hasAvailability = collect($this->buildSlotsForDate($candidate, $attendantName, $attendantUserId))
                ->contains(fn (array $slot) => $slot['available'] === true);

            if ($hasAvailability) {
                return $candidate;
            }
        }

        return null;
    }

    private function calendarUnavailabilityReason(
        Carbon $date,
        ?string $attendantName,
        ?int $attendantUserId,
        Collection $blockedDates,
    ): ?string {
        if ($date->isWeekend()) {
            return 'Fim de semana.';
        }

        if (! $this->scheduling->isSystemWorkingDay($date, $attendantName, $attendantUserId)) {
            return 'Dia sem expediente para este atendente.';
        }

        $identity = $this->scheduling->resolveAttendant($attendantName, $attendantUserId);

        $reasons = $blockedDates
            ->filter(fn (BlockedDate $blockedDate): bool => $blockedDate->blocked_date->isSameDay($date))
            ->filter(fn (BlockedDate $blockedDate): bool => $this->blockedDateAppliesToIdentity($blockedDate, $identity))
            ->pluck('reason')
            ->filter(fn (?string $reason): bool => trim((string) $reason) !== '')
            ->unique()
            ->values();

        if ($reasons->isEmpty()) {
            return null;
        }

        return $reasons->implode(' · ');
    }

    /**
     * @param array{user_id: int|null, name: string, aliases: array<int, string>} $identity
     */
    private function blockedDateAppliesToIdentity(BlockedDate $blockedDate, array $identity): bool
    {
        if ($blockedDate->attendant_user_id === null && ($blockedDate->attendant_name === null || $blockedDate->attendant_name === '')) {
            return true;
        }

        if ($identity['user_id'] !== null && $blockedDate->attendant_user_id === $identity['user_id']) {
            return true;
        }

        $blockedName = trim((string) ($blockedDate->attendant_name ?? ''));
        if ($blockedName === '') {
            return false;
        }

        if ($identity['name'] !== '' && $blockedName === $identity['name']) {
            return true;
        }

        return in_array($blockedName, $identity['aliases'], true);
    }
}
