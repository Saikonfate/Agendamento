@php
    $title = 'Perfil | Aluno';
    $role = 'student';
    $user = auth()->user();
    $displayName = $user?->name ?? 'Aluno';
    $institutionalEmail = $user?->email ?? '';
    $personalEmail = $user?->personal_email ?? '';
    $matricula = $user?->matricula ?? '';
    $initials = str($displayName)->explode(' ')->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $photoUrl = $user?->profilePhotoUrl();
    $recentActivities = collect($recentActivities ?? []);
@endphp

<x-layouts.academic :title="$title" :role="$role" active="perfil" :userName="$displayName" :userInitials="$initials" :userPhotoUrl="$photoUrl">
    <section class="rounded-3xl border border-violet-500/20 bg-zinc-900 p-5 shadow-[0_0_0_1px_rgba(99,102,241,0.08)] sm:p-6">
        @if (session('photo_status'))
            <div class="mb-4 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">{{ session('photo_status') }}</div>
        @endif
        @if (session('profile_status'))
            <div class="mb-4 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">{{ session('profile_status') }}</div>
        @endif
        @if (session('password_status'))
            <div class="mb-4 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">{{ session('password_status') }}</div>
        @endif

        <div class="rounded-2xl border border-violet-500/25 bg-zinc-800/80 p-5">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="relative">
                        @if ($photoUrl)
                            <img src="{{ $photoUrl }}" alt="Foto de perfil de {{ $displayName }}" class="size-20 rounded-full object-cover" />
                        @else
                            <div class="flex size-20 items-center justify-center rounded-full bg-violet-500 text-3xl font-bold text-white">
                                {{ $initials }}
                            </div>
                        @endif
                        <span class="absolute bottom-1 right-1 size-3 rounded-full border-2 border-zinc-800 bg-emerald-400"></span>
                    </div>
                    <div>
                        <h1 class="text-3xl font-semibold text-zinc-200">{{ $displayName }}</h1>
                        <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-zinc-400">
                            <span class="rounded-full bg-emerald-500/20 px-3 py-1 font-medium text-emerald-300">Ativo</span>
                            <span>Matrícula: {{ $matricula }}</span>
                        </div>
                    </div>
                </div>
                <form method="POST" action="{{ route('academic.student.profile.photo') }}" enctype="multipart/form-data" class="flex flex-col gap-2 sm:items-end">
                    @csrf
                    <label class="cursor-pointer rounded-xl border border-zinc-600 px-4 py-2 font-semibold text-white hover:border-violet-400">
                        <span>Alterar foto</span>
                        <input type="file" name="photo" accept="image/png,image/jpeg,image/webp" class="hidden" onchange="this.form.submit()">
                    </label>
                    @error('photo', 'photoUpdate')<p class="text-sm text-rose-300">{{ $message }}</p>@enderror
                    <p class="text-xs text-zinc-500">PNG, JPG ou WEBP até 2MB</p>
                </form>
            </div>
        </div>

        <div class="mt-5 grid gap-5 lg:grid-cols-[1.2fr_0.85fr]">
            <article class="rounded-2xl border border-violet-500/20 bg-zinc-800/80 p-5">
                <h2 class="text-2xl font-semibold text-zinc-200">Dados pessoais</h2>
                <form method="POST" action="{{ route('academic.student.profile.update') }}" class="mt-5 space-y-4">
                    @csrf
                    @method('PATCH')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-zinc-400">Nome completo</label>
                            <input type="text" name="name" value="{{ old('name', $displayName) }}" class="w-full rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-3 text-lg text-zinc-200" />
                            @error('name', 'profileUpdate')<p class="mt-2 text-sm text-rose-300">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-zinc-400">Matrícula</label>
                            <input type="text" value="{{ $matricula }}" disabled class="w-full rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-3 text-lg text-zinc-500 cursor-not-allowed" />
                            <p class="mt-1 text-xs text-zinc-500">A matrícula não pode ser alterada.</p>
                        </div>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-zinc-400">E-mail institucional</label>
                        <input type="email" value="{{ $institutionalEmail }}" disabled class="w-full rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-3 text-lg text-zinc-500 cursor-not-allowed" />
                        <p class="mt-1 text-xs text-zinc-500">O e-mail institucional não pode ser alterado.</p>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-zinc-400">E-mail pessoal</label>
                        <input type="email" name="personal_email" value="{{ old('personal_email', $personalEmail) }}" placeholder="Digite seu e-mail pessoal" class="w-full rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-3 text-lg text-zinc-200" />
                        @error('personal_email', 'profileUpdate')<p class="mt-2 text-sm text-rose-300">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex flex-wrap gap-3 pt-2">
                        <button type="submit" class="rounded-xl border border-zinc-600 px-5 py-2.5 text-xl font-semibold text-white hover:border-violet-400">Salvar alterações</button>
                        <a href="{{ route('academic.student.profile') }}" class="rounded-xl border border-zinc-700 px-5 py-2.5 text-xl font-semibold text-zinc-200">Cancelar</a>
                    </div>
                </form>
            </article>

            <article class="rounded-2xl border border-violet-500/20 bg-zinc-800/80 p-5">
                <h2 class="text-2xl font-semibold text-zinc-200">Segurança</h2>
                <form method="POST" action="{{ route('academic.student.profile.password') }}" class="mt-5 space-y-4">
                    @csrf
                    @method('PATCH')
                    <div class="space-y-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-zinc-400">Senha atual</label>
                            <input type="password" name="current_password" class="w-full rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-3 text-lg text-zinc-200" />
                            @error('current_password', 'passwordUpdate')<p class="mt-2 text-sm text-rose-300">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-zinc-400">Nova senha</label>
                            <input type="password" name="password" class="w-full rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-3 text-lg text-zinc-200" />
                            @error('password', 'passwordUpdate')<p class="mt-2 text-sm text-rose-300">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-zinc-400">Confirmar nova senha</label>
                            <input type="password" name="password_confirmation" class="w-full rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-3 text-lg text-zinc-200" />
                        </div>
                    </div>
                    <div class="pt-4">
                        <button type="submit" class="w-full rounded-xl border border-zinc-600 px-5 py-2.5 text-xl font-semibold text-white hover:border-violet-400">Atualizar senha</button>
                    </div>
                </form>
            </article>
        </div>

        <article class="mt-5 rounded-2xl border border-violet-500/20 bg-zinc-800/80 p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-2xl font-semibold text-zinc-200">Atividade recente</h2>
                <a href="{{ route('academic.student.mine') }}" class="text-sm font-medium text-violet-300 hover:text-violet-300/90">Ver todos os agendamentos →</a>
            </div>

            <div class="mt-4 divide-y divide-violet-500/10">
                @forelse ($recentActivities as $item)
                    <div class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-3">
                            <span class="size-2.5 rounded-full {{ $item['dot'] }}"></span>
                            <div>
                                <p class="font-semibold text-zinc-200">{{ $item['title'] }}</p>
                                <p class="text-sm text-zinc-400">{{ $item['meta'] }}</p>
                            </div>
                        </div>
                        <span class="inline-flex rounded-full px-3 py-1 text-sm font-medium {{ $item['statusClass'] }}">{{ $item['status'] }}</span>
                    </div>
                @empty
                    <p class="py-4 text-sm text-zinc-400">Nenhuma atividade recente encontrada.</p>
                @endforelse
            </div>
        </article>

        <div class="mt-5 flex justify-end">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-xl border border-rose-500/40 px-5 py-2.5 text-lg font-medium text-rose-300 hover:border-rose-400 hover:text-rose-200">Sair da conta</button>
            </form>
        </div>
    </section>
</x-layouts.academic>
