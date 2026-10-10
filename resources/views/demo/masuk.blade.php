{{--
    Pintu masuk demo. Dua keadaan dalam satu halaman: sebelum kode diterima
    (formulir kode) dan sesudahnya (pilih peran). Dipisah menjadi dua halaman
    akan membuat orang yang menutup tab kehilangan tempatnya tanpa alasan.
--}}
<x-tur.susun judul="Demo">
    <a class="text-sm text-[color:var(--zamrud-tua)] hover:underline" href="{{ url('/') }}">&larr; Beranda</a>

    <h1 class="mt-3 text-3xl font-judul font-bold tracking-tight">Coba {{ \App\Support\Jati::nama() }} sungguhan</h1>

    @if (session('sigap.pesan'))
        <p class="mt-4 rounded-2xl border-l-4 border-[color:var(--zamrud)] bg-[color:var(--zamrud-muda)] p-4 text-[color:var(--zamrud-tua)]">
            {{ session('sigap.pesan') }}
        </p>
    @endif

    @if ($simulasi === null)
        <p class="mt-4 max-w-2xl text-lg text-[color:var(--tinta-pudar)]">
            Demo berjalan di <strong>periode latihan</strong> — salinan kerangka periode
            sungguhan yang bisa dikerjakan bebas lalu dibuang utuh. Tidak ada satu pun data
            akreditasi sungguhan yang bisa Anda lihat atau sentuh dari dalamnya.
        </p>

        <form method="POST" action="{{ route('demo.kode') }}" class="mt-8 max-w-md">
            @csrf
            <label for="kode" class="block text-sm font-medium">Kode demo</label>
            <input id="kode" name="kode" type="text" autocomplete="off" required
                   placeholder="DEMO-XXXXXX" value="{{ old('kode') }}"
                   class="mt-2 w-full rounded-2xl border border-[color:var(--garis)] bg-white px-4 py-3 font-mono tracking-widest uppercase placeholder:tracking-normal placeholder:normal-case">

            @error('kode')
                <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
            @enderror

            <button type="submit" class="mt-4 rounded-full bg-[color:var(--zamrud)] px-6 py-3 font-semibold text-white hover:bg-[color:var(--zamrud-tua)]">
                Buka demo
            </button>
        </form>

        <div class="mt-10 max-w-2xl rounded-2xl border-l-4 border-[color:var(--emas)]/40 bg-[color:var(--emas-muda)] p-6">
            <h2 class="font-judul font-semibold">Belum punya kode?</h2>
            <p class="mt-2 text-[color:var(--tinta)]">
                Kode dibuat administrator sistem dan berlaku terbatas. Ia sengaja tidak
                dibagikan terbuka: demo memberi sesi sungguhan di dalam aplikasi, dan
                halaman ini bisa dibuka siapa saja.
            </p>
            <p class="mt-3 text-[color:var(--tinta)]">
                Untuk melihat cara kerjanya tanpa kode, ikuti
                <a class="font-medium underline" href="{{ url('/tur') }}">tur terpandu</a> —
                alur tiap peran, langkah demi langkah, dengan tangkapan layar sungguhan.
            </p>
        </div>
    @else
        <p class="mt-4 max-w-2xl text-lg text-[color:var(--tinta-pudar)]">
            Kode diterima: <strong>{{ $simulasi->nama }}</strong>.
            Berlaku sampai {{ $simulasi->demo_berlaku_sampai->translatedFormat('d F Y, H:i') }}.
            Pilih peran yang ingin Anda coba.
        </p>

        @error('peran')
            <p class="mt-4 rounded-2xl border-l-4 border-red-600 bg-red-50 p-4 text-red-900">{{ $message }}</p>
        @enderror

        <div class="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($peran as $p)
                <form method="POST" action="{{ route('demo.masuk') }}">
                    @csrf
                    <input type="hidden" name="peran" value="{{ $p->value }}">
                    <button type="submit"
                            class="group flex h-full w-full flex-col rounded-2xl border border-[color:var(--garis)] bg-white p-6 text-left transition hover:border-[color:var(--zamrud)] hover:shadow-sm">
                        <span class="font-semibold group-hover:text-[color:var(--zamrud-tua)]">{{ $p->label() }}</span>
                        <span class="mt-1 text-sm text-[color:var(--tinta-pudar)]">{{ $p->ringkas() }}</span>
                        <span class="mt-4 text-sm font-medium text-[color:var(--zamrud-tua)]">Masuk sebagai ini &rarr;</span>
                    </button>
                </form>
            @endforeach
        </div>

        <div class="mt-10 max-w-3xl rounded-2xl border-l-4 border-[color:var(--emas)]/40 bg-[color:var(--emas-muda)] p-6">
            <h2 class="font-judul font-semibold">Mengapa tidak ada peran Administrator Sistem</h2>
            <p class="mt-2 text-[color:var(--tinta)]">
                Wewenang admin menyentuh pengguna, prodi, dan periode — dan tidak satu pun
                dari itu terikat pada satu periode, jadi tidak ada cara mengurungnya di dalam
                periode latihan. Demo admin akan menyunting data sungguhan. Peran itu
                dipelajari lewat
                <a class="font-medium underline" href="{{ url('/tur/admin') }}">tur terpandu</a>.
            </p>
        </div>
    @endif
</x-tur.susun>
