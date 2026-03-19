@php
    $title = 'Novo Agendamento | Aluno';
    $role = 'student';
    $displayName = auth()->user()?->name ?? 'Aluno';
    $attendants = collect($attendants ?? []);
    $selectedDate = $selectedDate ?? now()->addDay();
    $selectedAttendantKey = $selectedAttendantKey ?? '';
    $selectedAttendantName = $selectedAttendantName ?? '';
    $rescheduleAppointmentId = $rescheduleAppointmentId ?? null;
    $subject = $subject ?? '';
    $slots = collect($slots ?? []);
    $slotsByDate = $slotsByDate ?? [];
    $slotsByDateByAttendant = $slotsByDateByAttendant ?? [];
    $calendarDays = collect($calendarDays ?? []);
    $calendarDaysByAttendant = $calendarDaysByAttendant ?? [];
    $calendarMonthLabel = $calendarMonthLabel ?? $selectedDate->locale('pt_BR')->translatedFormat('F/Y');
    $selectedTime = old('time', $selectedTime ?? '');
    $hasValidSelectedTime = preg_match('/^\d{2}:\d{2}$/', (string) $selectedTime) === 1;
    $selectedTimeLabel = $hasValidSelectedTime
        ? $selectedTime.' - '.\Illuminate\Support\Carbon::createFromFormat('H:i', $selectedTime)->addMinutes(30)->format('H:i')
        : 'Selecione um horário';
    $selectedDateValue = old('date', $selectedDate->format('Y-m-d'));
    $selectedDateLabel = \Illuminate\Support\Carbon::parse($selectedDateValue)->translatedFormat('d/m/Y');
    $selectedDateLongLabel = \Illuminate\Support\Carbon::parse($selectedDateValue)->translatedFormat('l, d \d\e F \d\e Y');
@endphp

