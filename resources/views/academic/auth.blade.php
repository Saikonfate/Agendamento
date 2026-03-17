@php
    $title = 'Acesso | Agendamento Acadêmico';
    $role = 'guest';
    $activeTab = old('name') || old('matricula') ? 'register' : 'login';
@endphp

<x-layouts.academic :title="$title" :role="$role">
    <section class="mx-auto flex max-w-2xl flex-col items-center gap-6 pt-8">
        <div class="text-center">
            <div class="mx-auto mb-4 flex size-20 items-center justify-center rounded-full border border-zinc-700 bg-zinc-900 text-zinc-500">logo</div>
            <h1 class="text-4xl font-semibold text-violet-300">Bem-vindo(a)</h1>
            <p class="mt-2 text-zinc-400">Centro Universitário Paraíso — Unifap</p>
        </div>

        <div class="w-full rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5" data-auth-tabs data-active-tab="{{ $activeTab }}">
            <div class="mb-4 flex gap-6 border-b border-zinc-800 pb-3 text-lg">
                <button type="button" data-tab-trigger="login" class="border-b-2 pb-2 {{ $activeTab === 'login' ? 'border-violet-400 text-violet-300' : 'border-transparent text-zinc-400' }}">Entrar</button>
                <button type="button" data-tab-trigger="register" class="border-b-2 pb-2 {{ $activeTab === 'register' ? 'border-violet-400 text-violet-300' : 'border-transparent text-zinc-400' }}">Criar conta</button>
            </div>

            <form method="POST" action="{{ route('login.store') }}" data-tab-panel="login" class="space-y-4 {{ $activeTab === 'login' ? '' : 'hidden' }}">
                @csrf
                <div>
                    <label class="mb-2 block text-sm text-zinc-300">Matrícula / E-mail institucional</label>
                    <input type="text" name="login" value="{{ old('login') }}" placeholder="ex: 20241180203 ou aluno@unifap.edu.br" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2.5 text-zinc-100 outline-none ring-violet-500/40 placeholder:text-zinc-500 focus:ring" />
                    @error('login')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                    @error('email')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm text-zinc-300">Senha</label>
                    <input type="password" name="password" placeholder="••••••••" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2.5 text-zinc-100 outline-none ring-violet-500/40 placeholder:text-zinc-500 focus:ring" />
                    @error('password')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="mt-2 block text-right text-sm text-violet-300">Esqueci minha senha</a>
                    @endif
                </div>
                <label class="flex items-center gap-2 text-sm text-zinc-400">
                    <input type="checkbox" name="remember" value="1" class="rounded border-zinc-600 bg-zinc-950 text-violet-500 focus:ring-violet-500" {{ old('remember') ? 'checked' : '' }}>
                    Lembrar de mim
                </label>
                <button type="submit" class="block w-full rounded-xl border border-zinc-700 bg-zinc-900 px-4 py-2.5 text-center text-2xl font-semibold hover:border-violet-400">Entrar no sistema</button>
                <p class="border-l border-zinc-700 pl-3 text-sm italic text-zinc-500">Redireciona para dashboard do aluno ou painel do atendente conforme perfil</p>
            </form>

            <form method="POST" action="{{ route('register.store') }}" data-tab-panel="register" class="grid grid-cols-1 gap-4 sm:grid-cols-2 {{ $activeTab === 'register' ? '' : 'hidden' }}">
                @csrf
                <div>
                    <label class="mb-2 block text-sm text-zinc-300">Nome completo</label>
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="ex: Gabriel Silva" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2.5 text-zinc-100 outline-none ring-violet-500/40 placeholder:text-zinc-500 focus:ring" />
                    @error('name')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm text-zinc-300">Matrícula</label>
                    <input type="text" name="matricula" value="{{ old('matricula') }}" placeholder="ex: 20241180203" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2.5 text-zinc-100 outline-none ring-violet-500/40 placeholder:text-zinc-500 focus:ring" />
                    @error('matricula')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-2 block text-sm text-zinc-300">E-mail institucional</label>
                    <input type="email" name="email" value="{{ old('email') }}" placeholder="ex: aluno@unifap.edu.br" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2.5 text-zinc-100 outline-none ring-violet-500/40 placeholder:text-zinc-500 focus:ring" />
                    @error('email')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm text-zinc-300">Senha</label>
                    <input type="password" name="password" placeholder="••••••••" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2.5 text-zinc-100 outline-none ring-violet-500/40 placeholder:text-zinc-500 focus:ring" />
                    @error('password')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm text-zinc-300">Confirmar senha</label>
                    <input type="password" name="password_confirmation" placeholder="••••••••" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2.5 text-zinc-100 outline-none ring-violet-500/40 placeholder:text-zinc-500 focus:ring" />
                </div>
                <button type="submit" class="sm:col-span-2 rounded-xl border border-zinc-700 bg-zinc-900 px-4 py-2.5 text-2xl font-semibold hover:border-violet-400">Cadastrar</button>
            </form>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-auth-tabs]').forEach((tabs) => {
                const triggers = tabs.querySelectorAll('[data-tab-trigger]');
                const panels = tabs.querySelectorAll('[data-tab-panel]');

                const setActiveTab = (tabName) => {
                    triggers.forEach((trigger) => {
                        const isActive = trigger.dataset.tabTrigger === tabName;
                        trigger.classList.toggle('border-violet-400', isActive);
                        trigger.classList.toggle('text-violet-300', isActive);
                        trigger.classList.toggle('border-transparent', !isActive);
                        trigger.classList.toggle('text-zinc-400', !isActive);
                    });

                    panels.forEach((panel) => {
                        panel.classList.toggle('hidden', panel.dataset.tabPanel !== tabName);
                    });
                };

                setActiveTab(tabs.dataset.activeTab || 'login');

                triggers.forEach((trigger) => {
                    trigger.addEventListener('click', () => setActiveTab(trigger.dataset.tabTrigger));
                });
            });
        });
    </script>
</x-layouts.academic>
