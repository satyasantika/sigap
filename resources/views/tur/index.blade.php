<x-tur.susun judul="Tur terpandu">
    <h1 class="text-3xl font-judul font-bold tracking-tight sm:text-4xl">Lihat dulu pekerjaannya, sebelum masuk</h1>
    <p class="mt-4 max-w-2xl text-lg text-[color:var(--tinta-pudar)]">
        Enam tur singkat, satu per peran. Tiap langkah menampilkan satu layar
        sungguhan beserta alasan mengapa layar itu ada. Tidak perlu masuk, dan
        tidak ada data yang tersentuh.
    </p>

    <div class="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($peran as $kode => $p)
            <a href="{{ route('tur.peran', $kode) }}"
               class="group flex flex-col rounded-2xl border border-[color:var(--garis)] bg-white p-6 transition hover:border-[color:var(--zamrud)] hover:shadow-sm">
                <h2 class="font-judul font-semibold group-hover:text-[color:var(--zamrud-tua)]">{{ $p['judul'] }}</h2>
                <p class="mt-1 text-sm text-[color:var(--tinta-pudar)]">{{ $p['ringkas'] }}</p>
                <span class="mt-4 text-sm font-medium text-[color:var(--zamrud-tua)]">
                    {{ count($p['langkah']) }} langkah &rarr;
                </span>
            </a>
        @endforeach
    </div>

    <div class="mt-12 rounded-2xl border-l-4 border-[color:var(--zamrud)] bg-[color:var(--zamrud-muda)] p-6">
        <h2 class="font-judul font-semibold">Tur, manual, dan demo — tiga hal berbeda</h2>
        <p class="mt-2 text-[color:var(--tinta)]">
            <strong>Tur</strong> menjawab "saya harus mulai dari mana": alurnya, berurutan.
            <strong><a class="underline" href="{{ url('/manual/index.html') }}">Manual</a></strong> menjawab
            "layar ini apa": lengkap, bisa dibuka di bagian mana pun.
            <strong>Demo</strong> membiarkan Anda benar-benar mencobanya pada data latihan —
            perlu kode akses dari administrator sistem.
        </p>
    </div>
</x-tur.susun>
