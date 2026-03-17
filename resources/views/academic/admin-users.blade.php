@php
    $title = 'Cadastros | Admin';
    $role = 'admin';
    $displayName = auth()->user()?->name ?? 'Admin · Sec. Acadêmica';
    $activeTab = $errors->studentCreate->isNotEmpty() ? 'student' : ($errors->professorCreate->isNotEmpty() ? 'professor' : 'student');
    $students = \App\Models\User::query()->where('role', 'student')->latest()->take(6)->get();
    $professors = \App\Models\User::query()->where('role', 'professor')->latest()->take(6)->get();
@endphp

<x-layouts.academic :title="$title" :role="$role" active="cadastros" :userName="$displayName" userInitials="AD">
    <section class="space-y-5">
        <div>
            <h1 class="text-4xl font-semibold">Gerenciar cadastros</h1>
            <p class="mt-1 text-zinc-400">Cadastre e gerencie professores e alunos do sistema</p>
        </div>

        <div class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5" data-register-tabs data-active-tab="{{ $activeTab }}">
            <div class="mb-4 flex gap-3 border-b border-zinc-800 pb-3">
                <button type="button" data-tab-trigger="student" class="rounded-xl border px-4 py-1.5 text-sm font-semibold">Aluno</button>
                <button type="button" data-tab-trigger="professor" class="rounded-xl border px-4 py-1.5 text-sm font-semibold">Professor</button>
            </div>

            <div class="grid gap-4 xl:grid-cols-[1fr_1.15fr]">
                <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                    <form method="POST" action="{{ route('academic.admin.students.store') }}" data-tab-panel="student" class="space-y-4">
                        @csrf
                        <h2 class="text-3xl font-semibold">Novo cadastro — Aluno</h2>
                        <div>
                            <label class="mb-2 block text-zinc-400">Nome completo</label>
                            <input type="text" name="name" value="{{ old('name') }}" placeholder="ex: Gabriel Silva" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2" />
                            @error('name', 'studentCreate')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-2 block text-zinc-400">E-mail pessoal (opcional)</label>
                            <input type="email" name="personal_email" value="{{ old('personal_email') }}" placeholder="ex: aluno@gmail.com" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2" />
                            @error('personal_email', 'studentCreate')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-2 block text-zinc-400">Matrícula</label>
                            <input type="text" value="Gerada automaticamente" disabled class="w-full cursor-not-allowed rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-500" />
                        </div>
                        <div>
                            <label class="mb-2 block text-zinc-400">E-mail institucional</label>
                            <input type="text" value="Gerado automaticamente" disabled class="w-full cursor-not-allowed rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-500" />
                        </div>
                        <div>
                            <label class="mb-2 block text-zinc-400">Senha provisória</label>
                            <input type="text" value="123456" disabled class="w-full cursor-not-allowed rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-500" />
                            <p class="mt-1 text-xs text-zinc-500">O aluno deverá alterar no primeiro acesso.</p>
                        </div>
                        <div class="flex gap-3 pt-1">
                            <button type="submit" class="rounded-xl border border-zinc-700 px-4 py-2 text-xl font-semibold hover:border-violet-400">Cadastrar aluno</button>
                            <button type="reset" class="rounded-xl border border-zinc-700 px-4 py-2 text-xl font-semibold">Limpar</button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('academic.admin.professors.store') }}" data-tab-panel="professor" class="hidden space-y-4">
                        @csrf
                        <h2 class="text-3xl font-semibold">Novo cadastro — Professor</h2>
                        <div>
                            <label class="mb-2 block text-zinc-400">Nome completo</label>
                            <input type="text" name="name" value="{{ old('name') }}" placeholder="ex: Ana Lima" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2" />
                            @error('name', 'professorCreate')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-2 block text-zinc-400">E-mail pessoal (opcional)</label>
                            <input type="email" name="personal_email" value="{{ old('personal_email') }}" placeholder="ex: professor@gmail.com" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2" />
                            @error('personal_email', 'professorCreate')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-2 block text-zinc-400">E-mail institucional</label>
                            <input type="text" value="Gerado automaticamente" disabled class="w-full cursor-not-allowed rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-500" />
                        </div>
                        <div>
                            <label class="mb-2 block text-zinc-400">Senha provisória</label>
                            <input type="text" value="123456" disabled class="w-full cursor-not-allowed rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-500" />
                        </div>
                        <div class="flex gap-3 pt-1">
                            <button type="submit" class="rounded-xl border border-zinc-700 px-4 py-2 text-xl font-semibold hover:border-violet-400">Cadastrar professor</button>
                            <button type="reset" class="rounded-xl border border-zinc-700 px-4 py-2 text-xl font-semibold">Limpar</button>
                        </div>
                    </form>
                </article>

                <article class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                    <div data-tab-panel="student" class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-3xl font-semibold">Alunos cadastrados</h3>
                            <span class="text-sm text-zinc-400">{{ $students->count() }} registros</span>
                        </div>

                        <div>
                            <input type="text" data-student-search placeholder="Buscar por nome ou matrícula..." class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2" />
                        </div>

                        <div class="space-y-2" data-student-list>
                            @forelse ($students as $listedUser)
                                <div class="flex items-center justify-between rounded-xl border border-zinc-800 bg-zinc-950/50 px-3 py-2.5" data-student-item data-search="{{ mb_strtolower($listedUser->name.' '.$listedUser->matricula.' '.$listedUser->email) }}">
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold text-white">{{ $listedUser->name }}</p>
                                        <p class="truncate text-sm text-zinc-400">{{ $listedUser->matricula }} · {{ $listedUser->email }}</p>
                                    </div>
                                    <div class="ml-3 flex items-center gap-2">
                                        <span class="rounded-full bg-emerald-500/20 px-3 py-1 text-sm text-emerald-300">Ativo</span>
                                        <button
                                            type="button"
                                            data-open-edit="student"
                                            data-name="{{ $listedUser->name }}"
                                            data-matricula="{{ $listedUser->matricula }}"
                                            data-email="{{ $listedUser->email }}"
                                            class="rounded-xl border border-zinc-700 px-3 py-1 text-sm font-semibold text-white hover:border-violet-400"
                                        >
                                            Editar
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-xl border border-zinc-800 bg-zinc-950/50 px-3 py-5 text-center text-zinc-400" data-student-empty-base>
                                    Nenhum aluno cadastrado ainda.
                                </div>
                            @endforelse
                            <div class="hidden rounded-xl border border-zinc-800 bg-zinc-950/50 px-3 py-5 text-center text-zinc-400" data-student-empty-search>
                                Nenhum aluno encontrado para a busca.
                            </div>
                        </div>
                    </div>

                    <div data-tab-panel="professor" class="hidden space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-3xl font-semibold">Professores cadastrados</h3>
                            <span class="text-sm text-zinc-400">{{ $professors->count() }} registros</span>
                        </div>

                        <div>
                            <input type="text" data-professor-search placeholder="Buscar por nome ou e-mail..." class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2" />
                        </div>

                        <div class="space-y-2" data-professor-list>
                            @forelse ($professors as $listedUser)
                                <div class="flex items-center justify-between rounded-xl border border-zinc-800 bg-zinc-950/50 px-3 py-2.5" data-professor-item data-search="{{ mb_strtolower($listedUser->name.' '.$listedUser->email) }}">
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold text-white">{{ $listedUser->name }}</p>
                                        <p class="truncate text-sm text-zinc-400">{{ $listedUser->email }}</p>
                                    </div>
                                    <div class="ml-3 flex items-center gap-2">
                                        <span class="rounded-full bg-violet-500/20 px-3 py-1 text-sm text-violet-300">Professor</span>
                                        <button
                                            type="button"
                                            data-open-edit="professor"
                                            data-name="{{ $listedUser->name }}"
                                            data-email="{{ $listedUser->email }}"
                                            class="rounded-xl border border-zinc-700 px-3 py-1 text-sm font-semibold text-white hover:border-violet-400"
                                        >
                                            Editar
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-xl border border-zinc-800 bg-zinc-950/50 px-3 py-5 text-center text-zinc-400" data-professor-empty-base>
                                    Nenhum professor cadastrado ainda.
                                </div>
                            @endforelse
                            <div class="hidden rounded-xl border border-zinc-800 bg-zinc-950/50 px-3 py-5 text-center text-zinc-400" data-professor-empty-search>
                                Nenhum professor encontrado para a busca.
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <div data-edit-modal="student" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 backdrop-blur-sm px-4 py-6">
        <div class="w-full max-w-3xl rounded-2xl border border-zinc-700 bg-zinc-900 p-6 shadow-2xl">
            <div class="flex items-start justify-between">
                <h3 class="text-3xl font-semibold text-white">Editar aluno</h3>
                <button type="button" data-close-modal class="text-zinc-400 hover:text-white">✕</button>
            </div>

            <div class="mt-5 space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm text-zinc-400">Nome completo</label>
                        <input data-edit-student-name type="text" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm text-zinc-400">Matrícula</label>
                        <input data-edit-student-matricula type="text" disabled class="w-full cursor-not-allowed rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-500" />
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm text-zinc-400">E-mail</label>
                    <input data-edit-student-email type="email" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2" />
                </div>

            </div>

            <div class="mt-6 flex flex-wrap gap-2 border-t border-violet-500/20 pt-4">
                <button type="button" class="rounded-xl border border-zinc-700 px-4 py-2 font-semibold text-white">Excluir cadastro</button>
                <button type="button" data-close-modal class="rounded-xl border border-zinc-700 px-4 py-2 font-semibold text-white">Cancelar</button>
                <button type="button" class="rounded-xl border border-zinc-700 px-4 py-2 font-semibold text-white hover:border-violet-400">Salvar alterações</button>
            </div>
        </div>
    </div>

    <div data-edit-modal="professor" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 backdrop-blur-sm px-4 py-6">
        <div class="w-full max-w-3xl rounded-2xl border border-zinc-700 bg-zinc-900 p-6 shadow-2xl">
            <div class="flex items-start justify-between">
                <h3 class="text-3xl font-semibold text-white">Editar professor</h3>
                <button type="button" data-close-modal class="text-zinc-400 hover:text-white">✕</button>
            </div>

            <div class="mt-5 space-y-4">
                <div>
                    <label class="mb-2 block text-sm text-zinc-400">Nome completo</label>
                    <input data-edit-professor-name type="text" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2" />
                </div>

                <div>
                    <label class="mb-2 block text-sm text-zinc-400">E-mail</label>
                    <input data-edit-professor-email type="email" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm text-zinc-400">Departamento</label>
                        <input type="text" value="Não informado" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm text-zinc-400">Titulação</label>
                        <input type="text" value="Não informado" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2" />
                    </div>
                </div>

            </div>

            <div class="mt-6 flex flex-wrap gap-2 border-t border-violet-500/20 pt-4">
                <button type="button" class="rounded-xl border border-zinc-700 px-4 py-2 font-semibold text-white">Excluir cadastro</button>
                <button type="button" data-close-modal class="rounded-xl border border-zinc-700 px-4 py-2 font-semibold text-white">Cancelar</button>
                <button type="button" class="rounded-xl border border-zinc-700 px-4 py-2 font-semibold text-white hover:border-violet-400">Salvar alterações</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-register-tabs]').forEach((tabs) => {
                const triggers = tabs.querySelectorAll('[data-tab-trigger]');
                const panels = tabs.querySelectorAll('[data-tab-panel]');

                const setActiveTab = (tabName) => {
                    triggers.forEach((trigger) => {
                        const active = trigger.dataset.tabTrigger === tabName;
                        trigger.classList.toggle('border-violet-500', active);
                        trigger.classList.toggle('bg-violet-500/20', active);
                        trigger.classList.toggle('text-violet-200', active);
                        trigger.classList.toggle('border-zinc-700', !active);
                        trigger.classList.toggle('text-zinc-300', !active);
                    });

                    panels.forEach((panel) => {
                        panel.classList.toggle('hidden', panel.dataset.tabPanel !== tabName);
                    });
                };

                setActiveTab(tabs.dataset.activeTab || 'student');

                triggers.forEach((trigger) => {
                    trigger.addEventListener('click', () => setActiveTab(trigger.dataset.tabTrigger));
                });
            });

            const studentModal = document.querySelector('[data-edit-modal="student"]');
            const professorModal = document.querySelector('[data-edit-modal="professor"]');

            const closeModal = (modal) => {
                if (!modal) return;
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            };

            const openModal = (modal) => {
                if (!modal) return;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            };

            document.querySelectorAll('[data-close-modal]').forEach((button) => {
                button.addEventListener('click', () => {
                    closeModal(studentModal);
                    closeModal(professorModal);
                });
            });

            studentModal?.addEventListener('click', (event) => {
                if (event.target === studentModal) closeModal(studentModal);
            });

            professorModal?.addEventListener('click', (event) => {
                if (event.target === professorModal) closeModal(professorModal);
            });

            document.querySelectorAll('[data-open-edit="student"]').forEach((button) => {
                button.addEventListener('click', () => {
                    const nameInput = document.querySelector('[data-edit-student-name]');
                    const matriculaInput = document.querySelector('[data-edit-student-matricula]');
                    const emailInput = document.querySelector('[data-edit-student-email]');

                    if (nameInput) nameInput.value = button.dataset.name || '';
                    if (matriculaInput) matriculaInput.value = button.dataset.matricula || '';
                    if (emailInput) emailInput.value = button.dataset.email || '';

                    openModal(studentModal);
                });
            });

            document.querySelectorAll('[data-open-edit="professor"]').forEach((button) => {
                button.addEventListener('click', () => {
                    const nameInput = document.querySelector('[data-edit-professor-name]');
                    const emailInput = document.querySelector('[data-edit-professor-email]');

                    if (nameInput) nameInput.value = button.dataset.name || '';
                    if (emailInput) emailInput.value = button.dataset.email || '';

                    openModal(professorModal);
                });
            });

            const applySearchFilter = ({ inputSelector, itemSelector, emptySearchSelector, emptyBaseSelector }) => {
                const input = document.querySelector(inputSelector);
                const items = document.querySelectorAll(itemSelector);
                const emptySearch = document.querySelector(emptySearchSelector);
                const emptyBase = document.querySelector(emptyBaseSelector);

                if (!input) return;

                const hasItems = items.length > 0;

                input.addEventListener('input', () => {
                    const query = input.value.trim().toLowerCase();
                    let visibleCount = 0;

                    items.forEach((item) => {
                        const searchable = item.dataset.search || '';
                        const visible = searchable.includes(query);
                        item.classList.toggle('hidden', !visible);
                        if (visible) visibleCount += 1;
                    });

                    if (!hasItems) {
                        return;
                    }

                    if (emptyBase) {
                        emptyBase.classList.add('hidden');
                    }

                    if (emptySearch) {
                        emptySearch.classList.toggle('hidden', visibleCount > 0);
                    }
                });
            };

            applySearchFilter({
                inputSelector: '[data-student-search]',
                itemSelector: '[data-student-item]',
                emptySearchSelector: '[data-student-empty-search]',
                emptyBaseSelector: '[data-student-empty-base]',
            });

            applySearchFilter({
                inputSelector: '[data-professor-search]',
                itemSelector: '[data-professor-item]',
                emptySearchSelector: '[data-professor-empty-search]',
                emptyBaseSelector: '[data-professor-empty-base]',
            });
        });
    </script>
</x-layouts.academic>
