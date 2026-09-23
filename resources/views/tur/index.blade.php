<x-tur.susun judul="Tur terpandu">
    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Lihat dulu pekerjaannya, sebelum masuk</h1>
    <p class="mt-4 max-w-2xl text-lg text-zinc-600 dark:text-zinc-400">
        Enam tur singkat, satu per peran. Tiap langkah menampilkan satu layar
        sungguhan beserta alasan mengapa layar itu ada. Tidak perlu masuk, dan
        tidak ada data yang tersentuh.
    </p>

    <div class="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($peran as $kode => $p)
            <a href="{{ route('tur.peran', $kode) }}"
               class="group flex flex-col rounded-xl border border-zinc-200 bg-white p-6 transition hover:border-emerald-500 hover:shadow-sm dark:border-zinc-800 dark:bg-zinc-950 dark:hover:border-emerald-500">
                <h2 class="font-semibold group-hover:text-emerald-700 dark:group-hover:text-emerald-400">{{ $p['judul'] }}</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $p['ringkas'] }}</p>
                <span class="mt-4 text-sm font-medium text-emerald-700 dark:text-emerald-400">
                    {{ count($p['langkah']) }} langkah &rarr;
                </span>
            </a>
        @endforeach
    </div>

    <div class="mt-12 rounded-xl border-l-4 border-emerald-600 bg-emerald-50 p-6 dark:bg-emerald-500/10">
        <h2 class="font-semibold">Tur, manual, dan demo — tiga hal berbeda</h2>
        <p class="mt-2 text-zinc-700 dark:text-zinc-300">
            <strong>Tur</strong> menjawab "saya harus mulai dari mana": alurnya, berurutan.
            <strong><a class="underline" href="{{ url('/manual') }}">Manual</a></strong> menjawab
            "layar ini apa": lengkap, bisa dibuka di bagian mana pun.
            <strong>Demo</strong> membiarkan Anda benar-benar mencobanya pada data latihan —
            perlu kode akses dari administrator sistem.
        </p>
    </div>
</x-tur.susun>
