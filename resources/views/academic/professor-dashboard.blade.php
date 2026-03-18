@php
    $title = 'Início | Professor';
    $role = 'professor';
    $user = auth()->user();
    $displayName = $user?->name ?? 'Professor';
    $firstName = str($displayName)->before(' ');
    $appointments = collect($appointments ?? []);
    $todayCount = $todayCount ?? $appointments->whereIn('status', ['Confirmado', 'Pendente', 'Realizado'])->count();
    $pendingCount = $pendingCount ?? $appointments->where('status', 'Pendente')->count();
    $completedMonthCount = $completedMonthCount ?? $appointments->where('status', 'Realizado')->count();
    $todayLabel = $todayLabel ?? now()->format('d/m/Y');
    $attendantAlias = $attendantAlias ?? ($displayName !== '' ? 'Prof. '.$displayName : 'Professor');
    $schedule = $schedule ?? [
        'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
        'start_time' => '08:00',
        'end_time' => '17:00',
        'break_start' => '12:00',
        'break_end' => '13:00',
        'slot_duration_minutes' => 30,
    ];
    $daySettings = $schedule['day_settings'] ?? [];
@endphp

<x-layouts.academic :title="$title" :role="$role" active="inicio" :userName="$displayName">
    <section class="space-y-5">
        <div>
            <h1 class="text-4xl font-semibold">Olá, Prof. {{ $firstName }} 👋</h1>
            <p class="mt-1 text-zinc-400">Acompanhe e atualize seus atendimentos</p>
        </div>

        @if (session('status'))
            <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-3">
            @foreach ([[$todayCount, 'Atendimentos hoje'], [$pendingCount, 'Pendentes'], [$completedMonthCount, 'Realizados no mês']] as [$value, $label])
                <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                    <p class="text-4xl font-bold">{{ $value }}</p>
                    <p class="text-zinc-400">{{ $label }}</p>
                </article>
            @endforeach
        </div>

        <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-3xl font-semibold">Minha disponibilidade</h2>
                <span class="text-sm text-zinc-400">Atendente no sistema: {{ $attendantAlias }}</span>
            </div>

            <form method="POST" action="{{ route('academic.professor.availability.store') }}" class="mt-4 space-y-4">
                @csrf

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
                    @error('working_days')<p class="mt-2 text-sm text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-zinc-400">Duração do atendimento</label>
                    <select name="slot_duration_minutes" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2">
                        @foreach ([30, 45, 60] as $duration)
                            <option value="{{ $duration }}" @selected((int) old('slot_duration_minutes', $schedule['slot_duration_minutes'] ?? 30) === $duration)>{{ $duration }} minutos</option>
                        @endforeach
                    </select>
                    @error('slot_duration_minutes')<p class="mt-2 text-sm text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div class="border-t border-zinc-800 pt-4">
                    <button type="submit" class="rounded-xl border border-zinc-700 px-4 py-2 text-xl font-semibold text-white hover:border-violet-400">Salvar disponibilidade</button>
                </div>
            </form>
        </article>

        <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-3xl font-semibold">Agenda de atendimentos</h2>
                <span class="text-sm text-zinc-400">Período: a partir de {{ $todayLabel }}</span>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[880px] text-left">
                    <thead class="text-sm text-zinc-400">
                        <tr>
                            <th class="px-3 py-2 font-medium">Data</th>
                            <th class="px-3 py-2 font-medium">Horário</th>
                            <th class="px-3 py-2 font-medium">Aluno</th>
                            <th class="px-3 py-2 font-medium">Matrícula</th>
                            <th class="px-3 py-2 font-medium">Assunto</th>
                            <th class="px-3 py-2 font-medium">Status</th>
                            <th class="px-3 py-2 font-medium">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @forelse ($appointments as $appointment)
                            <tr>
                                <td class="px-3 py-3 text-zinc-300">{{ $appointment->scheduled_at->locale('pt_BR')->translatedFormat('d/m/Y') }}</td>
                                <td class="px-3 py-3 font-semibold text-violet-200">{{ $appointment->scheduled_at->format('H:i') }}</td>
                                <td class="px-3 py-3 text-zinc-200">{{ $appointment->student_name }}</td>
                                <td class="px-3 py-3 text-zinc-400">{{ $appointment->student_registration }}</td>
                                <td class="px-3 py-3 text-zinc-200">{{ $appointment->subject }}</td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full px-3 py-1 text-sm {{ $appointment->status === 'Confirmado' ? 'bg-blue-500/20 text-blue-300' : ($appointment->status === 'Pendente' ? 'bg-amber-500/20 text-amber-300' : ($appointment->status === 'Cancelado' ? 'bg-rose-500/20 text-rose-300' : 'bg-emerald-500/20 text-emerald-300')) }}">
                                        {{ $appointment->status }}
                                    </span>
                                    @if ($appointment->status === 'Cancelado' && $appointment->cancellation_reason)
                                        <p class="mt-2 max-w-md text-xs text-rose-300">Motivo: {{ $appointment->cancellation_reason }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if (in_array($appointment->status, ['Confirmado', 'Pendente'], true))
                                        <div class="flex flex-wrap gap-2">
                                            <form method="POST" action="{{ route('academic.professor.appointments.status', $appointment) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="Realizado" />
                                                <button type="submit" class="rounded-xl border border-zinc-700 px-3 py-1 text-sm font-semibold text-white hover:border-violet-400">Marcar realizado</button>
                                            </form>
                                            <form method="POST" action="{{ route('academic.professor.appointments.status', $appointment) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="Cancelado" />
                                                <input
                                                    type="text"
                                                    name="cancellation_reason"
                                                    maxlength="500"
                                                    required
                                                    placeholder="Motivo da rejeição"
                                                    class="rounded-xl border border-zinc-700 bg-zinc-800 px-3 py-1 text-sm text-zinc-100 placeholder:text-zinc-400"
                                                />
                                                <button type="submit" class="rounded-xl border border-zinc-700 px-3 py-1 text-sm font-semibold text-white hover:border-violet-400">Rejeitar</button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-sm text-zinc-500">Sem ações</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-6 text-center text-zinc-400">Nenhum atendimento para hoje.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </section>
</x-layouts.academic>
