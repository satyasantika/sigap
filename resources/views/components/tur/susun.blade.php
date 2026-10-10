{{--
    Kerangka bersama halaman tur. Memakai app.css yang sama dengan halaman
    muka, jadi tur terasa satu bagian dengannya, bukan tempelan.
--}}
@props(['judul', 'ringkas' => null])
<!DOCTYPE html>
<html lang="id" class="scroll-smooth" style="color-scheme: light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $judul }} — Tur {{ \App\Support\Jati::nama() }}</title>
<meta name="robots" content="noindex">
<meta name="color-scheme" content="light">
@include('sigap.font')
@vite(['resources/css/app.css'])
</head>
<body class="latar-kertas font-[Inter,ui-sans-serif,system-ui,sans-serif] text-[color:var(--tinta)] antialiased">

<header class="sticky top-0 z-20 border-b border-[color:var(--garis)] bg-[color:var(--kertas)]/85 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-3">
        <a href="{{ url('/') }}" class="flex items-center gap-2.5">
            <span class="font-judul inline-flex size-9 items-center justify-center rounded-full bg-[color:var(--zamrud)] text-lg font-bold text-white">S</span>
            <span class="font-judul text-xl font-bold">{{ \App\Support\Jati::nama() }}</span>
        </a>
        <nav class="flex items-center gap-1 text-sm">
            <a href="{{ url('/tur') }}" class="rounded-full px-3 py-2 font-medium text-[color:var(--zamrud-tua)] hover:bg-[color:var(--zamrud-muda)]">Tur</a>
            <a href="{{ url('/manual/index.html') }}" class="hidden rounded-full px-3 py-2 text-[color:var(--tinta-pudar)] hover:bg-[color:var(--kertas-dalam)] sm:block">Manual</a>
            <a href="{{ url('/panel') }}" class="ml-1 rounded-full bg-[color:var(--zamrud)] px-5 py-2 font-semibold text-white hover:bg-[color:var(--zamrud-tua)]">Masuk</a>
        </nav>
    </div>
</header>

<main class="mx-auto max-w-6xl px-6 py-12">
    {{ $slot }}
</main>

<footer class="border-t border-[color:var(--garis)]">
    <div class="mx-auto flex max-w-6xl flex-col gap-4 px-6 py-8 text-sm text-[color:var(--tinta-pudar)] sm:flex-row sm:items-center sm:justify-between">
        <p>{{ \App\Support\Jati::hakCipta() }}. {{ \App\Support\Jati::namaPanjang() }}.</p>
        <p class="flex gap-4">
            <a href="{{ url('/') }}" class="hover:text-[color:var(--zamrud-tua)]">Beranda</a>
            <a href="{{ url('/manual/index.html') }}" class="hover:text-[color:var(--zamrud-tua)]">Manual</a>
        </p>
    </div>
</footer>
</body>
</html>
