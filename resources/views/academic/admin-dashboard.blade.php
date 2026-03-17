@php
    $title = 'Agenda do dia | Admin';
    $role = 'admin';
    $displayName = auth()->user()?->name ?? 'Admin · Sec. Acadêmica';
    $initialNotices = ($notices ?? collect())
        ->map(fn ($notice) => [
            'id' => $notice->id,
            'message' => $notice->message,
            'tone' => $notice->tone,
        ])
        ->values();
    $appointmentsCollection = collect($appointments ?? []);
    $initialAppointments = $appointmentsCollection
        ->map(function ($appointment) {
            $statusClass = match ($appointment->status) {
                'Confirmado' => 'bg-emerald-500/20 text-emerald-300',
                'Pendente' => 'bg-amber-500/20 text-amber-300',
                'Cancelado' => 'bg-rose-500/20 text-rose-300',
                default => 'bg-violet-500/20 text-violet-300',
            };

            $timeRange = $appointment->scheduled_at->format('H:i').' - '.$appointment->scheduled_at->copy()->addMinutes(30)->format('H:i');

            return [
                'id' => $appointment->id,
                'time' => $appointment->scheduled_at->format('H:i'),
                'time_range' => $timeRange,
                'student' => $appointment->student_name,
                'registration' => $appointment->student_registration,
                'attendant' => $appointment->attendant_name,
                'subject' => $appointment->subject,
                'status' => $appointment->status,
                'cancellation_reason' => $appointment->cancellation_reason,
                'status_class' => $statusClass,
                'action' => in_array($appointment->status, ['Realizado', 'Cancelado'], true) ? 'Detalhes' : 'Atender',
            ];
        })
        ->values();

    $scheduledTotal = $scheduledCount ?? $appointmentsCollection->whereIn('status', ['Confirmado', 'Pendente', 'Realizado'])->count();
    $completedTotal = $completedCount ?? $appointmentsCollection->where('status', 'Realizado')->count();
    $pendingTotal = $pendingCount ?? $appointmentsCollection->where('status', 'Pendente')->count();
    $vacancyTotal = $vacancyCount ?? 4;
    $confirmedTotal = $appointmentsCollection->where('status', 'Confirmado')->count();
    $attendants = $appointmentsCollection
        ->pluck('attendant_name')
        ->filter()
        ->unique()
        ->sort()
        ->values();
@endphp

