@php
    $title = 'Início | Aluno';
    $role = 'student';
    $user = auth()->user();
    $displayName = $user?->name ?? 'Gabriel Silva';
    $firstName = str($displayName)->before(' ');
    $mustChangePassword = (bool) ($user?->must_change_password ?? false);
    $activeCount = $activeCount ?? 0;
    $completedCount = $completedCount ?? 0;
    $todayAvailableCount = $todayAvailableCount ?? 0;
    $upcomingAppointments = collect($upcomingAppointments ?? []);
    $notices = collect($notices ?? []);
    $availableSlotsToday = collect($availableSlotsToday ?? []);
    $todayLabel = $todayLabel ?? now()->format('d/m/Y');
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
            @foreach ([[$activeCount, 'Agendamentos ativos'], [$completedCount, 'Atendimentos realizados'], [$todayAvailableCount, 'Horários disponíveis hoje']] as [$value, $label])
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
                    <a href="{{ route('academic.student.new') }}" class="rounded-xl border border-zinc-700 px-5 py-2 text-3xl font-semibold hover:border-violet-400">+ Novo agendamento</a>
                    <a href="{{ route('academic.student.mine') }}" class="rounded-xl border border-zinc-700 px-5 py-2 text-3xl font-semibold hover:border-violet-400">Ver todos</a>
                </div>
            </article>

            <div class="space-y-4">
                <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                    <h3 class="text-3xl font-semibold">Calendário · Mar/2026</h3>
                    <div class="mt-4 grid grid-cols-7 gap-2 text-center text-sm">
                        @foreach (['D','S','T','Q','Q','S','S'] as $d)
                            <span class="text-zinc-500">{{ $d }}</span>
                        @endforeach
                        @foreach (range(2, 28) as $day)
                            <span class="rounded-lg py-1 {{ in_array($day, [16]) ? 'bg-violet-500/30 text-violet-200' : (in_array($day, [9,18,26]) ? 'text-emerald-400' : (in_array($day, [6,13]) ? 'text-rose-400' : 'text-zinc-400')) }}">{{ $day }}</span>
                        @endforeach
                    </div>
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
            <h3 class="text-3xl font-semibold">Horários disponíveis hoje — {{ $todayLabel }}</h3>
            <p class="mt-2 text-sm italic text-zinc-500">Verde = disponível · Cinza = ocupado · Destaque = selecionado</p>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($availableSlotsToday as $slot)
                    <button class="rounded-lg border px-4 py-2 text-lg {{ $slot['available'] ? 'border-emerald-700 bg-emerald-700/35 text-emerald-300' : 'border-zinc-800 bg-zinc-800 text-zinc-500 line-through' }}">{{ $slot['time'] }}</button>
                @endforeach
            </div>
        </article>
    </section>
</x-layouts.academic>