<x-layouts.academic :title="$title" :role="$role" active="agendar" :userName="$displayName">
    <section class="space-y-5">
        <div>
            <h1 class="text-4xl font-semibold">Novo agendamento</h1>
            <p class="mt-1 text-zinc-400">Preencha os dados abaixo para solicitar um atendimento</p>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-rose-200">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="grid grid-cols-3 items-center gap-4 text-sm text-zinc-100">
            <div class="flex items-center gap-2"><span class="flex size-7 items-center justify-center rounded-full border border-violet-300 bg-violet-500/35 font-semibold text-white">1</span> Tipo</div>
            <div class="flex items-center gap-2"><span class="flex size-7 items-center justify-center rounded-full border border-violet-300 bg-violet-500/35 font-semibold text-white">2</span> Data / Horário</div>
            <div class="flex items-center gap-2"><span class="flex size-7 items-center justify-center rounded-full border border-violet-300 bg-violet-500/35 font-semibold text-white">3</span> Confirmação</div>
        </div>

        <form method="POST" action="{{ route('academic.student.store') }}" class="grid gap-4 lg:grid-cols-2" data-student-appointment-form>
            @csrf
            @if ($rescheduleAppointmentId)
                <input type="hidden" name="appointment_id" value="{{ $rescheduleAppointmentId }}" />
            @endif
            <div class="space-y-4">
                <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                    <h2 class="text-3xl font-semibold">1 · Tipo de atendimento</h2>
                    <div class="mt-4">
                        <label class="mb-2 block text-xl text-zinc-400">Atendente</label>
                        <select data-attendant-select name="attendant_key" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2.5">
                            @foreach ($attendants as $attendant)
                                <option value="{{ $attendant['key'] }}" @selected(old('attendant_key', $selectedAttendantKey) === $attendant['key'])>{{ $attendant['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mt-4">
                        <label class="mb-2 block text-xl text-zinc-400">Motivo / Assunto</label>
                        <textarea name="subject" rows="3" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2.5" placeholder="Requerimento de histórico escolar...">{{ old('subject', $subject) }}</textarea>
                    </div>
                </article>
            </div>

            <div class="space-y-4">
                <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                    <h2 class="text-3xl font-semibold">2 · Escolha a data</h2>
                    <div class="mt-4">
                        <label class="mb-2 block text-xl text-zinc-400">Data</label>
                        <div class="flex gap-2">
                            <input data-date-display type="text" value="{{ $selectedDateLabel }}" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2.5" readonly />
                            <input data-date-input name="date" type="hidden" value="{{ $selectedDateValue }}" />
                            <button type="button" data-open-calendar class="rounded-lg border border-zinc-700 px-3 py-2.5 text-sm font-semibold hover:border-violet-400">Abrir calendário</button>
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                    <h3 class="text-3xl font-semibold">Horários disponíveis — <span data-slots-date-label>{{ $selectedDate->locale('pt_BR')->translatedFormat('D d/m') }}</span></h3>
                    <input data-time-input type="hidden" name="time" value="{{ $selectedTime }}" />
                    <div data-slots-grid class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach ($slots as $slot)
                            <button type="button" data-slot-button data-slot-time="{{ $slot['time'] }}" class="rounded-lg border px-3 py-2 {{ $slot['available'] ? 'border-emerald-700 bg-emerald-700/35 text-emerald-300' : 'border-zinc-800 bg-zinc-800 text-zinc-500 line-through cursor-not-allowed' }} {{ $selectedTime === $slot['time'] ? 'ring-2 ring-violet-400 border-violet-400 bg-violet-500/25 text-violet-200' : '' }}" {{ $slot['available'] ? '' : 'disabled' }}>
                                {{ $slot['time'] }}
                            </button>
                        @endforeach
                    </div>
                    <p data-selected-time-text class="mt-2 text-sm italic text-zinc-500">
                        {{ $selectedTime !== '' ? $selectedTime.' selecionado' : 'Selecione um horário disponível para confirmar.' }}
                    </p>
                    <p class="mt-2 text-xs text-zinc-500">Legenda: Verde = disponível · Cinza = indisponível (ocupado ou bloqueado por regra).</p>
                </article>

                <article class="rounded-2xl border border-violet-400/60 bg-violet-500/15 p-5">
                    <h3 class="text-2xl font-semibold text-white">3 · Confirmação</h3>
                    <div class="mt-4 space-y-2 text-xl text-zinc-200">
                        <p><span class="font-semibold">Atendente:</span> <span data-confirm-attendant>{{ $selectedAttendantName !== '' ? $selectedAttendantName : 'Selecione um atendente' }}</span></p>
                        <p><span class="font-semibold">Data:</span> <span data-confirm-date>{{ $selectedDateLongLabel }}</span></p>
                        <p><span class="font-semibold">Horário:</span> <span data-confirm-time>{{ $selectedTimeLabel }}</span></p>
                        <p><span class="font-semibold">Motivo:</span> {{ old('subject', $subject ?: 'Informe o motivo') }}</p>
                    </div>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <button type="submit" data-submit-appointment class="rounded-xl border border-violet-300/60 bg-violet-500/20 px-4 py-2 text-3xl font-semibold text-violet-100 hover:border-violet-200">Confirmar agendamento</button>
                        <a href="{{ route('academic.student.mine') }}" class="rounded-xl border border-zinc-700 px-4 py-2 text-3xl font-semibold">Cancelar</a>
                    </div>
                    <p class="mt-3 border-l border-zinc-700 pl-3 text-sm italic text-zinc-400">Sistema verifica conflito antes de confirmar. Se horário já foi tomado, exibe alerta e recarrega grade.</p>
                </article>
            </div>
        </form>

        <div data-system-calendar-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 backdrop-blur-sm px-4 py-6">
            <div class="w-full max-w-xl rounded-2xl border border-zinc-700 bg-zinc-900 p-6 shadow-2xl">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-3xl font-semibold text-white">Calendário do sistema</h3>
                        <p class="mt-1 text-zinc-400">{{ \Illuminate\Support\Str::ucfirst($calendarMonthLabel) }}</p>
                    </div>
                    <button type="button" data-close-calendar class="text-zinc-400 hover:text-white">✕</button>
                </div>

                <div class="mt-4 grid grid-cols-7 gap-2 text-center text-sm text-zinc-500">
                    @foreach (['D', 'S', 'T', 'Q', 'Q', 'S', 'S'] as $day)
                        <span>{{ $day }}</span>
                    @endforeach
                </div>

                <div data-calendar-days class="mt-3 grid grid-cols-7 gap-2"></div>

                <p class="mt-4 text-sm text-zinc-400">Somente datas com horários disponíveis para agendamento podem ser selecionadas.</p>
                <p class="mt-1 text-xs text-zinc-500">Passe o cursor sobre dias indisponíveis para ver o motivo do bloqueio.</p>
                <p data-calendar-day-reason class="mt-2 min-h-10 rounded-lg border border-violet-300/70 bg-violet-500/30 px-3 py-2 text-sm font-semibold text-white">
                    Clique em um dia para visualizar o motivo de indisponibilidade.
                </p>

                <div class="mt-5 flex justify-end border-t border-zinc-800 pt-4">
                    <button type="button" data-close-calendar class="rounded-xl border border-zinc-700 px-5 py-2 text-xl font-semibold text-white hover:border-violet-400">Fechar</button>
                </div>
            </div>
        </div>
    </section>

    <script type="application/json" id="slots-by-date-data">@json($slotsByDate)</script>
    <script type="application/json" id="slots-by-date-attendant-data">@json($slotsByDateByAttendant)</script>
    <script type="application/json" id="calendar-days-attendant-data">@json($calendarDaysByAttendant)</script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-student-appointment-form]');
            const submitButton = document.querySelector('[data-submit-appointment]');
            const slotsGrid = document.querySelector('[data-slots-grid]');
            const timeInput = document.querySelector('[data-time-input]');
            const selectedTimeText = document.querySelector('[data-selected-time-text]');
            const confirmTimeText = document.querySelector('[data-confirm-time]');
            const confirmAttendantText = document.querySelector('[data-confirm-attendant]');
            const dateInput = document.querySelector('[data-date-input]');
            const dateDisplayInput = document.querySelector('[data-date-display]');
            const attendantSelect = document.querySelector('[data-attendant-select]');
            const openCalendarButton = document.querySelector('[data-open-calendar]');
            const slotsDateLabel = document.querySelector('[data-slots-date-label]');
            const confirmDateText = document.querySelector('[data-confirm-date]');
            const calendarModal = document.querySelector('[data-system-calendar-modal]');
            const closeCalendarButtons = document.querySelectorAll('[data-close-calendar]');
            const calendarDaysContainer = document.querySelector('[data-calendar-days]');
            const calendarDayReason = document.querySelector('[data-calendar-day-reason]');
            const slotsByDatePayload = document.getElementById('slots-by-date-data')?.textContent || '{}';
            const slotsByDateAttendantPayload = document.getElementById('slots-by-date-attendant-data')?.textContent || '{}';
            const calendarDaysAttendantPayload = document.getElementById('calendar-days-attendant-data')?.textContent || '{}';

            let slotsByDate = {};
            let slotsByDateByAttendant = {};
            let calendarDaysByAttendant = {};
            let calendarDays = [];

            try {
                slotsByDate = JSON.parse(slotsByDatePayload);
            } catch (error) {
                slotsByDate = {};
            }

            try {
                slotsByDateByAttendant = JSON.parse(slotsByDateAttendantPayload);
            } catch (error) {
                slotsByDateByAttendant = {};
            }

            try {
                calendarDaysByAttendant = JSON.parse(calendarDaysAttendantPayload);
            } catch (error) {
                calendarDaysByAttendant = {};
            }

            const fallbackAttendantId = Object.keys(slotsByDateByAttendant)[0] || '';

            const getSelectedAttendantId = () => {
                return attendantSelect?.value || fallbackAttendantId;
            };

            const getCurrentAttendantLabel = () => {
                if (!attendantSelect) return '';

                const option = attendantSelect.options[attendantSelect.selectedIndex];
                return option?.textContent?.trim() || '';
            };

            const toTimeRange = (time) => {
                const [hour, minute] = time.split(':').map(Number);
                const totalMinutes = (hour * 60) + minute + 30;
                const endHour = String(Math.floor(totalMinutes / 60)).padStart(2, '0');
                const endMinute = String(totalMinutes % 60).padStart(2, '0');
                return `${time} - ${endHour}:${endMinute}`;
            };

            const updateSlotVisualFeedback = () => {
                const selectedTime = timeInput?.value || '';

                slotsGrid?.querySelectorAll('[data-slot-button]').forEach((button) => {
                    const isSelected = button.dataset.slotTime === selectedTime;
                    button.classList.toggle('ring-2', isSelected);
                    button.classList.toggle('ring-violet-400', isSelected);
                    button.classList.toggle('border-violet-400', isSelected);
                    button.classList.toggle('bg-violet-500/25', isSelected);
                    button.classList.toggle('text-violet-200', isSelected);
                });

                if (!selectedTime) {
                    if (selectedTimeText) {
                        selectedTimeText.textContent = 'Selecione um horário disponível para confirmar.';
                    }

                    if (confirmTimeText) {
                        confirmTimeText.textContent = 'Selecione um horário';
                    }

                    return;
                }

                if (selectedTimeText) {
                    selectedTimeText.textContent = `${selectedTime} selecionado`;
                }

                if (confirmTimeText) {
                    confirmTimeText.textContent = toTimeRange(selectedTime);
                }
            };

            const renderSlotsForDate = (dateValue) => {
                if (!slotsGrid) return;

                const slots = slotsByDate[dateValue] || [];
                slotsGrid.innerHTML = '';

                slots.forEach((slot) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.dataset.slotButton = '';
                    button.dataset.slotTime = slot.time;
                    button.disabled = !slot.available;
                    button.className = `rounded-lg border px-3 py-2 ${slot.available ? 'border-emerald-700 bg-emerald-700/35 text-emerald-300' : 'border-zinc-800 bg-zinc-800 text-zinc-500 line-through cursor-not-allowed'}`;
                    button.textContent = slot.time;
                    slotsGrid.appendChild(button);
                });

                if (timeInput) {
                    const stillAvailable = slots.some((slot) => slot.available && slot.time === timeInput.value);
                    if (!stillAvailable) {
                        timeInput.value = '';
                    }
                }

                updateSlotVisualFeedback();
            };

            const renderCalendarDays = () => {
                if (!calendarDaysContainer) return;

                calendarDaysContainer.innerHTML = '';

                calendarDays.forEach((day) => {
                    if (day.status === 'empty' || !day.date || !day.day) {
                        const placeholder = document.createElement('span');
                        placeholder.className = 'rounded-lg border border-transparent px-2 py-2 text-sm';
                        placeholder.innerHTML = '&nbsp;';
                        calendarDaysContainer.appendChild(placeholder);
                        return;
                    }

                    const button = document.createElement('button');
                    const isSelectable = Boolean(day.hasAvailability && day.isSystemDay);

                    button.type = 'button';
                    button.dataset.calendarDay = '';
                    button.dataset.date = day.date;
                    button.dataset.selectable = isSelectable ? '1' : '0';
                    button.dataset.reason = day.unavailability_reason || '';
                    button.className = `rounded-lg border px-2 py-2 text-sm transition ${isSelectable ? 'border-zinc-700 text-zinc-200 hover:border-violet-400' : 'border-zinc-800 text-zinc-600 cursor-not-allowed'} ${day.isSelected ? 'ring-2 ring-violet-400 border-violet-400 bg-violet-500/20 text-violet-200' : ''} ${day.isToday ? 'font-semibold' : ''}`;
                    button.textContent = day.day;

                    if (!isSelectable && day.unavailability_reason) {
                        button.title = day.unavailability_reason;
                    }

                    calendarDaysContainer.appendChild(button);
                });
            };

            const syncDataForAttendant = () => {
                const attendantId = getSelectedAttendantId();

                slotsByDate = slotsByDateByAttendant[attendantId] || {};
                calendarDays = calendarDaysByAttendant[attendantId] || [];

                if (confirmAttendantText) {
                    confirmAttendantText.textContent = getCurrentAttendantLabel();
                }

                renderCalendarDays();

                const isCurrentDateAvailable = Boolean(slotsByDate[dateInput?.value || '']);

                if (!isCurrentDateAvailable && dateInput) {
                    const firstAvailableDate = calendarDays.find((day) => day.hasAvailability && day.isSystemDay)?.date || '';
                    dateInput.value = firstAvailableDate;
                }

                updateDateVisualFeedback();
            };

            const formatDateLabel = (dateValue) => {
                if (!dateValue) return '';

                const parsedDate = new Date(`${dateValue}T00:00:00`);
                if (Number.isNaN(parsedDate.getTime())) return '';

                const shortDate = new Intl.DateTimeFormat('pt-BR', {
                    weekday: 'short',
                    day: '2-digit',
                    month: '2-digit',
                }).format(parsedDate);

                const fullDate = new Intl.DateTimeFormat('pt-BR', {
                    weekday: 'long',
                    day: '2-digit',
                    month: 'long',
                    year: 'numeric',
                }).format(parsedDate);

                const displayDate = new Intl.DateTimeFormat('pt-BR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                }).format(parsedDate);

                return {
                    shortDate,
                    fullDate,
                    displayDate,
                };
            };

            const updateDateVisualFeedback = () => {
                if (!dateInput) return;

                const formatted = formatDateLabel(dateInput.value);
                if (!formatted) {
                    if (dateDisplayInput) {
                        dateDisplayInput.value = '';
                    }

                    if (slotsDateLabel) {
                        slotsDateLabel.textContent = 'Selecione uma data';
                    }

                    if (confirmDateText) {
                        confirmDateText.textContent = 'Selecione uma data';
                    }

                    renderSlotsForDate('');
                    return;
                }

                if (dateDisplayInput) {
                    dateDisplayInput.value = formatted.displayDate;
                }

                if (slotsDateLabel) {
                    slotsDateLabel.textContent = formatted.shortDate;
                }

                if (confirmDateText) {
                    confirmDateText.textContent = formatted.fullDate;
                }

                renderSlotsForDate(dateInput.value);
            };

            slotsGrid?.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) return;

                const slotButton = target.closest('[data-slot-button]');
                if (!(slotButton instanceof HTMLButtonElement) || slotButton.disabled) return;

                if (timeInput) {
                    timeInput.value = slotButton.dataset.slotTime || '';
                }

                updateSlotVisualFeedback();
            });

            calendarDaysContainer?.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement) || !dateInput) return;

                const button = target.closest('[data-calendar-day]');
                if (!(button instanceof HTMLButtonElement)) return;

                calendarDaysContainer.querySelectorAll('[data-calendar-day]').forEach((item) => {
                    item.classList.remove('ring-2', 'ring-violet-400', 'border-violet-400', 'bg-violet-500/20', 'text-violet-200');
                });

                button.classList.add('ring-2', 'ring-violet-400', 'border-violet-400', 'bg-violet-500/20', 'text-violet-200');

                const isSelectable = button.dataset.selectable === '1';
                const reason = (button.dataset.reason || '').trim();

                if (!isSelectable) {
                    if (calendarDayReason) {
                        calendarDayReason.textContent = reason !== ''
                            ? `Motivo do bloqueio: ${reason}`
                            : 'Indisponível por regra do calendário do sistema.';
                    }

                    return;
                }

                dateInput.value = button.dataset.date || dateInput.value;
                calendarDays = calendarDays.map((day) => ({
                    ...day,
                    isSelected: day.date === dateInput.value,
                }));

                if (calendarDayReason) {
                    calendarDayReason.textContent = 'Data selecionada. Escolha um horário disponível para continuar.';
                }

                renderCalendarDays();
                updateDateVisualFeedback();
                calendarModal?.classList.add('hidden');
                calendarModal?.classList.remove('flex');
            });

            openCalendarButton?.addEventListener('click', () => {
                calendarModal?.classList.remove('hidden');
                calendarModal?.classList.add('flex');
            });

            closeCalendarButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    calendarModal?.classList.add('hidden');
                    calendarModal?.classList.remove('flex');
                });
            });

            calendarModal?.addEventListener('click', (event) => {
                if (event.target === calendarModal) {
                    calendarModal.classList.add('hidden');
                    calendarModal.classList.remove('flex');
                }
            });

            attendantSelect?.addEventListener('change', () => {
                syncDataForAttendant();
            });

            updateSlotVisualFeedback();
            syncDataForAttendant();

            form?.addEventListener('submit', () => {
                if (!submitButton) return;

                submitButton.disabled = true;
                submitButton.classList.add('opacity-60', 'cursor-not-allowed');
                submitButton.textContent = 'Confirmando...';
            });
        });
    </script>
</x-layouts.academic>
