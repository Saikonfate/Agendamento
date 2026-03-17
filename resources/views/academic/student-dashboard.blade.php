@php
    $title = 'Início | Aluno';
    $role = 'student';
    $user = auth()->user();
    $displayName = $user?->name ?? 'Gabriel Silva';
    $firstName = str($displayName)->before(' ');
@endphp

<x-layouts.academic :title="$title" :role="$role" active="inicio" :userName="$displayName">
    <section class="space-y-5">
        <div>
            <h1 class="text-4xl font-semibold">Olá, {{ $firstName }} 👋</h1>
            <p class="mt-1 text-zinc-400">Você está logado como aluno</p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            @foreach ([['2', 'Agendamentos ativos'], ['5', 'Atendimentos realizados'], ['3', 'Horários disponíveis hoje']] as [$value, $label])
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
                    <div class="border-l border-zinc-700 pl-4">
                        <p class="text-zinc-400">Seg, 16 Mar · 09:00</p>
                        <p class="text-2xl font-semibold">Orientação de TCC</p>
                        <p class="text-zinc-400">Prof. Ramon Felizardo · Sala 204</p>
                        <span class="mt-2 inline-flex rounded-full bg-blue-500/20 px-3 py-1 text-sm text-blue-300">Confirmado</span>
                    </div>
                    <div class="border-l border-zinc-700 pl-4">
                        <p class="text-zinc-400">Qua, 18 Mar · 14:00</p>
                        <p class="text-2xl font-semibold">Revisão de prova</p>
                        <p class="text-zinc-400">Sec. Acadêmica · Balcão 02</p>
                        <span class="mt-2 inline-flex rounded-full bg-amber-500/20 px-3 py-1 text-sm text-amber-300">Pendente</span>
                    </div>
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
                        <li>📌 Secretaria fechada dia 19/03 (feriado)</li>
                        <li>📄 Prazo para requerimentos: 25/03</li>
                    </ul>
                </article>
            </div>
        </div>

        <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5 lg:max-w-4xl">
            <h3 class="text-3xl font-semibold">Horários disponíveis hoje — 16/03/2026</h3>
            <p class="mt-2 text-sm italic text-zinc-500">Verde = disponível · Cinza = ocupado · Destaque = selecionado</p>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach (['08:00' => 'off', '08:30' => 'off', '09:00' => 'on', '09:30' => 'on', '10:00' => 'off', '10:30' => 'off', '11:00' => 'off', '11:30' => 'on', '13:00' => 'off', '13:30' => 'off', '14:00' => 'on', '14:30' => 'off'] as $time => $status)
                    <button class="rounded-lg border px-4 py-2 text-lg {{ $status === 'on' ? 'border-emerald-700 bg-emerald-700/35 text-emerald-300' : 'border-zinc-800 bg-zinc-800 text-zinc-500 line-through' }}">{{ $time }}</button>
                @endforeach
            </div>
            <button class="mt-4 rounded-xl border border-zinc-700 px-5 py-2 text-3xl font-semibold hover:border-violet-400">Agendar um horário</button>
        </article>
    </section>
</x-layouts.academic>
