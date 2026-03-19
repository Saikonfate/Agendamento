@php
    $title = 'Gerenciar horários | Admin';
    $role = 'admin';
    $displayName = auth()->user()?->name ?? 'Administrador';
    $blockedDates = collect($blockedDates ?? []);
    $attendants = collect($attendants ?? []);
    $selectedAttendantKey = $selectedAttendantKey ?? ((array) $attendants->first())['key'] ?? '';
    $selectedAttendantName = $selectedAttendantName ?? ((array) $attendants->first())['name'] ?? '';
    $schedule = $schedule ?? [
        'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
        'start_time' => '08:00',
        'end_time' => '17:00',
        'break_start' => '12:00',
        'break_end' => '13:00',
        'slot_duration_minutes' => 30,
    ];
    $daySettings = $schedule['day_settings'] ?? [];
    $selectedDate = $selectedDate ?? now(config('app.timezone'));
    $selectedDateLabel = $selectedDateLabel ?? $selectedDate->locale('pt_BR')->translatedFormat('D, d/m');
    $selectedDateReason = $selectedDateReason ?? null;
    $calendarMonthLabel = $calendarMonthLabel ?? $selectedDate->format('m/y');
    $occupationCalendarDays = collect($occupationCalendarDays ?? []);
    $selectedDateSlots = collect($selectedDateSlots ?? []);
@endphp

