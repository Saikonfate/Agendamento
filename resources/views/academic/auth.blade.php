@php
    $title = 'Acesso | Agendamento Acadêmico';
    $role = 'guest';
@endphp

<x-layouts.academic :title="$title" :role="$role">
    <section class="mx-auto flex max-w-2xl flex-col items-center gap-6 pt-8">
        <div class="text-center">
            <div class="mx-auto mb-4 flex size-20 items-center justify-center rounded-full border border-zinc-700 bg-zinc-900 text-zinc-500">logo</div>
            <h1 class="text-4xl font-semibold text-violet-300">Bem-vindo(a)</h1>
            <p class="mt-2 text-zinc-400">Centro Universitário Paraíso — Unifap</p>
        </div>

        <div class="w-full rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
            <div class="mb-4 border-b border-zinc-800 pb-3 text-lg">
                <p class="border-b-2 border-violet-400 pb-2 text-violet-300">Entrar</p>
            </div>

            <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="mb-2 block text-sm text-zinc-300">Matrícula / E-mail institucional</label>
                    <input type="text" name="login" value="{{ old('login') }}" placeholder="Digite sua matrícula ou e-mail institucional" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2.5 text-zinc-100 outline-none ring-violet-500/40 placeholder:text-zinc-500 focus:ring" />
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
                <p class="border-l border-zinc-700 pl-3 text-sm italic text-zinc-500">Redireciona para dashboard do aluno, professor ou admin conforme perfil</p>
                <p class="border-l border-zinc-700 pl-3 text-sm italic text-zinc-500">O cadastro de novos alunos é realizado apenas pelo admin.</p>
            </form>
        </div>
    </section>
</x-layouts.academic>
