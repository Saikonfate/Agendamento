@php
    $title = 'Meus Agendamentos | Aluno';
    $role = 'student';
    $displayName = auth()->user()?->name ?? 'Gabriel Silva';
@endphp

<x-layouts.academic :title="$title" :role="$role" active="meus" :userName="$displayName">
    <section class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-4xl font-semibold">Meus agendamentos</h1>
                <p class="mt-1 text-zinc-400">Gerencie e acompanhe todos os seus atendimentos</p>
            </div>
            <a href="{{ route('academic.student.new') }}" class="rounded-2xl border border-zinc-700 px-5 py-2 text-3xl font-semibold hover:border-violet-400">+ Novo agendamento</a>
        </div>

        <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70">
            <div class="grid gap-3 border-b border-zinc-800 p-4 lg:grid-cols-[1fr_auto] lg:items-center">
                <div class="flex flex-wrap gap-2">
                    <input type="text" placeholder="🔍 Buscar agendamento..." class="min-w-72 rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2" />
                    @foreach (['Todos', 'Confirmados', 'Pendentes', 'Cancelados', 'Realizados'] as $index => $filter)
                        <button class="rounded-full border px-3 py-1 text-sm {{ $index === 0 ? 'border-violet-500 bg-violet-500/20 text-violet-200' : 'border-zinc-700 text-zinc-400' }}">{{ $filter }}</button>
                    @endforeach
                </div>
                <select class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2">
                    <option>Mar/2026</option>
                </select>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left">
                    <thead class="bg-zinc-900 text-zinc-400">
                        <tr>
                            <th class="px-4 py-3 font-medium">Data</th>
                            <th class="px-4 py-3 font-medium">Horário</th>
                            <th class="px-4 py-3 font-medium">Tipo de atendimento</th>
                            <th class="px-4 py-3 font-medium">Atendente</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @foreach ([
                            ['16/03/2026','09:00','Orientação de TCC','Prof. Ramon Felizardo','confirmado'],
                            ['18/03/2026','14:00','Secretaria Acadêmica','Sec. Acadêmica','pendente'],
                            ['05/03/2026','10:30','Revisão de prova','Prof. Ana Lima','realizado'],
                            ['20/02/2026','09:00','Coordenação Acadêmica','Coord. João Paulo','realizado'],
                            ['10/02/2026','14:00','Financeiro Acadêmico','—','cancelado'],
                        ] as [$date, $time, $type, $agent, $status])
                            <tr>
                                <td class="px-4 py-3">{{ $date }}</td>
                                <td class="px-4 py-3">{{ $time }}</td>
                                <td class="px-4 py-3">{{ $type }}</td>
                                <td class="px-4 py-3">{{ $agent }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-3 py-1 text-sm {{ $status === 'confirmado' ? 'bg-blue-500/20 text-blue-300' : ($status === 'pendente' ? 'bg-amber-500/20 text-amber-300' : ($status === 'realizado' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300')) }}">{{ ucfirst($status) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex gap-2">
                                        @if (in_array($status, ['confirmado', 'pendente', 'cancelado']))
                                            <button class="rounded-xl border border-zinc-700 px-3 py-1 hover:border-violet-400">Reagendar</button>
                                        @endif
                                        @if (in_array($status, ['confirmado', 'pendente']))
                                            <button class="rounded-xl border border-zinc-700 px-3 py-1">Cancelar</button>
                                        @else
                                            <button class="rounded-xl border border-zinc-700 px-3 py-1">Ver detalhes</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </article>

        <p class="text-lg italic text-zinc-500">Cancelamento disponível somente com antecedência mínima de 2h. Reagendamento redireciona para tela de novo agendamento com dados pré-preenchidos.</p>
    </section>
</x-layouts.academic>
