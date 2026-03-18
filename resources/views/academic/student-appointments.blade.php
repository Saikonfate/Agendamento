@php
    $title = 'Meus Agendamentos | Aluno';
    $role = 'student';
    $displayName = auth()->user()?->name ?? 'Gabriel Silva';
    $appointments = collect($appointments ?? []);
    $statusFilter = $statusFilter ?? 'Todos';
    $search = $search ?? '';
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
            <form method="GET" action="{{ route('academic.student.mine') }}" class="grid gap-3 border-b border-zinc-800 p-4 lg:grid-cols-[1fr_auto_auto] lg:items-center">
                <input name="q" value="{{ $search }}" type="text" placeholder="🔍 Buscar agendamento..." class="min-w-72 rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2" />
                <select name="status" class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2">
                    @foreach (['Todos', 'Confirmado', 'Pendente', 'Cancelado', 'Realizado'] as $filter)
                        <option value="{{ $filter }}" @selected($statusFilter === $filter)>{{ $filter === 'Todos' ? 'Todos' : $filter.'s' }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-xl border border-zinc-700 px-4 py-2 text-sm font-semibold hover:border-violet-400">Filtrar</button>
            </form>

            @if (session('status'))
                <div class="border-b border-zinc-800 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">{{ session('status') }}</div>
            @endif

            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left">
                    <thead class="bg-zinc-900 text-zinc-400">
                        <tr>
                            <th class="px-4 py-3 font-medium">Data</th>
                            <th class="px-4 py-3 font-medium">Horário</th>
                            <th class="px-4 py-3 font-medium">Tipo de atendimento</th>
                            <th class="px-4 py-3 font-medium">Admin</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @forelse ($appointments as $appointment)
                            <tr>
                                <td class="px-4 py-3">{{ $appointment->scheduled_at->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $appointment->scheduled_at->format('H:i') }}</td>
                                <td class="px-4 py-3">{{ $appointment->subject }}</td>
                                <td class="px-4 py-3">{{ $appointment->attendant_display_name }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-3 py-1 text-sm {{ $appointment->status === 'Confirmado' ? 'bg-blue-500/20 text-blue-300' : ($appointment->status === 'Pendente' ? 'bg-amber-500/20 text-amber-300' : ($appointment->status === 'Realizado' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300')) }}">{{ $appointment->status }}</span>
                                    @if ($appointment->status === 'Cancelado' && $appointment->cancellation_reason)
                                        <p class="mt-2 max-w-md text-xs text-rose-300">Motivo: {{ $appointment->cancellation_reason }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex gap-2">
                                        @if (in_array($appointment->status, ['Confirmado', 'Pendente'], true))
                                            <a href="{{ route('academic.student.new', ['date' => $appointment->scheduled_at->format('Y-m-d'), 'attendant_user_id' => $appointment->attendant_user_id, 'attendant_name' => $appointment->attendant_display_name, 'subject' => $appointment->subject]) }}" class="rounded-xl border border-zinc-700 px-3 py-1 hover:border-violet-400">Reagendar</a>
                                            <form method="POST" action="{{ route('academic.student.cancel', $appointment) }}" data-cancel-form>
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" data-cancel-button class="rounded-xl border border-zinc-700 px-3 py-1">Cancelar</button>
                                            </form>
                                        @else
                                            <a href="{{ route('academic.student.new') }}" class="rounded-xl border border-zinc-700 px-3 py-1">Novo</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-zinc-400">Nenhum agendamento encontrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <p class="text-lg italic text-zinc-500">Cancelamento disponível somente com antecedência mínima de 2h. Reagendamento redireciona para tela de novo agendamento com dados pré-preenchidos.</p>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-cancel-form]').forEach((form) => {
                form.addEventListener('submit', () => {
                    const cancelButton = form.querySelector('[data-cancel-button]');
                    if (!cancelButton) return;

                    cancelButton.disabled = true;
                    cancelButton.classList.add('opacity-60', 'cursor-not-allowed');
                    cancelButton.textContent = 'Cancelando...';
                });
            });
        });
    </script>
</x-layouts.academic>
