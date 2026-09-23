{{--
    Pintu masuk demo. Dua keadaan dalam satu halaman: sebelum kode diterima
    (formulir kode) dan sesudahnya (pilih peran). Dipisah menjadi dua halaman
    akan membuat orang yang menutup tab kehilangan tempatnya tanpa alasan.
--}}
<x-tur.susun judul="Demo">
    <a class="text-sm text-emerald-700 hover:underline dark:text-emerald-400" href="{{ url('/') }}">&larr; Beranda</a>

    <h1 class="mt-3 text-3xl font-bold tracking-tight">Coba {{ \App\Support\Jati::nama() }} sungguhan</h1>

    @if (session('sigap.pesan'))
        <p class="mt-4 rounded-xl border-l-4 border-emerald-600 bg-emerald-50 p-4 text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-200">
            {{ session('sigap.pesan') }}
        </p>
    @endif

    @if ($simulasi === null)
        <p class="mt-4 max-w-2xl text-lg text-zinc-600 dark:text-zinc-400">
            Demo berjalan di <strong>periode latihan</strong> — salinan kerangka periode
            sungguhan yang bisa dikerjakan bebas lalu dibuang utuh. Tidak ada satu pun data
            akreditasi sungguhan yang bisa Anda lihat atau sentuh dari dalamnya.
        </p>

        <form method="POST" action="{{ route('demo.kode') }}" class="mt-8 max-w-md">
            @csrf
            <label for="kode" class="block text-sm font-medium">Kode demo</label>
            <input id="kode" name="kode" type="text" autocomplete="off" required
                   placeholder="DEMO-XXXXXX" value="{{ old('kode') }}"
                   class="mt-2 w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 font-mono tracking-widest uppercase placeholder:tracking-normal placeholder:normal-case dark:border-zinc-700 dark:bg-zinc-950">

            @error('kode')
                <p class="mt-2 text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
            @enderror

            <button type="submit" class="mt-4 rounded-xl bg-emerald-600 px-6 py-3 font-semibold text-white hover:bg-emerald-500">
                Buka demo
            </button>
        </form>

        <div class="mt-10 max-w-2xl rounded-xl border-l-4 border-amber-500 bg-amber-50 p-6 dark:bg-amber-500/10">
            <h2 class="font-semibold">Belum punya kode?</h2>
            <p class="mt-2 text-zinc-700 dark:text-zinc-300">
                Kode dibuat administrator sistem dan berlaku terbatas. Ia sengaja tidak
                dibagikan terbuka: demo memberi sesi sungguhan di dalam aplikasi, dan
                halaman ini bisa dibuka siapa saja.
            </p>
            <p class="mt-3 text-zinc-700 dark:text-zinc-300">
                Untuk melihat cara kerjanya tanpa kode, ikuti
                <a class="font-medium underline" href="{{ url('/tur') }}">tur terpandu</a> —
                alur tiap peran, langkah demi langkah, dengan tangkapan layar sungguhan.
            </p>
        </div>
    @else
        <p class="mt-4 max-w-2xl text-lg text-zinc-600 dark:text-zinc-400">
            Kode diterima: <strong>{{ $simulasi->nama }}</strong>.
            Berlaku sampai {{ $simulasi->demo_berlaku_sampai->translatedFormat('d F Y, H:i') }}.
            Pilih peran yang ingin Anda coba.
        </p>

        @error('peran')
            <p class="mt-4 rounded-xl border-l-4 border-red-600 bg-red-50 p-4 text-red-900 dark:bg-red-500/10 dark:text-red-200">{{ $message }}</p>
        @enderror

        <div class="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($peran as $p)
                <form method="POST" action="{{ route('demo.masuk') }}">
                    @csrf
                    <input type="hidden" name="peran" value="{{ $p->value }}">
                    <button type="submit"
                            class="group flex h-full w-full flex-col rounded-xl border border-zinc-200 bg-white p-6 text-left transition hover:border-emerald-500 hover:shadow-sm dark:border-zinc-800 dark:bg-zinc-950 dark:hover:border-emerald-500">
                        <span class="font-semibold group-hover:text-emerald-700 dark:group-hover:text-emerald-400">{{ $p->label() }}</span>
                        <span class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $p->ringkas() }}</span>
                        <span class="mt-4 text-sm font-medium text-emerald-700 dark:text-emerald-400">Masuk sebagai ini &rarr;</span>
                    </button>
                </form>
            @endforeach
        </div>

        <div class="mt-10 max-w-3xl rounded-xl border-l-4 border-amber-500 bg-amber-50 p-6 dark:bg-amber-500/10">
            <h2 class="font-semibold">Mengapa tidak ada peran Administrator Sistem</h2>
            <p class="mt-2 text-zinc-700 dark:text-zinc-300">
                Wewenang admin menyentuh pengguna, prodi, dan periode — dan tidak satu pun
                dari itu terikat pada satu periode, jadi tidak ada cara mengurungnya di dalam
                periode latihan. Demo admin akan menyunting data sungguhan. Peran itu
                dipelajari lewat
                <a class="font-medium underline" href="{{ url('/tur/admin') }}">tur terpandu</a>.
            </p>
        </div>
    @endif
</x-tur.susun>
