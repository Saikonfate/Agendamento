@php
    $title = 'Perfil | Atendente';
    $role = 'attendant';
    $user = auth()->user();
    $displayName = $user?->name ?? 'Atendente Secretaria';
    $email = $user?->email ?? 'atendente@unifap.edu.br';
    $photoUrl = $user?->profilePhotoUrl();
@endphp

<x-layouts.academic :title="$title" :role="$role" active="perfil" :userName="$displayName" userInitials="AT" :userPhotoUrl="$photoUrl">
    <section class="rounded-3xl border border-violet-500/20 bg-[#17152f] p-5 shadow-[0_0_0_1px_rgba(99,102,241,0.08)] sm:p-6">
        @if (session('photo_status'))
            <div class="mb-4 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">{{ session('photo_status') }}</div>
        @endif
        @if (session('profile_status'))
            <div class="mb-4 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">{{ session('profile_status') }}</div>
        @endif
        @if (session('password_status'))
            <div class="mb-4 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">{{ session('password_status') }}</div>
        @endif

        <div class="rounded-2xl border border-violet-500/25 bg-[#232144] p-5">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="relative">
                        @if ($photoUrl)
                            <img src="{{ $photoUrl }}" alt="Foto de perfil de {{ $displayName }}" class="size-20 rounded-full object-cover" />
                        @else
                            <div class="flex size-20 items-center justify-center rounded-full bg-violet-500 text-3xl font-bold text-white">
                                AT
                            </div>
                        @endif
                        <span class="absolute bottom-1 right-1 size-3 rounded-full border-2 border-[#232144] bg-emerald-400"></span>
                    </div>
                    <div>
                        <h1 class="text-3xl font-semibold text-white">{{ $displayName }}</h1>
                        <p class="text-zinc-300">Atendente</p>
                        <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-zinc-400">
                            <span class="rounded-full bg-emerald-500/20 px-3 py-1 font-medium text-emerald-300">Em serviço</span>
                        </div>
                    </div>
                </div>
                <form method="POST" action="{{ route('academic.attendant.profile.photo') }}" enctype="multipart/form-data" class="flex flex-col gap-2 sm:items-end">
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
            <article class="rounded-2xl border border-violet-500/20 bg-[#232144] p-5">
                <h2 class="text-2xl font-semibold text-white">Dados profissionais</h2>
                <form method="POST" action="{{ route('academic.attendant.profile.update') }}" class="mt-5 space-y-4">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label class="mb-2 block text-sm font-medium text-zinc-400">Nome completo</label>
                        <input type="text" name="name" value="{{ old('name', $displayName) }}" class="w-full rounded-xl border border-zinc-700 bg-[#2f3040] px-4 py-3 text-lg text-white" />
                        @error('name', 'profileUpdate')<p class="mt-2 text-sm text-rose-300">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-zinc-400">E-mail</label>
                        <input type="email" name="email" value="{{ old('email', $email) }}" class="w-full rounded-xl border border-zinc-700 bg-[#2f3040] px-4 py-3 text-lg text-white" />
                        @error('email', 'profileUpdate')<p class="mt-2 text-sm text-rose-300">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex flex-wrap gap-3 pt-2">
                        <button type="submit" class="rounded-xl border border-zinc-600 px-5 py-2.5 text-xl font-semibold text-white hover:border-violet-400">Salvar alterações</button>
                        <a href="{{ route('academic.attendant.profile') }}" class="rounded-xl border border-zinc-700 px-5 py-2.5 text-xl font-semibold text-zinc-200">Cancelar</a>
                    </div>
                </form>
            </article>

            <article class="rounded-2xl border border-violet-500/20 bg-[#232144] p-5">
                <h2 class="text-2xl font-semibold text-white">Segurança</h2>
                <form method="POST" action="{{ route('academic.attendant.profile.password') }}" class="mt-5 space-y-4">
                    @csrf
                    @method('PATCH')
                    <div class="space-y-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-zinc-400">Senha atual</label>
                            <input type="password" name="current_password" class="w-full rounded-xl border border-zinc-700 bg-[#2f3040] px-4 py-3 text-lg text-white" />
                            @error('current_password', 'passwordUpdate')<p class="mt-2 text-sm text-rose-300">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-zinc-400">Nova senha</label>
                            <input type="password" name="password" class="w-full rounded-xl border border-zinc-700 bg-[#2f3040] px-4 py-3 text-lg text-white" />
                            @error('password', 'passwordUpdate')<p class="mt-2 text-sm text-rose-300">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-zinc-400">Confirmar nova senha</label>
                            <input type="password" name="password_confirmation" class="w-full rounded-xl border border-zinc-700 bg-[#2f3040] px-4 py-3 text-lg text-white" />
                        </div>
                    </div>
                    <div class="pt-4">
                        <button type="submit" class="w-full rounded-xl border border-zinc-600 px-5 py-2.5 text-xl font-semibold text-white hover:border-violet-400">Atualizar senha</button>
                    </div>
                </form>
            </article>
        </div>

        <article class="mt-5 rounded-2xl border border-violet-500/20 bg-[#232144] p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-2xl font-semibold text-white">Atividade recente</h2>
                <a href="{{ route('academic.attendant.dashboard') }}" class="text-sm font-medium text-violet-300 hover:text-violet-200">Ver agenda do dia →</a>
            </div>

            <div class="mt-4 divide-y divide-violet-500/10">
                @foreach ([
                    ['dot' => 'bg-emerald-400', 'title' => 'Atendimento concluído', 'meta' => 'Marcus Vinícius · Trancamento parcial · 08:00', 'status' => 'Realizado', 'statusClass' => 'bg-emerald-500/20 text-emerald-300'],
                    ['dot' => 'bg-blue-400', 'title' => 'Atendimento confirmado', 'meta' => 'Gabriel Silva · Orientação TCC · 09:00', 'status' => 'Confirmado', 'statusClass' => 'bg-blue-500/20 text-blue-300'],
                    ['dot' => 'bg-amber-400', 'title' => 'Atendimento pendente', 'meta' => 'Luiz Gabriel · Histórico escolar · 10:00', 'status' => 'Pendente', 'statusClass' => 'bg-amber-500/20 text-amber-300'],
                    ['dot' => 'bg-zinc-400', 'title' => 'Horário bloqueado', 'meta' => 'Intervalo administrativo · 10:30', 'status' => 'Bloqueado', 'statusClass' => 'bg-zinc-700 text-zinc-200'],
                ] as $item)
                    <div class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-3">
                            <span class="size-2.5 rounded-full {{ $item['dot'] }}"></span>
                            <div>
                                <p class="font-semibold text-white">{{ $item['title'] }}</p>
                                <p class="text-sm text-zinc-400">{{ $item['meta'] }}</p>
                            </div>
                        </div>
                        <span class="inline-flex rounded-full px-3 py-1 text-sm font-medium {{ $item['statusClass'] }}">{{ $item['status'] }}</span>
                    </div>
                @endforeach
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
