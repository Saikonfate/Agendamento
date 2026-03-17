@php
    $title = 'Novo Agendamento | Aluno';
    $role = 'student';
    $displayName = auth()->user()?->name ?? 'Gabriel Silva';
@endphp

<x-layouts.academic :title="$title" :role="$role" active="agendar" :userName="$displayName">
    <section class="space-y-5">
        <div>
            <h1 class="text-4xl font-semibold">Novo agendamento</h1>
            <p class="mt-1 text-zinc-400">Preencha os dados abaixo para solicitar um atendimento</p>
        </div>

        <div class="grid grid-cols-3 items-center gap-4 text-sm text-zinc-400">
            <div class="flex items-center gap-2"><span class="flex size-7 items-center justify-center rounded-full border border-violet-400 bg-violet-500/20 text-violet-200">1</span> Tipo</div>
            <div class="flex items-center gap-2"><span class="flex size-7 items-center justify-center rounded-full border border-violet-400 bg-violet-500/20 text-violet-200">2</span> Data / Horário</div>
            <div class="flex items-center gap-2"><span class="flex size-7 items-center justify-center rounded-full border border-zinc-700">3</span> Confirmar</div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="space-y-4">
                <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                    <h2 class="text-3xl font-semibold">1 · Tipo de atendimento</h2>
                    <div class="mt-4 space-y-3 border-y border-zinc-800 py-4">
                        @foreach (['Secretaria Acadêmica' => true, 'Orientação com Professor' => false, 'Coordenação Acadêmica' => false, 'Financeiro Acadêmico' => false] as $type => $selected)
                            <label class="flex items-center gap-3 text-xl">
                                <span class="size-4 rounded-full border {{ $selected ? 'border-violet-400 bg-violet-400' : 'border-zinc-500' }}"></span>
                                {{ $type }}
                            </label>
                        @endforeach
                    </div>
                    <div class="mt-4">
                        <label class="mb-2 block text-xl text-zinc-400">Motivo / Assunto</label>
                        <textarea rows="3" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2.5" placeholder="Requerimento de histórico escolar..."></textarea>
                    </div>
                </article>

                <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                    <h2 class="text-3xl font-semibold">Filtros de disponibilidade</h2>
                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="mb-2 block text-xl text-zinc-400">Atendente / Professor</label>
                            <select class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2.5">
                                <option>Qualquer disponível</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-xl text-zinc-400">Preferência de turno</label>
                            <div class="flex gap-2">
                                <button class="rounded-full border border-violet-400 bg-violet-500/20 px-3 py-1 text-violet-200">Manhã</button>
                                <button class="rounded-full border border-violet-400 bg-violet-500/20 px-3 py-1 text-violet-200">Tarde</button>
                                <button class="rounded-full border border-zinc-700 px-3 py-1 text-zinc-400">Noite</button>
                            </div>
                        </div>
                    </div>
                </article>
            </div>

            <div class="space-y-4">
                <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                    <h2 class="text-3xl font-semibold">2 · Escolha a data</h2>
                    <div class="mt-4">
                        <label class="mb-2 block text-xl text-zinc-400">Data</label>
                        <input type="text" value="18/03/2026" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2.5" />
                    </div>
                    <div class="mt-4 grid grid-cols-7 gap-2 text-center text-sm">
                        @foreach (['D','S','T','Q','Q','S','S'] as $d)
                            <span class="text-zinc-500">{{ $d }}</span>
                        @endforeach
                        @foreach (range(2, 21) as $day)
                            <button class="rounded-lg py-1 {{ $day === 16 ? 'bg-violet-300/60 text-zinc-900' : ($day === 18 ? 'bg-violet-500/25 text-violet-200' : ($day === 13 ? 'text-rose-400' : 'text-zinc-400')) }}">{{ $day }}</button>
                        @endforeach
                    </div>
                </article>

                <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                    <h3 class="text-3xl font-semibold">Horários disponíveis — Qua 18/03</h3>
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach (['08:00'=>'off','08:30'=>'on','09:00'=>'off','09:30'=>'on','10:00'=>'off','10:30'=>'on','11:00'=>'off','11:30'=>'on','13:00'=>'off','13:30'=>'off','14:00'=>'selected','14:30'=>'on'] as $time => $status)
                            <button class="rounded-lg border px-3 py-2 {{ $status === 'on' ? 'border-emerald-700 bg-emerald-700/35 text-emerald-300' : ($status === 'selected' ? 'border-violet-400 bg-violet-500/25 text-violet-200' : 'border-zinc-800 bg-zinc-800 text-zinc-500 line-through') }}">{{ $time }}</button>
                        @endforeach
                    </div>
                    <p class="mt-2 text-sm italic text-zinc-500">14:00 selecionado — Secretaria Acadêmica</p>
                </article>

                <article class="rounded-2xl border border-violet-500/40 bg-violet-500/10 p-5">
                    <h3 class="text-2xl font-semibold text-violet-200">3 · Confirmação</h3>
                    <div class="mt-4 space-y-2 text-xl">
                        <p><span class="font-semibold">Tipo:</span> Secretaria Acadêmica</p>
                        <p><span class="font-semibold">Data:</span> Quarta, 18 de março de 2026</p>
                        <p><span class="font-semibold">Horário:</span> 14:00 - 14:30</p>
                        <p><span class="font-semibold">Motivo:</span> Requerimento de histórico escolar</p>
                    </div>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <button class="rounded-xl border border-zinc-600 px-4 py-2 text-3xl font-semibold hover:border-violet-300">Confirmar agendamento</button>
                        <button class="rounded-xl border border-zinc-700 px-4 py-2 text-3xl font-semibold">Cancelar</button>
                    </div>
                    <p class="mt-3 border-l border-zinc-700 pl-3 text-sm italic text-zinc-400">Sistema verifica conflito antes de confirmar. Se horário já foi tomado, exibe alerta e recarrega grade.</p>
                </article>
            </div>
        </div>
    </section>
</x-layouts.academic>