<x-layouts.academic :title="$title" :role="$role" active="agenda" :userName="$displayName" userInitials="AD">
    <section class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-4xl font-semibold">Agenda do dia</h1>
                <p class="mt-1 text-zinc-400">{{ $dateLabel ?? 'Segunda-feira, 16 de março de 2026' }}</p>
            </div>
        </div>

        <div class="grid gap-3 md:grid-cols-4">
            <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                <p class="text-zinc-400">Agendados hoje</p>
                <p data-count-agendados class="mt-2 text-4xl font-bold text-white">{{ $scheduledTotal }}</p>
            </article>
            <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                <p class="text-zinc-400">Realizados</p>
                <p data-count-realizados class="mt-2 text-4xl font-bold text-violet-300">{{ $completedTotal }}</p>
            </article>
            <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                <p class="text-zinc-400">Pendentes</p>
                <p data-count-pendentes class="mt-2 text-4xl font-bold text-amber-300">{{ $pendingTotal }}</p>
            </article>
            <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                <p class="text-zinc-400">Vagos hoje</p>
                <p class="mt-2 text-4xl font-bold text-emerald-300">{{ $vacancyTotal }}</p>
            </article>
        </div>

        <div class="grid gap-4 xl:grid-cols-[1.35fr_1fr]">
            <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-3xl font-semibold">Agendamentos — Hoje</h2>
                    <select data-filter-attendant class="rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-200">
                        <option value="all">Todos os atendentes</option>
                        @foreach ($attendants as $attendant)
                            <option value="{{ strtolower($attendant) }}">{{ $attendant }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-4 flex flex-wrap gap-2">
                    <button type="button" data-filter-button data-filter-status="all" class="rounded-xl border border-violet-500 bg-violet-500/20 px-3 py-1.5 text-sm font-semibold text-violet-200">
                        Todos (<span data-filter-count="all">{{ $scheduledTotal }}</span>)
                    </button>
                    <button type="button" data-filter-button data-filter-status="confirmado" class="rounded-xl border border-zinc-700 px-3 py-1.5 text-sm font-semibold text-zinc-300">
                        Confirmados (<span data-filter-count="confirmado">{{ $confirmedTotal }}</span>)
                    </button>
                    <button type="button" data-filter-button data-filter-status="pendente" class="rounded-xl border border-zinc-700 px-3 py-1.5 text-sm font-semibold text-zinc-300">
                        Pendentes (<span data-filter-count="pendente">{{ $pendingTotal }}</span>)
                    </button>
                    <button type="button" data-filter-button data-filter-status="realizado" class="rounded-xl border border-zinc-700 px-3 py-1.5 text-sm font-semibold text-zinc-300">
                        Realizados (<span data-filter-count="realizado">{{ $completedTotal }}</span>)
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left">
                        <thead class="text-sm text-zinc-400">
                            <tr>
                                <th class="px-3 py-2 font-medium">Horário</th>
                                <th class="px-3 py-2 font-medium">Aluno</th>
                                <th class="px-3 py-2 font-medium">Atendente</th>
                                <th class="px-3 py-2 font-medium">Assunto</th>
                                <th class="px-3 py-2 font-medium">Status</th>
                                <th class="px-3 py-2 font-medium">Ação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800">
                            @forelse ($initialAppointments as $appointment)
                                <tr data-appointment-row data-row-current-status="{{ strtolower($appointment['status']) }}" data-row-attendant="{{ strtolower($appointment['attendant']) }}">
                                    <td data-row-time class="px-3 py-3 text-xl font-semibold text-violet-200">{{ $appointment['time'] }}</td>
                                    <td class="px-3 py-3">
                                        <p class="font-semibold text-white">{{ $appointment['student'] }}</p>
                                        <p class="text-sm text-zinc-400">{{ $appointment['registration'] }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-zinc-300">{{ $appointment['attendant'] }}</td>
                                    <td class="px-3 py-3 text-zinc-300">{{ $appointment['subject'] }}</td>
                                    <td class="px-3 py-3">
                                        <span data-row-status class="rounded-full px-3 py-1 text-sm font-medium {{ $appointment['status_class'] }}">{{ $appointment['status'] }}</span>
                                        @if (($appointment['status'] ?? '') === 'Cancelado' && ! empty($appointment['cancellation_reason']))
                                            <p class="mt-2 max-w-md text-xs text-rose-300">Motivo: {{ $appointment['cancellation_reason'] }}</p>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3">
                                        @if ($appointment['action'] === 'Detalhes')
                                                <button
                                                    type="button"
                                                    data-open-details
                                                    data-row-action
                                                    data-appointment-id="{{ $appointment['id'] }}"
                                                    data-time="{{ $appointment['time_range'] }}"
                                                    data-student="{{ $appointment['student'] }}"
                                                    data-registration="{{ $appointment['registration'] }}"
                                                    data-attendant="{{ $appointment['attendant'] }}"
                                                    data-subject="{{ $appointment['subject'] }}"
                                                    data-status="{{ $appointment['status'] }}"
                                                    data-cancellation-reason="{{ $appointment['cancellation_reason'] ?? '' }}"
                                                    class="rounded-xl border border-zinc-700 px-3 py-1.5 text-sm font-semibold text-white hover:border-violet-400"
                                                >
                                                    Detalhes
                                                </button>
                                        @else
                                                <button
                                                    type="button"
                                                    data-open-attend
                                                    data-row-action
                                                    data-appointment-id="{{ $appointment['id'] }}"
                                                    data-time="{{ $appointment['time_range'] }}"
                                                    data-student="{{ $appointment['student'] }}"
                                                    data-registration="{{ $appointment['registration'] }}"
                                                    data-attendant="{{ $appointment['attendant'] }}"
                                                    data-subject="{{ $appointment['subject'] }}"
                                                    data-status="{{ $appointment['status'] }}"
                                                    data-cancellation-reason="{{ $appointment['cancellation_reason'] ?? '' }}"
                                                    class="rounded-xl border border-zinc-700 px-3 py-1.5 text-sm font-semibold text-white hover:border-violet-400"
                                                >
                                                    Atender
                                                </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-3 py-6 text-center text-zinc-400">Nenhum agendamento para hoje.</td>
                                </tr>
                            @endforelse
                            <tr data-filter-empty class="hidden">
                                <td colspan="6" class="px-3 py-6 text-center text-zinc-400">Nenhum agendamento encontrado para este filtro.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <div class="space-y-4">
                <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-3xl font-semibold">Navegação rápida</h3>
                        <div class="flex items-center gap-2">
                            <button class="rounded-xl border border-zinc-700 px-3 py-1 text-sm font-semibold">‹ Ontem</button>
                            <span class="px-2 text-lg font-semibold">16/03</span>
                            <button class="rounded-xl border border-zinc-700 px-3 py-1 text-sm font-semibold">Amanhã ›</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-7 gap-2 text-center text-sm">
                        @foreach (['D', 'S', 'T', 'Q', 'Q', 'S', 'S'] as $day)
                            <span class="text-zinc-500">{{ $day }}</span>
                        @endforeach

                        @foreach (range(1, 20) as $day)
                            @php
                                $class = match (true) {
                                    in_array($day, [1, 3, 8, 15, 17, 20], true) => 'border-emerald-500/30 bg-emerald-500/15 text-emerald-300',
                                    in_array($day, [2, 4, 9, 11, 12, 16, 18], true) => 'border-violet-500/30 bg-violet-500/15 text-violet-200',
                                    in_array($day, [5, 10, 13], true) => 'border-rose-500/30 bg-rose-500/15 text-rose-300',
                                    default => 'border-zinc-800 bg-zinc-800/40 text-zinc-500',
                                };
                            @endphp
                            <span class="rounded-lg border py-2 {{ $class }} {{ $day === 16 ? 'ring-2 ring-white/80' : '' }}">{{ $day }}</span>
                        @endforeach
                    </div>

                    <div class="mt-4 flex flex-wrap gap-3 text-sm text-zinc-400">
                        <span class="inline-flex items-center gap-1"><span class="size-2.5 rounded bg-emerald-400"></span> Com vaga</span>
                        <span class="inline-flex items-center gap-1"><span class="size-2.5 rounded bg-violet-300"></span> Parcial</span>
                        <span class="inline-flex items-center gap-1"><span class="size-2.5 rounded bg-rose-400"></span> Lotado</span>
                    </div>
                </article>

                <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-3xl font-semibold">Avisos</h3>
                        <button type="button" data-open-notices-manager class="rounded-xl border border-zinc-700 px-3 py-1.5 text-sm font-semibold text-white hover:border-violet-400">
                            Gerenciar avisos
                        </button>
                    </div>
                    <div data-notices-list class="mt-4 space-y-3 text-sm">
                        @foreach ($initialNotices as $notice)
                            <div data-notice-item data-notice-id="{{ $notice['id'] }}" data-tone="{{ $notice['tone'] }}" class="{{ $notice['tone'] === 'amber' ? 'rounded-lg border-l-2 border-amber-400 bg-amber-500/10 px-3 py-2 text-amber-300' : 'rounded-lg border-l-2 border-violet-400 bg-violet-500/10 px-3 py-2 text-violet-200' }}">
                                {{ $notice['message'] }}
                            </div>
                        @endforeach
                    </div>
                </article>
            </div>
        </div>
    </section>

    <div data-ui-feedback class="pointer-events-none fixed right-4 top-4 z-[60] hidden rounded-xl border px-4 py-3 text-sm shadow-lg"></div>

    <div data-details-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 backdrop-blur-sm px-4 py-6">
        <div class="w-full max-w-2xl rounded-2xl border border-zinc-700 bg-zinc-900 p-6 shadow-2xl">
            <div class="flex items-start justify-between">
                <h3 class="text-3xl font-semibold text-white">Detalhes do atendimento</h3>
                <button type="button" data-close-details-modal class="text-zinc-400 hover:text-white">✕</button>
            </div>

            <div class="mt-4">
                <span data-details-status class="inline-flex rounded-full bg-violet-500/20 px-3 py-1 text-sm font-medium text-violet-300">Realizado</span>
            </div>

            <div class="mt-4 divide-y divide-zinc-800 text-sm">
                <div class="grid grid-cols-[1fr_auto] py-3">
                    <span class="text-zinc-400">Aluno</span>
                    <span data-details-student class="font-semibold text-zinc-200">—</span>
                </div>
                <div class="grid grid-cols-[1fr_auto] py-3">
                    <span class="text-zinc-400">Matrícula</span>
                    <span data-details-registration class="font-semibold text-zinc-200">—</span>
                </div>
                <div class="grid grid-cols-[1fr_auto] py-3">
                    <span class="text-zinc-400">Horário</span>
                    <span data-details-time class="font-semibold text-violet-200">—</span>
                </div>
                <div class="grid grid-cols-[1fr_auto] py-3">
                    <span class="text-zinc-400">Atendente</span>
                    <span data-details-attendant class="font-semibold text-zinc-200">—</span>
                </div>
                <div class="grid grid-cols-[1fr_auto] py-3">
                    <span class="text-zinc-400">Assunto</span>
                    <span data-details-subject class="font-semibold text-zinc-200">—</span>
                </div>
                <div class="grid grid-cols-[1fr_auto] py-3">
                    <span class="text-zinc-400">Data</span>
                    <span class="font-semibold text-zinc-200">Seg, 16 de março de 2026</span>
                </div>
                <div class="grid grid-cols-[1fr_auto] py-3">
                    <span class="text-zinc-400">Motivo da rejeição</span>
                    <span data-details-cancellation-reason class="max-w-sm text-right font-semibold text-rose-300">—</span>
                </div>
            </div>

            <div class="mt-5 flex justify-end border-t border-zinc-800 pt-4">
                <button type="button" data-close-details-modal class="rounded-xl border border-zinc-700 px-5 py-2 text-xl font-semibold text-white hover:border-violet-400">Fechar</button>
            </div>
        </div>
    </div>

    <div data-attend-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 backdrop-blur-sm px-4 py-6">
        <div class="w-full max-w-2xl rounded-2xl border border-zinc-700 bg-zinc-900 p-6 shadow-2xl">
            <div class="flex items-start justify-between">
                <h3 class="text-3xl font-semibold text-white">Atender</h3>
                <button type="button" data-close-attend-modal class="text-zinc-400 hover:text-white">✕</button>
            </div>

            <div class="mt-4">
                <span data-attend-status class="inline-flex rounded-full bg-emerald-500/20 px-3 py-1 text-sm font-medium text-emerald-300">Confirmado</span>
            </div>

            <div class="mt-4 divide-y divide-zinc-800 text-sm">
                <div class="grid grid-cols-[1fr_auto] py-3">
                    <span class="text-zinc-400">Aluno</span>
                    <span data-attend-student class="font-semibold text-zinc-200">—</span>
                </div>
                <div class="grid grid-cols-[1fr_auto] py-3">
                    <span class="text-zinc-400">Matrícula</span>
                    <span data-attend-registration class="font-semibold text-zinc-200">—</span>
                </div>
                <div class="grid grid-cols-[1fr_auto] py-3">
                    <span class="text-zinc-400">Horário</span>
                    <span data-attend-time class="font-semibold text-violet-200">—</span>
                </div>
                <div class="grid grid-cols-[1fr_auto] py-3">
                    <span class="text-zinc-400">Atendente</span>
                    <span data-attend-attendant class="font-semibold text-zinc-200">—</span>
                </div>
                <div class="grid grid-cols-[1fr_auto] py-3">
                    <span class="text-zinc-400">Assunto</span>
                    <span data-attend-subject class="font-semibold text-zinc-200">—</span>
                </div>
                <div class="grid grid-cols-[1fr_auto] py-3">
                    <span class="text-zinc-400">Data</span>
                    <span class="font-semibold text-zinc-200">Seg, 16 de março de 2026</span>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap justify-end gap-2 border-t border-zinc-800 pt-4">
                <button type="button" data-cancel-appointment class="rounded-xl border border-zinc-700 px-4 py-2 text-xl font-semibold text-white hover:border-violet-400">Cancelar agendamento</button>
                <button type="button" data-open-reschedule class="rounded-xl border border-zinc-700 px-4 py-2 text-xl font-semibold text-white hover:border-violet-400">Reagendar</button>
                <button type="button" data-mark-done class="rounded-xl border border-zinc-700 px-4 py-2 text-xl font-semibold text-white hover:border-violet-400">Marcar como realizado</button>
            </div>
        </div>
    </div>

    <div data-reschedule-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 backdrop-blur-sm px-4 py-6">
        <div class="w-full max-w-2xl rounded-2xl border border-zinc-700 bg-zinc-900 p-6 shadow-2xl">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-3xl font-semibold text-white">Reagendar atendimento</h3>
                    <p data-reschedule-subtitle class="mt-1 text-zinc-400">—</p>
                </div>
                <button type="button" data-close-reschedule-modal class="text-zinc-400 hover:text-white">✕</button>
            </div>

            <div class="mt-5 space-y-4">
                <div>
                    <label for="reschedule-date" class="text-sm font-medium text-zinc-300">Nova data</label>
                    <input id="reschedule-date" data-reschedule-date type="date" class="mt-2 w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-zinc-200">
                </div>

                <div>
                    <p class="text-sm font-medium text-zinc-300">Horários disponíveis</p>
                    <div class="mt-2 grid grid-cols-4 gap-2">
                        @foreach (['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '13:00', '13:30', '14:00', '14:30'] as $slot)
                            <button type="button" data-reschedule-slot data-slot-value="{{ $slot }}" class="rounded-xl border border-zinc-700 px-3 py-2 text-sm font-semibold text-zinc-200 hover:border-violet-400">
                                {{ $slot }}
                            </button>
                        @endforeach
                    </div>
                    <p data-selected-slot class="mt-2 text-sm text-zinc-400">Nenhum horário selecionado</p>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap justify-end gap-2 border-t border-zinc-800 pt-4">
                <button type="button" data-close-reschedule-modal class="rounded-xl border border-zinc-700 px-5 py-2 text-xl font-semibold text-white hover:border-violet-400">Cancelar</button>
                <button type="button" data-confirm-reschedule class="rounded-xl border border-zinc-700 px-5 py-2 text-xl font-semibold text-white hover:border-violet-400">Confirmar reagendamento</button>
            </div>
        </div>
    </div>

    <div data-notices-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 backdrop-blur-sm px-4 py-6">
        <div class="w-full max-w-2xl rounded-2xl border border-zinc-700 bg-zinc-900 p-6 shadow-2xl">
            <div class="flex items-start justify-between">
                <h3 class="text-3xl font-semibold text-white">Gerenciar avisos</h3>
                <button type="button" data-close-notices-modal class="text-zinc-400 hover:text-white">✕</button>
            </div>

            <div class="mt-5 rounded-xl border border-zinc-800 bg-zinc-950/60 p-4">
                <p data-notice-form-title class="text-sm font-semibold text-zinc-300">Novo aviso</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-[180px_1fr]">
                    <select data-notice-tone-input class="rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-200">
                        <option value="amber">Alerta</option>
                        <option value="violet">Informativo</option>
                    </select>
                    <input data-notice-message-input type="text" placeholder="Digite o texto do aviso" class="rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-200">
                </div>
                <div class="mt-3 flex flex-wrap justify-end gap-2">
                    <button type="button" data-clear-notice-form class="rounded-xl border border-zinc-700 px-3 py-1.5 text-sm font-semibold text-white hover:border-violet-400">Limpar</button>
                    <button type="button" data-save-notice class="rounded-xl border border-zinc-700 px-3 py-1.5 text-sm font-semibold text-white hover:border-violet-400">Salvar aviso</button>
                </div>
            </div>

            <div class="mt-4">
                <p class="text-sm font-semibold text-zinc-300">Avisos cadastrados</p>
                <div data-notice-admin-list class="mt-2 max-h-64 space-y-2 overflow-y-auto pr-1"></div>
            </div>

            <div class="mt-5 flex justify-end border-t border-zinc-800 pt-4">
                <button type="button" data-close-notices-modal class="rounded-xl border border-zinc-700 px-5 py-2 text-xl font-semibold text-white hover:border-violet-400">Fechar</button>
            </div>
        </div>
    </div>

    <script type="application/json" id="initial-notices-data">@json($initialNotices)</script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const detailsModal = document.querySelector('[data-details-modal]');
            const attendModal = document.querySelector('[data-attend-modal]');
            const rescheduleModal = document.querySelector('[data-reschedule-modal]');
            const noticesModal = document.querySelector('[data-notices-modal]');
            const closeDetailsButtons = document.querySelectorAll('[data-close-details-modal]');
            const closeAttendButtons = document.querySelectorAll('[data-close-attend-modal]');
            const closeRescheduleButtons = document.querySelectorAll('[data-close-reschedule-modal]');
            const closeNoticesButtons = document.querySelectorAll('[data-close-notices-modal]');
            const cancelAppointmentButton = document.querySelector('[data-cancel-appointment]');
            const markDoneButton = document.querySelector('[data-mark-done]');
            const openRescheduleButton = document.querySelector('[data-open-reschedule]');
            const confirmRescheduleButton = document.querySelector('[data-confirm-reschedule]');
            const rescheduleDateInput = document.querySelector('[data-reschedule-date]');
            const rescheduleSlots = document.querySelectorAll('[data-reschedule-slot]');
            const selectedSlotText = document.querySelector('[data-selected-slot]');
            const rescheduleSubtitle = document.querySelector('[data-reschedule-subtitle]');
            const openNoticesManagerButton = document.querySelector('[data-open-notices-manager]');
            const noticesListEl = document.querySelector('[data-notices-list]');
            const noticeAdminListEl = document.querySelector('[data-notice-admin-list]');
            const noticeFormTitleEl = document.querySelector('[data-notice-form-title]');
            const noticeToneInput = document.querySelector('[data-notice-tone-input]');
            const noticeMessageInput = document.querySelector('[data-notice-message-input]');
            const saveNoticeButton = document.querySelector('[data-save-notice]');
            const clearNoticeFormButton = document.querySelector('[data-clear-notice-form]');
            const totalAppointmentsCountEl = document.querySelector('[data-count-agendados]');
            const completedCountEl = document.querySelector('[data-count-realizados]');
            const pendingCountEl = document.querySelector('[data-count-pendentes]');
            const noticesApiUrl = '{{ route("academic.admin.notices.index") }}';
            const appointmentStatusUrlTemplate = '{{ route("academic.admin.appointments.status", ["appointment" => "__ID__"]) }}';
            const appointmentRescheduleUrlTemplate = '{{ route("academic.admin.appointments.reschedule", ["appointment" => "__ID__"]) }}';
            const csrfToken = '{{ csrf_token() }}';
            const filterButtons = document.querySelectorAll('[data-filter-button]');
            const filterCountAllEl = document.querySelector('[data-filter-count="all"]');
            const filterCountConfirmadoEl = document.querySelector('[data-filter-count="confirmado"]');
            const filterCountPendenteEl = document.querySelector('[data-filter-count="pendente"]');
            const filterCountRealizadoEl = document.querySelector('[data-filter-count="realizado"]');
            const appointmentRows = document.querySelectorAll('[data-appointment-row]');
            const filterEmptyRow = document.querySelector('[data-filter-empty]');
            const attendantFilterSelect = document.querySelector('[data-filter-attendant]');
            const uiFeedbackEl = document.querySelector('[data-ui-feedback]');

            const statusEl = document.querySelector('[data-details-status]');
            const studentEl = document.querySelector('[data-details-student]');
            const registrationEl = document.querySelector('[data-details-registration]');
            const timeEl = document.querySelector('[data-details-time]');
            const attendantEl = document.querySelector('[data-details-attendant]');
            const subjectEl = document.querySelector('[data-details-subject]');
            const detailsCancellationReasonEl = document.querySelector('[data-details-cancellation-reason]');

            const attendStatusEl = document.querySelector('[data-attend-status]');
            const attendStudentEl = document.querySelector('[data-attend-student]');
            const attendRegistrationEl = document.querySelector('[data-attend-registration]');
            const attendTimeEl = document.querySelector('[data-attend-time]');
            const attendAttendantEl = document.querySelector('[data-attend-attendant]');
            const attendSubjectEl = document.querySelector('[data-attend-subject]');

            let currentAttendButton = null;
            let selectedRescheduleSlot = '';
            let noticeEditIndex = null;
            let activeAppointmentFilter = 'all';
            let activeAttendantFilter = 'all';
            let feedbackTimeout = null;
            const initialNoticesPayload = document.getElementById('initial-notices-data')?.textContent || '[]';
            let notices = [];

            try {
                notices = JSON.parse(initialNoticesPayload);
            } catch (error) {
                notices = [];
            }

            const getNoticeToneClasses = (tone) => {
                if (tone === 'amber') {
                    return 'rounded-lg border-l-2 border-amber-400 bg-amber-500/10 px-3 py-2 text-amber-300';
                }

                return 'rounded-lg border-l-2 border-violet-400 bg-violet-500/10 px-3 py-2 text-violet-200';
            };

            const getNoticeToneLabel = (tone) => {
                return tone === 'amber' ? 'Alerta' : 'Informativo';
            };

            const showUiFeedback = (message, type = 'success') => {
                if (!uiFeedbackEl) return;

                uiFeedbackEl.textContent = message;
                uiFeedbackEl.classList.remove(
                    'hidden',
                    'border-emerald-500/40', 'bg-emerald-500/10', 'text-emerald-200',
                    'border-rose-500/40', 'bg-rose-500/10', 'text-rose-200',
                    'border-amber-500/40', 'bg-amber-500/10', 'text-amber-200',
                );

                if (type === 'error') {
                    uiFeedbackEl.classList.add('border-rose-500/40', 'bg-rose-500/10', 'text-rose-200');
                } else if (type === 'warning') {
                    uiFeedbackEl.classList.add('border-amber-500/40', 'bg-amber-500/10', 'text-amber-200');
                } else {
                    uiFeedbackEl.classList.add('border-emerald-500/40', 'bg-emerald-500/10', 'text-emerald-200');
                }

                if (feedbackTimeout) {
                    window.clearTimeout(feedbackTimeout);
                }

                feedbackTimeout = window.setTimeout(() => {
                    uiFeedbackEl.classList.add('hidden');
                }, 2800);
            };

            const withLoadingButton = async (button, label, callback) => {
                if (!button) {
                    await callback();
                    return;
                }

                const originalLabel = button.textContent;
                button.disabled = true;
                button.classList.add('opacity-60', 'cursor-not-allowed');
                button.textContent = label;

                try {
                    await callback();
                } finally {
                    button.disabled = false;
                    button.classList.remove('opacity-60', 'cursor-not-allowed');
                    button.textContent = originalLabel;
                }
            };

            const resetNoticeForm = () => {
                noticeEditIndex = null;
                if (noticeFormTitleEl) noticeFormTitleEl.textContent = 'Novo aviso';
                if (noticeToneInput) noticeToneInput.value = 'amber';
                if (noticeMessageInput) noticeMessageInput.value = '';
            };

            const requestNotices = async (url, method, body = null) => {
                const response = await fetch(url, {
                    method,
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: body ? JSON.stringify(body) : undefined,
                });

                if (!response.ok) {
                    throw new Error('Não foi possível concluir a operação com avisos.');
                }

                if (response.status === 204) {
                    return null;
                }

                const payload = await response.json();
                return payload.data ?? null;
            };

            const requestAppointment = async (url, body) => {
                const response = await fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(body),
                });

                if (!response.ok) {
                    throw new Error('Não foi possível atualizar o agendamento.');
                }

                const payload = await response.json();
                return payload.data ?? null;
            };

            const loadNotices = async () => {
                try {
                    const data = await requestNotices(noticesApiUrl, 'GET');
                    if (Array.isArray(data)) {
                        notices = data;
                        renderNotices();
                        renderNoticeAdminList();
                    }
                } catch (error) {
                    showUiFeedback('Não foi possível carregar os avisos.', 'error');
                }
            };

            const renderNotices = () => {
                if (!noticesListEl) return;

                noticesListEl.innerHTML = '';

                notices.forEach((notice) => {
                    const noticeEl = document.createElement('div');
                    noticeEl.className = getNoticeToneClasses(notice.tone);
                    noticeEl.dataset.noticeItem = '';
                    noticeEl.dataset.noticeId = String(notice.id);
                    noticeEl.dataset.tone = notice.tone;
                    noticeEl.textContent = notice.message;
                    noticesListEl.appendChild(noticeEl);
                });

                if (!notices.length) {
                    const emptyEl = document.createElement('p');
                    emptyEl.className = 'rounded-lg border border-zinc-800 px-3 py-2 text-zinc-500';
                    emptyEl.textContent = 'Nenhum aviso cadastrado.';
                    noticesListEl.appendChild(emptyEl);
                }
            };

            const renderNoticeAdminList = () => {
                if (!noticeAdminListEl) return;

                noticeAdminListEl.innerHTML = '';

                notices.forEach((notice, index) => {
                    const row = document.createElement('div');
                    row.className = 'flex items-start justify-between gap-2 rounded-xl border border-zinc-800 bg-zinc-950/70 px-3 py-2';

                    const textWrap = document.createElement('div');
                    textWrap.className = 'min-w-0';

                    const toneEl = document.createElement('p');
                    toneEl.className = `text-xs font-semibold ${notice.tone === 'amber' ? 'text-amber-300' : 'text-violet-200'}`;
                    toneEl.textContent = getNoticeToneLabel(notice.tone);

                    const messageEl = document.createElement('p');
                    messageEl.className = 'truncate text-sm text-zinc-200';
                    messageEl.textContent = notice.message;

                    textWrap.appendChild(toneEl);
                    textWrap.appendChild(messageEl);

                    const actionsWrap = document.createElement('div');
                    actionsWrap.className = 'flex shrink-0 gap-2';

                    const editButton = document.createElement('button');
                    editButton.type = 'button';
                    editButton.dataset.editNotice = String(index);
                    editButton.className = 'rounded-lg border border-zinc-700 px-2 py-1 text-xs font-semibold text-white hover:border-violet-400';
                    editButton.textContent = 'Editar';

                    const removeButton = document.createElement('button');
                    removeButton.type = 'button';
                    removeButton.dataset.removeNotice = String(index);
                    removeButton.className = 'rounded-lg border border-zinc-700 px-2 py-1 text-xs font-semibold text-white hover:border-violet-400';
                    removeButton.textContent = 'Excluir';

                    actionsWrap.appendChild(editButton);
                    actionsWrap.appendChild(removeButton);

                    row.appendChild(textWrap);
                    row.appendChild(actionsWrap);
                    noticeAdminListEl.appendChild(row);
                });

                if (!notices.length) {
                    const emptyEl = document.createElement('p');
                    emptyEl.className = 'rounded-lg border border-zinc-800 px-3 py-2 text-sm text-zinc-500';
                    emptyEl.textContent = 'Nenhum aviso cadastrado.';
                    noticeAdminListEl.appendChild(emptyEl);
                }
            };

            const applyStatusBadge = (element, status) => {
                if (!element) return;

                element.textContent = status || '—';
                element.className = 'rounded-full px-3 py-1 text-sm font-medium';

                const normalizedStatus = (status || '').toLowerCase();

                if (normalizedStatus === 'confirmado') {
                    element.classList.add('bg-emerald-500/20', 'text-emerald-300');
                    return;
                }

                if (normalizedStatus === 'pendente') {
                    element.classList.add('bg-amber-500/20', 'text-amber-300');
                    return;
                }

                if (normalizedStatus === 'cancelado') {
                    element.classList.add('bg-rose-500/20', 'text-rose-300');
                    return;
                }

                element.classList.add('bg-violet-500/20', 'text-violet-300');
            };

            const isScheduledStatus = (status) => {
                const normalizedStatus = (status || '').toLowerCase();
                return normalizedStatus === 'confirmado' || normalizedStatus === 'pendente' || normalizedStatus === 'realizado';
            };

            const adjustCountElement = (element, delta) => {
                if (!element || !delta) return;
                const current = Number.parseInt(element.textContent || '0', 10);
                const next = Math.max(0, current + delta);
                element.textContent = String(next);
            };

            const updateTopCounters = (previousStatus, nextStatus) => {
                const oldStatus = (previousStatus || '').toLowerCase();
                const newStatus = (nextStatus || '').toLowerCase();

                if (oldStatus === newStatus) return;

                if (isScheduledStatus(previousStatus) && !isScheduledStatus(nextStatus)) {
                    adjustCountElement(totalAppointmentsCountEl, -1);
                }

                if (!isScheduledStatus(previousStatus) && isScheduledStatus(nextStatus)) {
                    adjustCountElement(totalAppointmentsCountEl, 1);
                }

                if (oldStatus === 'realizado') {
                    adjustCountElement(completedCountEl, -1);
                }

                if (newStatus === 'realizado') {
                    adjustCountElement(completedCountEl, 1);
                }

                if (oldStatus === 'pendente') {
                    adjustCountElement(pendingCountEl, -1);
                }

                if (newStatus === 'pendente') {
                    adjustCountElement(pendingCountEl, 1);
                }
            };

            const setFilterButtonState = (button, isActive) => {
                if (isActive) {
                    button.classList.add('border-violet-500', 'bg-violet-500/20', 'text-violet-200');
                    button.classList.remove('border-zinc-700', 'text-zinc-300');
                    return;
                }

                button.classList.remove('border-violet-500', 'bg-violet-500/20', 'text-violet-200');
                button.classList.add('border-zinc-700', 'text-zinc-300');
            };

            const refreshFilterCounts = () => {
                const applyZeroStyle = (element, value) => {
                    if (!element) return;

                    element.classList.toggle('text-zinc-500', value === 0);
                    element.classList.toggle('text-current', value !== 0);
                };

                let allCount = 0;
                let confirmedCount = 0;
                let pendingCount = 0;
                let completedCount = 0;

                appointmentRows.forEach((row) => {
                    const status = (row.dataset.rowCurrentStatus || '').toLowerCase();
                    const rowAttendant = (row.dataset.rowAttendant || '').toLowerCase();
                    const matchesAttendant = activeAttendantFilter === 'all'
                        ? true
                        : rowAttendant === activeAttendantFilter;

                    if (!matchesAttendant) {
                        return;
                    }

                    if (isScheduledStatus(status)) {
                        allCount += 1;
                    }

                    if (status === 'confirmado') {
                        confirmedCount += 1;
                    }

                    if (status === 'pendente') {
                        pendingCount += 1;
                    }

                    if (status === 'realizado') {
                        completedCount += 1;
                    }
                });

                if (filterCountAllEl) filterCountAllEl.textContent = String(allCount);
                if (filterCountConfirmadoEl) filterCountConfirmadoEl.textContent = String(confirmedCount);
                if (filterCountPendenteEl) filterCountPendenteEl.textContent = String(pendingCount);
                if (filterCountRealizadoEl) filterCountRealizadoEl.textContent = String(completedCount);

                applyZeroStyle(filterCountAllEl, allCount);
                applyZeroStyle(filterCountConfirmadoEl, confirmedCount);
                applyZeroStyle(filterCountPendenteEl, pendingCount);
                applyZeroStyle(filterCountRealizadoEl, completedCount);

                if (totalAppointmentsCountEl) totalAppointmentsCountEl.textContent = String(allCount);
                if (completedCountEl) completedCountEl.textContent = String(completedCount);
                if (pendingCountEl) pendingCountEl.textContent = String(pendingCount);
            };

            const applyAppointmentFilter = () => {
                let visibleRows = 0;

                appointmentRows.forEach((row) => {
                    const status = (row.dataset.rowCurrentStatus || '').toLowerCase();
                    const rowAttendant = (row.dataset.rowAttendant || '').toLowerCase();
                    const matchesStatus = activeAppointmentFilter === 'all'
                        ? isScheduledStatus(status)
                        : status === activeAppointmentFilter;
                    const matchesAttendant = activeAttendantFilter === 'all'
                        ? true
                        : rowAttendant === activeAttendantFilter;
                    const showRow = matchesStatus && matchesAttendant;

                    row.classList.toggle('hidden', !showRow);
                    if (showRow) {
                        visibleRows += 1;
                    }
                });

                if (filterEmptyRow) {
                    filterEmptyRow.classList.toggle('hidden', visibleRows > 0);
                }
            };

            const bindDetailsButton = (button) => {
                button.addEventListener('click', () => {
                    applyStatusBadge(statusEl, button.dataset.status || '—');

                    if (studentEl) studentEl.textContent = button.dataset.student || '—';
                    if (registrationEl) registrationEl.textContent = button.dataset.registration || '—';
                    if (timeEl) timeEl.textContent = button.dataset.time || '—';
                    if (attendantEl) attendantEl.textContent = button.dataset.attendant || '—';
                    if (subjectEl) subjectEl.textContent = button.dataset.subject || '—';
                    if (detailsCancellationReasonEl) {
                        const reason = (button.dataset.cancellationReason || '').trim();
                        detailsCancellationReasonEl.textContent = reason !== '' ? reason : '—';
                    }

                    openModal(detailsModal);
                });
            };

            const bindAttendButton = (button) => {
                button.addEventListener('click', () => {
                    currentAttendButton = button;

                    applyStatusBadge(attendStatusEl, button.dataset.status || '—');

                    if (attendStudentEl) attendStudentEl.textContent = button.dataset.student || '—';
                    if (attendRegistrationEl) attendRegistrationEl.textContent = button.dataset.registration || '—';
                    if (attendTimeEl) attendTimeEl.textContent = button.dataset.time || '—';
                    if (attendAttendantEl) attendAttendantEl.textContent = button.dataset.attendant || '—';
                    if (attendSubjectEl) attendSubjectEl.textContent = button.dataset.subject || '—';

                    selectedRescheduleSlot = '';
                    if (selectedSlotText) selectedSlotText.textContent = 'Nenhum horário selecionado';
                    rescheduleSlots.forEach((slotButton) => {
                        slotButton.classList.remove('bg-violet-500', 'text-white', 'border-violet-500');
                    });

                    openModal(attendModal);
                });
            };

            const promoteActionToDetails = (button) => {
                if (!button) return null;

                const replacement = button.cloneNode(true);
                replacement.textContent = 'Detalhes';
                replacement.removeAttribute('data-open-attend');
                replacement.setAttribute('data-open-details', '');
                replacement.dataset.status = replacement.dataset.status || 'Realizado';

                button.replaceWith(replacement);
                bindDetailsButton(replacement);

                return replacement;
            };

            const updateCurrentRowStatus = (status) => {
                if (!currentAttendButton) return;

                const previousStatus = currentAttendButton.dataset.status || '';
                currentAttendButton.dataset.status = status;

                const row = currentAttendButton.closest('tr');
                const rowStatusBadge = row?.querySelector('[data-row-status]');
                if (row) {
                    row.dataset.rowCurrentStatus = (status || '').toLowerCase();
                }

                applyStatusBadge(rowStatusBadge, status);
                applyStatusBadge(attendStatusEl, status);
                updateTopCounters(previousStatus, status);
                refreshFilterCounts();
                applyAppointmentFilter();
            };

            const updateCurrentRowTime = (timeRange) => {
                if (!currentAttendButton || !timeRange) return;

                currentAttendButton.dataset.time = timeRange;
                if (attendTimeEl) attendTimeEl.textContent = timeRange;

                const row = currentAttendButton.closest('tr');
                const timeCell = row?.querySelector('[data-row-time]');

                if (timeCell) {
                    const [startTime] = timeRange.split(' - ');
                    timeCell.textContent = startTime || timeCell.textContent;
                }
            };

            const openModal = (modal) => {
                if (!modal) return;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            };

            const closeModal = (modal) => {
                if (!modal) return;
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            };

            document.querySelectorAll('[data-open-details]').forEach((button) => bindDetailsButton(button));
            document.querySelectorAll('[data-open-attend]').forEach((button) => bindAttendButton(button));

            filterButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    activeAppointmentFilter = button.dataset.filterStatus || 'all';

                    filterButtons.forEach((otherButton) => {
                        setFilterButtonState(otherButton, otherButton === button);
                    });

                    applyAppointmentFilter();
                });
            });

            attendantFilterSelect?.addEventListener('change', () => {
                const selectedValue = attendantFilterSelect.value || 'all';
                activeAttendantFilter = selectedValue;

                refreshFilterCounts();
                applyAppointmentFilter();
            });

            closeDetailsButtons.forEach((button) => {
                button.addEventListener('click', () => closeModal(detailsModal));
            });

            closeAttendButtons.forEach((button) => {
                button.addEventListener('click', () => closeModal(attendModal));
            });

            closeRescheduleButtons.forEach((button) => {
                button.addEventListener('click', () => closeModal(rescheduleModal));
            });

            closeNoticesButtons.forEach((button) => {
                button.addEventListener('click', () => closeModal(noticesModal));
            });

            openNoticesManagerButton?.addEventListener('click', () => {
                loadNotices();
                renderNoticeAdminList();
                openModal(noticesModal);
            });

            clearNoticeFormButton?.addEventListener('click', () => {
                resetNoticeForm();
            });

            saveNoticeButton?.addEventListener('click', async () => {
                const message = noticeMessageInput?.value?.trim() || '';
                const tone = noticeToneInput?.value || 'amber';

                if (!message) {
                    showUiFeedback('Informe o texto do aviso.', 'warning');
                    return;
                }

                await withLoadingButton(saveNoticeButton, 'Salvando...', async () => {
                    try {
                        if (noticeEditIndex === null) {
                            const createdNotice = await requestNotices(noticesApiUrl, 'POST', { message, tone });
                            if (createdNotice) {
                                notices.unshift(createdNotice);
                            }
                        } else {
                            const currentNotice = notices[noticeEditIndex];
                            if (!currentNotice?.id) return;

                            const updatedNotice = await requestNotices(`${noticesApiUrl}/${currentNotice.id}`, 'PATCH', { message, tone });
                            if (updatedNotice) {
                                notices[noticeEditIndex] = updatedNotice;
                            }
                        }

                        renderNotices();
                        renderNoticeAdminList();
                        resetNoticeForm();
                        showUiFeedback('Aviso salvo com sucesso.');
                    } catch (error) {
                        showUiFeedback('Não foi possível salvar o aviso.', 'error');
                    }
                });
            });

            noticeAdminListEl?.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) return;

                if (target.dataset.editNotice !== undefined) {
                    const index = Number.parseInt(target.dataset.editNotice, 10);
                    const notice = notices[index];
                    if (!notice) return;

                    noticeEditIndex = index;
                    if (noticeFormTitleEl) noticeFormTitleEl.textContent = 'Editar aviso';
                    if (noticeToneInput) noticeToneInput.value = notice.tone;
                    if (noticeMessageInput) noticeMessageInput.value = notice.message;
                    noticeMessageInput?.focus();
                    return;
                }

                if (target.dataset.removeNotice !== undefined) {
                    const index = Number.parseInt(target.dataset.removeNotice, 10);
                    if (Number.isNaN(index)) return;

                    (async () => {
                        try {
                            const currentNotice = notices[index];
                            if (!currentNotice?.id) return;

                            await requestNotices(`${noticesApiUrl}/${currentNotice.id}`, 'DELETE');
                            notices.splice(index, 1);
                            renderNotices();
                            renderNoticeAdminList();
                            showUiFeedback('Aviso removido com sucesso.');

                            if (noticeEditIndex === index || (noticeEditIndex !== null && noticeEditIndex >= notices.length)) {
                                resetNoticeForm();
                            }
                        } catch (error) {
                            showUiFeedback('Não foi possível remover o aviso.', 'error');
                        }
                    })();
                }
            });

            cancelAppointmentButton?.addEventListener('click', () => {
                withLoadingButton(cancelAppointmentButton, 'Cancelando...', async () => {
                    if (!currentAttendButton?.dataset.appointmentId) return;

                    try {
                        const url = appointmentStatusUrlTemplate.replace('__ID__', currentAttendButton.dataset.appointmentId);
                        const response = await requestAppointment(url, { status: 'Cancelado' });

                        updateCurrentRowStatus(response?.status || 'Cancelado');
                        currentAttendButton = promoteActionToDetails(currentAttendButton);
                        closeModal(attendModal);
                        showUiFeedback('Agendamento cancelado com sucesso.');
                    } catch (error) {
                        showUiFeedback('Não foi possível cancelar o agendamento.', 'error');
                    }
                });
            });

            markDoneButton?.addEventListener('click', () => {
                withLoadingButton(markDoneButton, 'Salvando...', async () => {
                    if (!currentAttendButton?.dataset.appointmentId) return;

                    try {
                        const url = appointmentStatusUrlTemplate.replace('__ID__', currentAttendButton.dataset.appointmentId);
                        const response = await requestAppointment(url, { status: 'Realizado' });

                        updateCurrentRowStatus(response?.status || 'Realizado');
                        currentAttendButton = promoteActionToDetails(currentAttendButton);
                        closeModal(attendModal);
                        showUiFeedback('Atendimento marcado como realizado.');
                    } catch (error) {
                        showUiFeedback('Não foi possível atualizar o atendimento.', 'error');
                    }
                });
            });

            openRescheduleButton?.addEventListener('click', () => {
                if (rescheduleSubtitle && currentAttendButton) {
                    rescheduleSubtitle.textContent = `${currentAttendButton.dataset.student || '—'} · ${currentAttendButton.dataset.subject || '—'}`;
                }

                if (rescheduleDateInput && !rescheduleDateInput.value) {
                    rescheduleDateInput.value = '2026-03-18';
                }

                closeModal(attendModal);
                openModal(rescheduleModal);
            });

            rescheduleSlots.forEach((slotButton) => {
                slotButton.addEventListener('click', () => {
                    selectedRescheduleSlot = slotButton.dataset.slotValue || '';

                    rescheduleSlots.forEach((otherSlotButton) => {
                        otherSlotButton.classList.remove('bg-violet-500', 'text-white', 'border-violet-500');
                    });

                    slotButton.classList.add('bg-violet-500', 'text-white', 'border-violet-500');

                    if (selectedSlotText) {
                        selectedSlotText.textContent = `${selectedRescheduleSlot} selecionado`;
                    }
                });
            });

            confirmRescheduleButton?.addEventListener('click', () => {
                withLoadingButton(confirmRescheduleButton, 'Confirmando...', async () => {
                    if (!currentAttendButton?.dataset.appointmentId || !selectedRescheduleSlot || !rescheduleDateInput?.value) {
                        showUiFeedback('Selecione data e horário para reagendar.', 'warning');
                        return;
                    }

                    try {
                        const url = appointmentRescheduleUrlTemplate.replace('__ID__', currentAttendButton.dataset.appointmentId);
                        const response = await requestAppointment(url, {
                            date: rescheduleDateInput.value,
                            time: selectedRescheduleSlot,
                        });

                        const newRange = response?.scheduled_time_range;
                        if (newRange) {
                            updateCurrentRowTime(newRange);
                        }

                        updateCurrentRowStatus(response?.status || 'Confirmado');
                        closeModal(rescheduleModal);
                        showUiFeedback('Agendamento reagendado com sucesso.');
                    } catch (error) {
                        showUiFeedback('Não foi possível reagendar o atendimento.', 'error');
                    }
                });
            });

            detailsModal?.addEventListener('click', (event) => {
                if (event.target === detailsModal) closeModal(detailsModal);
            });

            attendModal?.addEventListener('click', (event) => {
                if (event.target === attendModal) closeModal(attendModal);
            });

            rescheduleModal?.addEventListener('click', (event) => {
                if (event.target === rescheduleModal) closeModal(rescheduleModal);
            });

            noticesModal?.addEventListener('click', (event) => {
                if (event.target === noticesModal) closeModal(noticesModal);
            });

            renderNotices();
            resetNoticeForm();
            loadNotices();
            refreshFilterCounts();
            applyAppointmentFilter();
        });
    </script>
</x-layouts.academic>
