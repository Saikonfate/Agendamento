<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acesso negado</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
    <main class="mx-auto flex min-h-screen w-full max-w-3xl items-center px-4 py-8 sm:px-6 lg:px-8">
        <section class="w-full rounded-2xl border border-zinc-800 bg-zinc-900/70 p-6 sm:p-8">
            <p class="text-sm font-semibold tracking-wide text-violet-300">ERRO 403</p>
            <h1 class="mt-2 text-3xl font-semibold sm:text-4xl">Acesso negado</h1>
            <p class="mt-3 text-zinc-400">{{ $message ?? 'Você não tem permissão para acessar esta página.' }}</p>

            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ $targetUrl ?? route('dashboard') }}" class="rounded-xl border border-zinc-700 bg-zinc-900 px-4 py-2.5 text-sm font-semibold hover:border-violet-400">
                    {{ $targetLabel ?? 'Voltar ao painel' }}
                </a>
                <a href="{{ route('academic.auth') }}" class="rounded-xl border border-zinc-700 px-4 py-2.5 text-sm font-semibold text-zinc-300">
                    Ir para acesso
                </a>
            </div>
        </section>
    </main>
</body>
</html>
