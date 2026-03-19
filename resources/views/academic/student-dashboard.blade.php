@php
    $title = 'Início | Aluno';
    $role = 'student';
    $user = auth()->user();
    $displayName = $user?->name ?? 'Aluno';
    $firstName = str($displayName)->before(' ');
    $mustChangePassword = (bool) ($user?->must_change_password ?? false);
    $activeCount = $activeCount ?? 0;
    $completedCount = $completedCount ?? 0;
    $todayAvailableCount = $todayAvailableCount ?? 0;
    $attendants = collect($attendants ?? []);
    $selectedAttendantKey = $selectedAttendantKey ?? '';
    $selectedAttendantName = $selectedAttendantName ?? '';
    $calendarMonthLabel = $calendarMonthLabel ?? now()->locale('pt_BR')->translatedFormat('M/Y');
    $calendarDays = collect($calendarDays ?? []);
    $upcomingAppointments = collect($upcomingAppointments ?? []);
    $notices = collect($notices ?? []);
    $availableSlotsToday = collect($availableSlotsToday ?? []);
    $todayLabel = $todayLabel ?? now()->format('d/m/Y');
    $slotsReferenceDateLabel = $slotsReferenceDateLabel ?? $todayLabel;
    $isSlotsReferenceToday = (bool) ($isSlotsReferenceToday ?? true);
    $newAppointmentParams = ['date' => now()->toDateString()];

    if ($selectedAttendantKey !== '') {
        $newAppointmentParams['attendant'] = $selectedAttendantKey;
    }
@endphp

