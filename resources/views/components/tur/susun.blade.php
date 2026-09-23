{{--
    Kerangka bersama halaman tur. Memakai app.css yang sama dengan halaman
    muka, jadi tur terasa satu bagian dengannya, bukan tempelan.
--}}
@props(['judul', 'ringkas' => null])
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $judul }} — Tur {{ \App\Support\Jati::nama() }}</title>
<meta name="robots" content="noindex">
@vite(['resources/css/app.css'])
</head>
<body class="bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">

<header class="sticky top-0 z-20 border-b border-zinc-200 bg-white/85 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/85">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-3">
        <a href="{{ url('/') }}" class="text-lg font-bold tracking-tight">{{ \App\Support\Jati::nama() }}</a>
        <nav class="flex items-center gap-1 text-sm">
            <a href="{{ url('/tur') }}" class="rounded-lg px-3 py-2 font-medium text-emerald-700 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-500/10">Tur</a>
            <a href="{{ url('/manual') }}" class="hidden rounded-lg px-3 py-2 text-zinc-600 hover:bg-zinc-100 sm:block dark:text-zinc-400 dark:hover:bg-zinc-900">Manual</a>
            <a href="{{ url('/panel') }}" class="rounded-lg bg-emerald-600 px-4 py-2 font-semibold text-white hover:bg-emerald-500">Masuk</a>
        </nav>
    </div>
</header>

<main class="mx-auto max-w-6xl px-6 py-12">
    {{ $slot }}
</main>

<footer class="border-t border-zinc-200 dark:border-zinc-800">
    <div class="mx-auto flex max-w-6xl flex-col gap-4 px-6 py-8 text-sm text-zinc-500 sm:flex-row sm:items-center sm:justify-between dark:text-zinc-500">
        <p>{{ \App\Support\Jati::hakCipta() }}. {{ \App\Support\Jati::namaPanjang() }}.</p>
        <p class="flex gap-4">
            <a href="{{ url('/') }}" class="hover:text-emerald-700 dark:hover:text-emerald-400">Beranda</a>
            <a href="{{ url('/manual') }}" class="hover:text-emerald-700 dark:hover:text-emerald-400">Manual</a>
        </p>
    </div>
</footer>
</body>
</html>
