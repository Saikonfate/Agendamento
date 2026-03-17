<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Agendamento Acadêmico' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
    @php
        $currentRole = $role ?? 'guest';
        $roleLabel = $currentRole === 'admin'
            ? 'Admin'
            : ($currentRole === 'professor' ? 'Professor' : ($currentRole === 'student' ? 'Aluno' : 'Visitante'));
        $profileUrl = $currentRole === 'student'
            ? route('academic.student.profile')
            : ($currentRole === 'admin' ? route('academic.admin.profile') : ($currentRole === 'professor' ? route('academic.professor.profile') : null));
        $cleanName = preg_replace('/[^\pL\s]/u', ' ', $userName ?? 'Usuário');
        $nameParts = array_values(array_filter(preg_split('/\s+/u', trim((string) $cleanName))));
        $computedInitials = collect($nameParts)
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
        $authUser = auth()->user();
        $profilePhotoUrl = $userPhotoUrl ?? $authUser?->profilePhotoUrl();
        $avatarInitials = $userInitials
            ?? ($currentRole === 'admin'
                ? 'AD'
                : ($computedInitials !== '' ? $computedInitials : 'US'));

        $flashMessages = collect([
            session('status'),
            session('profile_status'),
            session('password_status'),
            session('photo_status'),
        ])->filter()->values();
    @endphp

    @if ($flashMessages->isNotEmpty())
        <div class="fixed right-4 top-4 z-50 flex w-[min(92vw,26rem)] flex-col gap-2">
            @foreach ($flashMessages as $message)
                <div data-flash-message class="ui-flash rounded-xl border border-emerald-500/40 bg-emerald-500/12 px-4 py-3 text-sm text-emerald-200 shadow-lg shadow-black/20">
                    {{ $message }}
                </div>
            @endforeach
        </div>
    @endif

    <header class="sticky top-0 z-20 border-b border-emerald-500/30 bg-zinc-200/95 backdrop-blur">
        <div class="mx-auto flex w-full max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
            <div class="flex items-center gap-6">
                <a href="{{ route('academic.auth') }}" class="rounded-md border border-violet-500 px-3 py-1 text-sm font-semibold tracking-wide text-white bg-violet-500">UNIFAP</a>

                @if ($currentRole === 'student')
                    <nav class="hidden items-center gap-6 text-sm text-white/50 md:flex">
                        <a href="{{ route('academic.student.dashboard') }}" class="border-b-2 pb-0.5 {{ ($active ?? '') === 'inicio' ? 'border-violet-300 text-violet-300' : 'border-transparent text-white/50 hover:text-white' }}">Início</a>
                        <a href="{{ route('academic.student.new') }}" class="border-b-2 pb-0.5 {{ ($active ?? '') === 'agendar' ? 'border-violet-300 text-violet-300' : 'border-transparent text-white/50 hover:text-white' }}">Agendar</a>
                        <a href="{{ route('academic.student.mine') }}" class="border-b-2 pb-0.5 {{ ($active ?? '') === 'meus' ? 'border-violet-300 text-violet-300' : 'border-transparent text-white/50 hover:text-white' }}">Meus agendamentos</a>
                        <a href="{{ route('academic.student.profile') }}" class="border-b-2 pb-0.5 {{ ($active ?? '') === 'perfil' ? 'border-violet-300 text-violet-300' : 'border-transparent text-white/50 hover:text-white' }}">Perfil</a>
                    </nav>
                @elseif ($currentRole === 'admin')
                    <nav class="hidden items-center gap-6 text-sm text-white/50 md:flex">
                        <a href="{{ route('academic.admin.dashboard') }}" class="border-b-2 pb-0.5 {{ ($active ?? '') === 'agenda' ? 'border-violet-300 text-violet-300' : 'border-transparent text-white/50 hover:text-white' }}">Agenda do dia</a>
                        <a href="{{ route('academic.admin.schedule') }}" class="border-b-2 pb-0.5 {{ ($active ?? '') === 'horarios' ? 'border-violet-300 text-violet-300' : 'border-transparent text-white/50 hover:text-white' }}">Gerenciar horários</a>
                        <a href="{{ route('academic.admin.users') }}" class="border-b-2 pb-0.5 {{ ($active ?? '') === 'cadastros' ? 'border-violet-300 text-violet-300' : 'border-transparent text-white/50 hover:text-white' }}">Cadastros</a>
                        <a href="{{ route('academic.admin.profile') }}" class="border-b-2 pb-0.5 {{ ($active ?? '') === 'perfil' ? 'border-violet-300 text-violet-300' : 'border-transparent text-white/50 hover:text-white' }}">Perfil</a>
                    </nav>
                @elseif ($currentRole === 'professor')
                    <nav class="hidden items-center gap-6 text-sm text-white/50 md:flex">
                        <a href="{{ route('academic.professor.dashboard') }}" class="border-b-2 pb-0.5 {{ ($active ?? '') === 'inicio' ? 'border-violet-300 text-violet-300' : 'border-transparent text-white/50 hover:text-white' }}">Início</a>
                        <a href="{{ route('academic.professor.profile') }}" class="border-b-2 pb-0.5 {{ ($active ?? '') === 'perfil' ? 'border-violet-300 text-violet-300' : 'border-transparent text-white/50 hover:text-white' }}">Perfil</a>
                    </nav>
                @else
                    <p class="hidden text-sm text-zinc-400 md:block">Sistema de Agendamento Acadêmico</p>
                @endif
            </div>

            <div class="flex items-center gap-3 text-sm text-white/90">
                @if ($currentRole !== 'guest')
                    <span class="hidden rounded-full border border-zinc-700 bg-zinc-900 px-3 py-1 text-xs font-semibold text-zinc-300 sm:inline-flex">
                        Perfil: {{ $roleLabel }}
                    </span>
                    @if ($profileUrl)
                        <a href="{{ $profileUrl }}" class="flex items-center gap-3 rounded-full px-1 py-1 transition-colors hover:bg-zinc-900/70">
                            @if ($profilePhotoUrl)
                                <img src="{{ $profilePhotoUrl }}" alt="Foto de perfil" class="size-8 rounded-full border border-zinc-700 object-cover" />
                            @else
                                <div class="flex size-8 items-center justify-center rounded-full bg-zinc-800 font-semibold text-zinc-200">
                                    {{ $avatarInitials }}
                                </div>
                            @endif
                            <span class="hidden sm:block">{{ $userName ?? 'Usuário' }}</span>
                        </a>
                    @else
                        @if ($profilePhotoUrl)
                            <img src="{{ $profilePhotoUrl }}" alt="Foto de perfil" class="size-8 rounded-full border border-zinc-700 object-cover" />
                        @else
                            <div class="flex size-8 items-center justify-center rounded-full bg-zinc-800 font-semibold text-zinc-200">
                                {{ $avatarInitials }}
                            </div>
                        @endif
                        <span class="hidden sm:block">{{ $userName ?? 'Usuário' }}</span>
                    @endif
                @endif
            </div>
        </div>

        @if ($currentRole === 'student')
            <nav class="border-t border-emerald-500/30 px-4 py-2 text-sm text-white/50 md:hidden sm:px-6 lg:px-8">
                <div class="mx-auto flex w-full max-w-7xl items-center gap-4 overflow-x-auto whitespace-nowrap">
                    <a href="{{ route('academic.student.dashboard') }}" class="border-b-2 pb-0.5 {{ ($active ?? '') === 'inicio' ? 'border-violet-300 text-violet-300' : 'border-transparent text-white/50 hover:text-white' }}">Início</a>
                    <a href="{{ route('academic.student.new') }}" class="border-b-2 pb-0.5 {{ ($active ?? '') === 'agendar' ? 'border-violet-300 text-violet-300' : 'border-transparent text-white/50 hover:text-white' }}">Agendar</a>
                    <a href="{{ route('academic.student.mine') }}" class="border-b-2 pb-0.5 {{ ($active ?? '') === 'meus' ? 'border-violet-300 text-violet-300' : 'border-transparent text-white/50 hover:text-white' }}">Meus agendamentos</a>
                    <a href="{{ route('academic.student.profile') }}" class="border-b-2 pb-0.5 {{ ($active ?? '') === 'perfil' ? 'border-violet-300 text-violet-300' : 'border-transparent text-white/50 hover:text-white' }}">Perfil</a>
                    <span class="ml-auto rounded-full border border-zinc-700 bg-zinc-900 px-3 py-1 text-xs font-semibold text-zinc-300">Perfil: {{ $roleLabel }}</span>
                </div>
            </nav>
        @elseif ($currentRole === 'admin')
            <nav class="border-t border-emerald-500/30 px-4 py-2 text-sm text-white/50 md:hidden sm:px-6 lg:px-8">
                <div class="mx-auto flex w-full max-w-7xl items-center gap-4 overflow-x-auto whitespace-nowrap">
                    <a href="{{ route('academic.admin.dashboard') }}" class="border-b-2 pb-0.5 {{ ($active ?? '') === 'agenda' ? 'border-violet-300 text-violet-300' : 'border-transparent text-white/50 hover:text-white' }}">Agenda do dia</a>
                    <a href="{{ route('academic.admin.schedule') }}" class="border-b-2 pb-0.5 {{ ($active ?? '') === 'horarios' ? 'border-violet-300 text-violet-300' : 'border-transparent text-white/50 hover:text-white' }}">Gerenciar horários</a>
                    <a href="{{ route('academic.admin.users') }}" class="border-b-2 pb-0.5 {{ ($active ?? '') === 'cadastros' ? 'border-violet-300 text-violet-300' : 'border-transparent text-white/50 hover:text-white' }}">Cadastros</a>
                    <a href="{{ route('academic.admin.profile') }}" class="border-b-2 pb-0.5 {{ ($active ?? '') === 'perfil' ? 'border-violet-300 text-violet-300' : 'border-transparent text-white/50 hover:text-white' }}">Perfil</a>
                    <span class="ml-auto rounded-full border border-zinc-700 bg-zinc-900 px-3 py-1 text-xs font-semibold text-zinc-300">Perfil: {{ $roleLabel }}</span>
                </div>
            </nav>
        @elseif ($currentRole === 'professor')
            <nav class="border-t border-emerald-500/30 px-4 py-2 text-sm text-white/50 md:hidden sm:px-6 lg:px-8">
                <div class="mx-auto flex w-full max-w-7xl items-center gap-4 overflow-x-auto whitespace-nowrap">
                    <a href="{{ route('academic.professor.dashboard') }}" class="border-b-2 pb-0.5 {{ ($active ?? '') === 'inicio' ? 'border-violet-300 text-violet-300' : 'border-transparent text-white/50 hover:text-white' }}">Início</a>
                    <a href="{{ route('academic.professor.profile') }}" class="border-b-2 pb-0.5 {{ ($active ?? '') === 'perfil' ? 'border-violet-300 text-violet-300' : 'border-transparent text-white/50 hover:text-white' }}">Perfil</a>
                    <span class="ml-auto rounded-full border border-zinc-700 bg-zinc-900 px-3 py-1 text-xs font-semibold text-zinc-300">Perfil: {{ $roleLabel }}</span>
                </div>
            </nav>
        @endif
    </header>

    <main class="ui-page-enter mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        {{ $slot }}
    </main>
</body>
</html>