<x-layouts.academic :title="$title" :role="$role" active="horarios" :userName="$displayName" userInitials="AD">
    <section class="space-y-5">
        <div>
            <h1 class="text-4xl font-semibold">Gerenciar horários</h1>
            <p class="mt-1 text-zinc-400">Configure disponibilidade, bloqueios e duração dos atendimentos</p>
        </div>

        <div class="grid gap-4 xl:grid-cols-[1fr_1.25fr]">
            <div class="space-y-4">
                <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                    <h2 class="text-3xl font-semibold">Configurações por atendente</h2>
                    <form method="GET" action="{{ route('academic.admin.schedule') }}" class="mt-4">
                        <div>
                            <label class="mb-2 block text-zinc-400">Atendente / Professor</label>
                            <select name="attendant" onchange="this.form.submit()" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2">
                                @foreach ($attendants as $attendant)
                                    <option value="{{ $attendant['key'] }}" @selected($selectedAttendantKey === $attendant['key'])>{{ $attendant['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('academic.admin.schedule.settings.store') }}" class="mt-4 space-y-4">
                        @csrf
                        <input type="hidden" name="attendant_key" value="{{ $selectedAttendantKey }}">

                        <div>
                            <label class="mb-2 block text-zinc-400">Disponibilidade por dia</label>
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[760px] text-left text-sm">
                                    <thead class="text-zinc-500">
                                        <tr>
                                            <th class="px-2 py-2">Dia</th>
                                            <th class="px-2 py-2">Ativo</th>
                                            <th class="px-2 py-2">Início</th>
                                            <th class="px-2 py-2">Fim</th>
                                            <th class="px-2 py-2">Intervalo início</th>
                                            <th class="px-2 py-2">Intervalo fim</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-800">
                                        @foreach (['mon' => 'Seg', 'tue' => 'Ter', 'wed' => 'Qua', 'thu' => 'Qui', 'fri' => 'Sex'] as $key => $label)
                                            @php
                                                $config = old("day_settings.$key", $daySettings[$key] ?? []);
                                                $enabled = (bool) ($config['enabled'] ?? false);
                                            @endphp
                                            <tr>
                                                <td class="px-2 py-2 font-semibold text-zinc-200">{{ $label }}</td>
                                                <td class="px-2 py-2">
                                                    <input type="checkbox" name="day_settings[{{ $key }}][enabled]" value="1" class="h-4 w-4 rounded border-zinc-600 bg-zinc-950 text-violet-500" @checked($enabled)>
                                                </td>
                                                <td class="px-2 py-2">
                                                    <input type="time" name="day_settings[{{ $key }}][start_time]" value="{{ $config['start_time'] ?? '08:00' }}" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-2 py-1.5" />
                                                </td>
                                                <td class="px-2 py-2">
                                                    <input type="time" name="day_settings[{{ $key }}][end_time]" value="{{ $config['end_time'] ?? '17:00' }}" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-2 py-1.5" />
                                                </td>
                                                <td class="px-2 py-2">
                                                    <input type="time" name="day_settings[{{ $key }}][break_start]" value="{{ $config['break_start'] ?? '12:00' }}" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-2 py-1.5" />
                                                </td>
                                                <td class="px-2 py-2">
                                                    <input type="time" name="day_settings[{{ $key }}][break_end]" value="{{ $config['break_end'] ?? '13:00' }}" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-2 py-1.5" />
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="mt-2 text-xs text-zinc-500">Sábado e domingo permanecem bloqueados no calendário do sistema.</p>
                            @error('working_days')
                                <p class="mt-2 text-sm text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-zinc-400">Duração padrão do atendimento</label>
                            <select name="slot_duration_minutes" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2">
                                @foreach ([30, 45, 60] as $duration)
                                    <option value="{{ $duration }}" @selected((int) old('slot_duration_minutes', $schedule['slot_duration_minutes'] ?? 30) === $duration)>{{ $duration }} minutos</option>
                                @endforeach
                            </select>
                            @error('slot_duration_minutes')<p class="mt-2 text-sm text-rose-400">{{ $message }}</p>@enderror
                            <p class="mt-2 text-xs text-zinc-500">Slots gerados automaticamente com base na duração</p>
                        </div>

                        <div class="border-t border-zinc-800 pt-4">
                            <button type="submit" class="w-full rounded-xl border border-zinc-700 px-4 py-2.5 text-xl font-semibold hover:border-violet-400">Salvar configuração</button>
                        </div>
                    </form>
                </article>

                <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-3xl font-semibold">Datas bloqueadas</h3>
                        <button type="button" data-open-block-date class="rounded-xl border border-zinc-700 px-3 py-1.5 text-sm font-semibold hover:border-violet-400">+ Bloquear data</button>
                    </div>

                    @if (session('status'))
                        <div class="mb-3 rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-200">
                            {{ session('status') }}
                        </div>
                    @endif

                    <div class="space-y-3">
                        @forelse ($blockedDates as $blockedDate)
                            <div class="flex items-center justify-between rounded-xl border border-zinc-800 bg-zinc-950/50 px-3 py-2.5">
                                <div>
                                    <p class="font-semibold text-white">{{ $blockedDate->blocked_date->format('d/m/y') }}</p>
                                    <p class="text-sm text-zinc-400">{{ $blockedDate->reason }}{{ $blockedDate->attendant_display_name !== '' ? ' · '.$blockedDate->attendant_display_name : ' · Todos os atendentes' }}</p>
                                </div>
                                <form method="POST" action="{{ route('academic.admin.schedule.blocked-dates.destroy', $blockedDate) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-xl border border-zinc-700 px-3 py-1.5 text-sm font-semibold hover:border-violet-400">Remover</button>
                                </form>
                            </div>
                        @empty
                            <p class="rounded-xl border border-zinc-800 px-3 py-2 text-sm text-zinc-400">Nenhuma data bloqueada cadastrada.</p>
                        @endforelse
                    </div>
                </article>
            </div>

            <div class="space-y-4">
                <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-3xl font-semibold">Ocupação — {{ $calendarMonthLabel }}</h2>
                        <div class="flex gap-2">
                            <a href="{{ route('academic.admin.schedule', ['attendant' => $selectedAttendantKey, 'date' => $selectedDate->copy()->subMonthNoOverflow()->toDateString()]) }}" class="rounded-xl border border-zinc-700 px-3 py-1 text-sm">‹</a>
                            <a href="{{ route('academic.admin.schedule', ['attendant' => $selectedAttendantKey, 'date' => $selectedDate->copy()->addMonthNoOverflow()->toDateString()]) }}" class="rounded-xl border border-zinc-700 px-3 py-1 text-sm">›</a>
                        </div>
                    </div>

                    <div class="grid grid-cols-7 gap-2 text-center text-sm">
                        @foreach (['D', 'S', 'T', 'Q', 'Q', 'S', 'S'] as $day)
                            <span class="text-zinc-500">{{ $day }}</span>
                        @endforeach

                        @foreach ($occupationCalendarDays as $day)
                            @if (($day['status'] ?? 'empty') === 'empty')
                                <span class="rounded-lg py-2">&nbsp;</span>
                                @continue
                            @endif

                            @php
                                $class = match ($day['status']) {
                                    'available' => 'border-emerald-500/30 bg-emerald-500/15 text-emerald-400',
                                    'partial' => 'border-violet-500/30 bg-violet-500/15 text-violet-300',
                                    'full' => 'border-rose-500/30 bg-rose-500/15 text-rose-400',
                                    default => 'border-zinc-800 bg-zinc-800/40 text-zinc-500',
                                };
                            @endphp

                            <a href="{{ route('academic.admin.schedule', ['attendant' => $selectedAttendantKey, 'date' => $day['date']]) }}" title="{{ $day['reason'] ?? '' }}" class="block rounded-lg border py-2 {{ $class }} {{ ($day['isToday'] ?? false) ? 'ring-1 ring-zinc-400/50' : '' }} {{ ($day['isSelected'] ?? false) ? 'ring-2 ring-violet-300' : '' }}">{{ $day['day'] }}</a>
                        @endforeach
                    </div>

                    <div class="mt-4 flex flex-wrap gap-3 text-sm text-zinc-400">
                        <span class="inline-flex items-center gap-1"><span class="size-2.5 rounded bg-emerald-400"></span> Disponível</span>
                        <span class="inline-flex items-center gap-1"><span class="size-2.5 rounded bg-violet-300"></span> Disponibilidade parcial</span>
                        <span class="inline-flex items-center gap-1"><span class="size-2.5 rounded bg-rose-400"></span> Indisponível (lotado)</span>
                        <span class="inline-flex items-center gap-1"><span class="size-2.5 rounded bg-zinc-500"></span> Indisponível por regra</span>
                    </div>

                    @if ($selectedDateReason)
                        <p class="mt-3 rounded-lg border border-zinc-800 bg-zinc-950/60 px-3 py-2 text-xs text-zinc-300">
                            Motivo do bloqueio em {{ $selectedDate->format('d/m/y') }}: {{ $selectedDateReason }}
                        </p>
                    @endif
                </article>

                <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                    <h3 class="text-3xl font-semibold">Slots — {{ $selectedDateLabel }}</h3>
                    <div class="mt-4 divide-y divide-zinc-800">
                        @forelse ($selectedDateSlots as $slot)
                            <div class="flex items-center justify-between py-2.5">
                                <span class="font-semibold text-zinc-200">{{ $slot['time_range'] }}</span>
                                <span class="text-zinc-400">{{ $slot['name'] }}</span>
                                <span class="rounded-full px-3 py-1 text-sm font-medium {{ $slot['status_class'] }}">{{ $slot['status'] }}</span>
                            </div>
                        @empty
                            <p class="py-2 text-sm text-zinc-400">Nenhum slot configurado para esta data.</p>
                        @endforelse
                    </div>
                </article>
            </div>
        </div>
    </section>

    <div data-block-date-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 backdrop-blur-sm px-4 py-6">
        <div class="w-full max-w-xl rounded-2xl border border-zinc-700 bg-zinc-900 p-6 shadow-2xl">
            <div class="flex items-start justify-between">
                <h3 class="text-3xl font-semibold text-white">Bloquear data</h3>
                <button type="button" data-close-block-date-modal class="text-zinc-400 hover:text-zinc-200">✕</button>
            </div>

            <form method="POST" action="{{ route('academic.admin.schedule.blocked-dates.store') }}" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label class="mb-2 block text-zinc-400">Data</label>
                    <input name="blocked_date" type="date" value="{{ old('blocked_date') }}" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2" />
                </div>

                <div>
                    <label class="mb-2 block text-zinc-400">Motivo</label>
                    <input name="reason" type="text" value="{{ old('reason') }}" placeholder="Informe o motivo do bloqueio" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2" />
                </div>

                <div>
                    <label class="mb-2 block text-zinc-400">Aplicar para</label>
                    <select name="attendant_key" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2">
                        <option value="all">Todos os atendentes</option>
                        @foreach ($attendants as $attendant)
                            <option value="{{ $attendant['key'] }}">{{ $attendant['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex justify-end gap-2 border-t border-zinc-800 pt-4">
                    <button type="button" data-close-block-date-modal class="rounded-xl border border-zinc-700 px-4 py-2 font-semibold text-white">Cancelar</button>
                    <button type="submit" class="rounded-xl border border-zinc-700 px-4 py-2 font-semibold text-white hover:border-violet-400">Confirmar bloqueio</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.querySelector('[data-block-date-modal]');
            const openButton = document.querySelector('[data-open-block-date]');
            const closeButtons = document.querySelectorAll('[data-close-block-date-modal]');

            const openModal = () => {
                if (!modal) return;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            };

            const closeModal = () => {
                if (!modal) return;
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            };

            openButton?.addEventListener('click', openModal);

            closeButtons.forEach((button) => {
                button.addEventListener('click', closeModal);
            });

            modal?.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal();
                }
            });
        });
    </script>
</x-layouts.academic>