<x-layouts.academic :title="$title" :role="$role" active="inicio" :userName="$displayName">
    @if ($mustChangePassword)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4">
            <div class="w-full max-w-xl rounded-2xl border border-violet-500/30 bg-zinc-900 p-6 shadow-2xl">
                <h2 class="text-3xl font-semibold text-white">Altere sua senha no primeiro acesso</h2>
                <p class="mt-3 text-zinc-300">
                    Para continuar usando o sistema com segurança, troque agora a senha padrão da sua conta.
                </p>
                <div class="mt-6 flex justify-end">
                    <a href="{{ route('academic.student.profile') }}" class="rounded-xl border border-violet-400 px-5 py-2.5 text-xl font-semibold text-white hover:bg-violet-500/20">
                        Alterar senha agora
                    </a>
                </div>
            </div>
        </div>
    @endif

    <section class="space-y-5">
        <div>
            <h1 class="text-4xl font-semibold">Olá, {{ $firstName }} 👋</h1>
            <p class="mt-1 text-zinc-400">Você está logado como aluno</p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            @foreach ([[$activeCount, 'Agendamentos ativos'], [$completedCount, 'Atendimentos realizados'], [$todayAvailableCount, 'Horários na próxima data']] as [$value, $label])
                <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                    <p class="text-4xl font-bold">{{ $value }}</p>
                    <p class="text-zinc-400">{{ $label }}</p>
                </article>
            @endforeach
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <article class="space-y-4 rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5 lg:col-span-2">
                <h2 class="text-3xl font-semibold">Próximos agendamentos</h2>
                <div class="space-y-5 border-t border-zinc-800 pt-4">
                    @forelse ($upcomingAppointments as $appointment)
                        <div class="border-l border-zinc-700 pl-4">
                            <p class="text-zinc-400">{{ $appointment->scheduled_at->locale('pt_BR')->translatedFormat('D, d M') }} · {{ $appointment->scheduled_at->format('H:i') }}</p>
                            <p class="text-2xl font-semibold">{{ $appointment->subject }}</p>
                            <p class="text-zinc-400">{{ $appointment->attendant_display_name }}</p>
                            <span class="mt-2 inline-flex rounded-full px-3 py-1 text-sm {{ $appointment->status === 'Confirmado' ? 'bg-blue-500/20 text-blue-300' : ($appointment->status === 'Pendente' ? 'bg-amber-500/20 text-amber-300' : ($appointment->status === 'Cancelado' ? 'bg-rose-500/20 text-rose-300' : 'bg-emerald-500/20 text-emerald-300')) }}">{{ $appointment->status }}</span>
                        </div>
                    @empty
                        <p class="text-zinc-400">Nenhum agendamento futuro.</p>
                    @endforelse
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('academic.student.new', $newAppointmentParams) }}" class="rounded-xl border border-zinc-700 px-5 py-2 text-3xl font-semibold hover:border-violet-400">+ Novo agendamento</a>
                    <a href="{{ route('academic.student.mine') }}" class="rounded-xl border border-zinc-700 px-5 py-2 text-3xl font-semibold hover:border-violet-400">Ver todos</a>
                </div>
            </article>

            <div class="space-y-4">
                <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                    <h3 class="text-3xl font-semibold">Calendário · {{ $calendarMonthLabel }}</h3>
                    <div class="mt-3 flex flex-wrap gap-3 text-xs text-zinc-400">
                        <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full bg-emerald-400"></span>Disponível</span>
                        <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full bg-rose-400"></span>Lotado</span>
                        <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full bg-zinc-600"></span>Indisponível (ocupado ou regra)</span>
                        <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full border border-violet-400 bg-violet-500/40"></span>Hoje</span>
                    </div>
                    <div data-student-calendar-grid class="mt-4 grid grid-cols-7 gap-2 text-center text-sm">
                        @foreach (['D','S','T','Q','Q','S','S'] as $d)
                            <span class="text-zinc-500">{{ $d }}</span>
                        @endforeach
                        @foreach ($calendarDays as $day)
                            @if ($day['status'] === 'empty')
                                <span class="rounded-lg py-1">&nbsp;</span>
                                @continue
                            @endif

                            @php
                                $statusClass = match ($day['status']) {
                                    'available' => 'text-emerald-400',
                                    'full' => 'text-rose-400',
                                    'unavailable' => 'text-zinc-600',
                                    default => 'text-zinc-400',
                                };

                                $todayClass = '';

                                if ($day['isToday']) {
                                    $statusClass = 'text-violet-200';
                                    $todayClass = 'border border-violet-400 ring-1 ring-violet-400 bg-violet-500/25 font-semibold';
                                }
                            @endphp

                            <button
                                type="button"
                                data-calendar-day
                                data-day-label="{{ sprintf('%02d', (int) $day['day']) }}/{{ now()->format('m') }}"
                                data-day-status="{{ $day['status'] }}"
                                data-day-reason="{{ $day['unavailability_reason'] ?? '' }}"
                                title="{{ $day['unavailability_reason'] ?? '' }}"
                                class="rounded-lg py-1 transition {{ $statusClass }} {{ $todayClass }}"
                            >{{ $day['day'] }}</button>
                        @endforeach
                    </div>
                    <p data-calendar-day-reason class="mt-2 min-h-10 rounded-lg border border-violet-300/70 bg-violet-500/30 px-3 py-2 text-sm font-semibold text-white">
                        Clique em um dia para visualizar o motivo de indisponibilidade.
                    </p>
                    @if ($selectedAttendantName === '')
                        <p class="mt-3 text-xs text-zinc-500">Legenda: Roxo = hoje · Verde = disponível · Vermelho = lotado · Cinza = indisponível (ocupado ou bloqueado por regra). Passe o cursor sobre um dia indisponível para ver o motivo.</p>
                    @else
                        <p class="mt-3 text-xs text-zinc-500">{{ $selectedAttendantName }} · Roxo: hoje · Verde: disponível · Vermelho: lotado · Cinza: indisponível (ocupado ou bloqueado por regra). Passe o cursor sobre um dia indisponível para ver o motivo.</p>
                    @endif
                </article>

                <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                    <h3 class="text-3xl font-semibold">Avisos</h3>
                    <ul class="mt-4 space-y-3 text-zinc-300">
                        @forelse ($notices as $notice)
                            <li>{{ $notice->tone === 'amber' ? '📌' : '📄' }} {{ $notice->message }}</li>
                        @empty
                            <li>Nenhum aviso no momento.</li>
                        @endforelse
                    </ul>
                </article>
            </div>
        </div>

        <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5 lg:max-w-4xl">
            <h3 class="text-3xl font-semibold">
                {{ $isSlotsReferenceToday ? 'Horários disponíveis — '.$slotsReferenceDateLabel : 'Próxima data disponível — '.$slotsReferenceDateLabel }}
            </h3>

            <form method="GET" action="{{ route('academic.student.dashboard') }}" class="mt-4 max-w-md">
                <label for="attendant" class="mb-2 block text-sm text-zinc-400">Selecione o atendente</label>
                <select id="attendant" name="attendant" onchange="this.form.submit()" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2.5">
                    <option value="">Selecione um atendente...</option>
                    @foreach ($attendants as $attendant)
                        <option value="{{ $attendant['key'] }}" @selected($selectedAttendantKey === $attendant['key'])>{{ $attendant['name'] }}</option>
                    @endforeach
                </select>
            </form>

            @if ($selectedAttendantName === '')
                <p class="mt-4 text-zinc-400">Escolha um atendente para visualizar os horários da próxima data disponível.</p>
            @else
                <p class="mt-2 text-sm italic text-zinc-500">Exibindo agenda de {{ $selectedAttendantName }} · Verde = disponível · Cinza = indisponível (ocupado ou bloqueado por regra)</p>
                @if (! $isSlotsReferenceToday)
                    <p class="mt-2 text-xs text-zinc-500">Hoje não há mais horários disponíveis. Exibindo a próxima data com vaga.</p>
                @endif
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @forelse ($availableSlotsToday as $slot)
                        <button class="rounded-lg border px-4 py-2 text-lg {{ $slot['available'] ? 'border-emerald-700 bg-emerald-700/35 text-emerald-300' : 'border-zinc-800 bg-zinc-800 text-zinc-500 line-through' }}">{{ $slot['time'] }}</button>
                    @empty
                        <p class="col-span-full text-zinc-400">Nenhum horário disponível para este atendente.</p>
                    @endforelse
                </div>
            @endif
        </article>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const dayButtons = document.querySelectorAll('[data-calendar-day]');
            const reasonTarget = document.querySelector('[data-calendar-day-reason]');

            if (!reasonTarget || !dayButtons.length) {
                return;
            }

            dayButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    dayButtons.forEach((otherButton) => {
                        otherButton.classList.remove('ring-2', 'ring-violet-400', 'border', 'border-violet-400', 'bg-violet-500/20');
                    });

                    button.classList.add('ring-2', 'ring-violet-400', 'border', 'border-violet-400', 'bg-violet-500/20');

                    const status = button.dataset.dayStatus || '';
                    const reason = (button.dataset.dayReason || '').trim();
                    const dayLabel = button.dataset.dayLabel || '';

                    if (status === 'available') {
                        reasonTarget.textContent = `${dayLabel}: dia com disponibilidade.`;
                        return;
                    }

                    if (status === 'full') {
                        reasonTarget.textContent = `${dayLabel}: ${reason !== '' ? reason : 'dia lotado, sem horários disponíveis.'}`;
                        return;
                    }

                    reasonTarget.textContent = `${dayLabel}: ${reason !== '' ? reason : 'indisponível por regra do calendário.'}`;
                });
            });
        });
    </script>
</x-layouts.academic>
