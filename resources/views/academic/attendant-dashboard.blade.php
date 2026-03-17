@php
    $title = 'Painel do Admin';
    $role = 'admin';
    $displayName = auth()->user()?->name ?? 'Admin · Sec. Acadêmica';
@endphp

<x-layouts.academic :title="$title" :role="$role" active="agenda" :userName="$displayName" userInitials="AT">
    <section class="space-y-5">
        <div>
            <h1 class="text-4xl font-semibold">Painel do admin</h1>
            <p class="mt-1 text-zinc-400">Secretaria Acadêmica • Segunda, 16 de março de 2026</p>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            @foreach ([['8', 'Agendados hoje'], ['3', 'Realizados'], ['1', 'Pendentes'], ['4', 'Vagas hoje']] as [$value, $label])
                <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                    <p class="text-4xl font-bold">{{ $value }}</p>
                    <p class="text-zinc-400">{{ $label }}</p>
                </article>
            @endforeach
        </div>

        <div class="grid gap-4 xl:grid-cols-[2fr_1fr]">
            <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-3xl font-semibold">Agendamentos — Hoje</h2>
                    <div class="flex gap-2">
                        <button class="rounded-xl border border-zinc-700 px-4 py-2">Exportar</button>
                        <button class="rounded-xl border border-zinc-700 px-4 py-2">Imprimir agenda</button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[850px] text-left">
                        <thead class="bg-zinc-900 text-zinc-400">
                            <tr>
                                <th class="px-4 py-3 font-medium">Horário</th>
                                <th class="px-4 py-3 font-medium">Aluno</th>
                                <th class="px-4 py-3 font-medium">Matrícula</th>
                                <th class="px-4 py-3 font-medium">Assunto</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium">Ação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800">
                            @foreach ([
                                ['08:00', 'Marcus Vinícius', '20241180171', 'Trancamento parcial', 'realizado', 'Detalhes'],
                                ['09:00', 'Gabriel Silva', '20241180203', 'Orientação TCC', 'confirmado', 'Atender'],
                                ['10:00', 'Luiz Gabriel', '20232130014', 'Histórico escolar', 'pendente', 'Atender'],
                                ['10:30', '— Horário livre —', '', '', 'livre', 'Bloquear'],
                                ['11:00', 'Jose Ueider', '200610066', 'Requerimento geral', 'confirmado', 'Atender'],
                            ] as [$time, $student, $registration, $subject, $status, $action])
                                <tr>
                                    <td class="px-4 py-3">{{ $time }}</td>
                                    <td class="px-4 py-3">{{ $student }}</td>
                                    <td class="px-4 py-3">{{ $registration }}</td>
                                    <td class="px-4 py-3">{{ $subject }}</td>
                                    <td class="px-4 py-3">
                                        @if ($status !== 'livre')
                                            <span class="rounded-full px-3 py-1 text-sm {{ $status === 'confirmado' ? 'bg-blue-500/20 text-blue-300' : ($status === 'pendente' ? 'bg-amber-500/20 text-amber-300' : 'bg-emerald-500/20 text-emerald-300') }}">{{ ucfirst($status) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3"><button class="rounded-xl border border-zinc-700 px-3 py-1">{{ $action }}</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </article>

            <div class="space-y-4">
                <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                    <h3 class="text-3xl font-semibold">Cadastrar aluno</h3>
                    <form method="POST" action="{{ route('academic.admin.students.store') }}" class="mt-4 space-y-3">
                        @csrf
                        <div>
                            <label class="mb-2 block text-zinc-400">Nome completo</label>
                            <input type="text" name="name" value="{{ old('name') }}" placeholder="ex: Gabriel Silva" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2" />
                            @error('name', 'studentCreate')
                                <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="w-full rounded-xl border border-zinc-700 px-4 py-2 text-3xl font-semibold hover:border-violet-400">Cadastrar aluno</button>
                        <p class="border-l border-zinc-700 pl-3 text-sm italic text-zinc-500">A matrícula e o e-mail institucional são gerados automaticamente. Senha padrão: 123456.</p>
                    </form>
                </article>

                <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                    <h3 class="text-3xl font-semibold">Gerenciar disponibilidade</h3>
                    <form class="mt-4 space-y-3">
                        <div>
                            <label class="mb-2 block text-zinc-400">Duração padrão</label>
                            <select class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2">
                                <option>30 minutos</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-zinc-400">Horário de início</label>
                            <input type="text" value="08:00" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2" />
                        </div>
                        <div>
                            <label class="mb-2 block text-zinc-400">Horário de fim</label>
                            <input type="text" value="17:00" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2" />
                        </div>
                        <div>
                            <label class="mb-2 block text-zinc-400">Intervalo</label>
                            <input type="text" value="12:00 - 13:00" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2" />
                        </div>
                        <button type="button" class="w-full rounded-xl border border-zinc-700 px-4 py-2 text-3xl font-semibold hover:border-violet-400">Salvar configuração</button>
                        <button type="button" class="w-full rounded-xl border border-zinc-700 px-4 py-2">Bloquear dia inteiro</button>
                    </form>
                </article>

                <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                    <h3 class="text-3xl font-semibold">Filtro rápido de status</h3>
                    <div class="mt-4 space-y-2">
                        @foreach (['Todos (8)' => true, 'Confirmados (4)' => false, 'Pendentes (1)' => false, 'Realizados (3)' => false, 'Cancelados (0)' => false] as $label => $selected)
                            <button class="w-full rounded-full border px-3 py-1 text-left {{ $selected ? 'border-violet-500 bg-violet-500/20 text-violet-200' : 'border-zinc-700 text-zinc-400' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </article>
            </div>
        </div>
    </section>
</x-layouts.academic>
