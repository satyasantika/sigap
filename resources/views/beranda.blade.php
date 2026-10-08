{{--
    Halaman muka SIGAP.

    Terbuka tanpa masuk, jadi aturannya keras: tidak ada satu pun angka
    akreditasi di sini. Yang tampil hanya bentuk instrumennya, dan itu terbit
    untuk umum lewat Peraturan LAMDIK Nomor 5 Tahun 2025.

    Gambarnya diambil dari manual — tangkapan layar sungguhan dengan data
    DemoSeeder, bukan mockup. Bila tampilan sistem berubah, jalankan
    tools/tangkap-layar.py dan halaman ini ikut benar dengan sendirinya.
--}}
<!DOCTYPE html>
<html lang="id" class="scroll-smooth" style="color-scheme: light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ \App\Support\Jati::nama() }} — {{ \App\Support\Jati::namaPanjang() }}</title>
<meta name="description" content="Aplikasi internal pengumpulan bukti akreditasi LAMDIK untuk Program Studi Pendidikan Profesi Guru.">
<meta name="robots" content="noindex">
<meta name="color-scheme" content="light">
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
@vite(['resources/css/app.css'])
</head>
<body class="bg-zinc-50 font-[Inter,ui-sans-serif,system-ui,sans-serif] text-zinc-900 antialiased">

{{-- ------------------------------------------------------------------ --}}
{{-- Bilah atas                                                          --}}
{{-- ------------------------------------------------------------------ --}}
<header class="sticky top-0 z-20 border-b border-zinc-950/5 bg-white/90 shadow-sm backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-3">
        <a href="#" class="flex items-center gap-2 text-lg font-bold tracking-tight">
            <span class="inline-flex size-8 items-center justify-center rounded-lg bg-emerald-600 text-sm font-bold text-white">S</span>
            {{ \App\Support\Jati::nama() }}
        </a>

        <nav class="flex items-center gap-1 text-sm">
            <a href="#cara-kerja" class="hidden rounded-lg px-3 py-2 text-zinc-600 hover:bg-zinc-100 sm:block">Cara kerja</a>
            <a href="#peran" class="hidden rounded-lg px-3 py-2 text-zinc-600 hover:bg-zinc-100 sm:block">Peran</a>
            <a href="{{ url('/manual') }}" class="rounded-lg px-3 py-2 font-medium text-emerald-700 hover:bg-emerald-50">Manual</a>
            <a href="{{ url('/panel') }}" class="rounded-lg bg-emerald-600 px-4 py-2 font-semibold text-white hover:bg-emerald-500">Masuk</a>
        </nav>
    </div>
</header>

{{-- ------------------------------------------------------------------ --}}
{{-- Kepala                                                              --}}
{{-- ------------------------------------------------------------------ --}}
<section class="mx-auto max-w-6xl px-6 pt-16 pb-12 sm:pt-24">
    <p class="mb-4 inline-block rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold tracking-wide text-emerald-800 uppercase">
        Aplikasi internal · Prodi PPG
    </p>

    <h1 class="max-w-3xl text-4xl leading-[1.1] font-bold tracking-tight text-balance sm:text-5xl">
        Satu tempat untuk mengumpulkan bukti akreditasi, bukan satu folder yang
        dicari saat asesor sudah datang.
    </h1>

    <p class="mt-6 max-w-2xl text-lg text-zinc-600">
        {{ \App\Support\Jati::nama() }} memecah instrumen LAMDIK menjadi pekerjaan
        yang bisa ditugaskan, ditagih, dan diperiksa — lalu memperlihatkan posisinya
        setiap hari, bukan hanya di rapat terakhir.
    </p>

    <div class="mt-8 flex flex-wrap gap-3">
        <a href="{{ url('/panel') }}" class="rounded-xl bg-emerald-600 px-6 py-3 font-semibold text-white hover:bg-emerald-500">
            Masuk ke {{ \App\Support\Jati::nama() }}
        </a>
        <a href="{{ url('/manual') }}" class="rounded-xl border border-zinc-300 px-6 py-3 font-semibold hover:bg-zinc-50">
            Baca manual pengguna
        </a>
    </div>

    <p class="mt-4 text-sm text-zinc-500">
        Tidak ada pendaftaran mandiri. Akun dibuat administrator sistem.
    </p>

    <figure class="mt-14">
        <img src="{{ url('/manual/gambar/ketua/01-dasbor.png') }}" alt="Dasbor SIGAP" loading="lazy"
             class="w-full rounded-2xl border border-zinc-950/5 shadow-sm">
        <figcaption class="mt-3 text-sm text-zinc-500">
            Dasbor ketua task force. Tangkapan layar sungguhan dari sistem ini, dengan data contoh.
        </figcaption>
    </figure>
</section>

{{-- ------------------------------------------------------------------ --}}
{{-- Angka instrumen                                                     --}}
{{-- ------------------------------------------------------------------ --}}
<section class="border-y border-zinc-950/5 bg-white">
    <div class="mx-auto max-w-6xl px-6 py-14">
        <h2 class="text-2xl font-bold tracking-tight">Bentuk instrumennya</h2>
        <p class="mt-2 max-w-2xl text-zinc-600">
            Angka di bawah bukan capaian, melainkan ukuran pekerjaannya. Seluruhnya
            dibaca dari berkas yang sama dengan yang dipakai penyemai basis data,
            jadi halaman ini tidak bisa menyimpang dari isi sistemnya.
        </p>

        <dl class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($angka as $a)
                <div class="rounded-xl bg-zinc-50 p-5 shadow-sm ring-1 ring-zinc-950/5">
                    <dt class="text-3xl font-bold tracking-tight text-emerald-700">{{ $a['nilai'] }}</dt>
                    <dd class="mt-1 font-medium">{{ $a['label'] }}</dd>
                    <dd class="mt-1 text-sm text-zinc-600">{{ $a['catatan'] }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>

{{-- ------------------------------------------------------------------ --}}
{{-- Cara kerja                                                          --}}
{{-- ------------------------------------------------------------------ --}}
<section id="cara-kerja" class="mx-auto max-w-6xl px-6 py-16">
    <h2 class="text-2xl font-bold tracking-tight">Cara kerjanya</h2>

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        <article class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-zinc-950/5">
            <p class="text-xs font-semibold tracking-wider text-zinc-500 uppercase">Langkah 1</p>
            <h3 class="mt-2 text-lg font-semibold">Instrumen dipecah jadi tagihan</h3>
            <p class="mt-2 text-zinc-600">
                Setiap elemen melahirkan tagihan narasi, bukti, dan isian DKPS —
                masing-masing membawa bobotnya sendiri. Tagihan punya penanggung
                jawab dan tenggat, jadi "siapa mengerjakan apa" tidak lagi
                bergantung pada ingatan.
            </p>
        </article>

        <article class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-zinc-950/5">
            <p class="text-xs font-semibold tracking-wider text-zinc-500 uppercase">Langkah 2</p>
            <h3 class="mt-2 text-lg font-semibold">Bukti diperiksa dua kali</h3>
            <p class="mt-2 text-zinc-600">
                <strong>Keterbacaan</strong> diperiksa mesin: tautan dibuka tanpa
                kredensial apa pun, persis seperti asesor akan membukanya.
                <strong>Keabsahan</strong> dinilai manusia — tautan bisa terbuka lebar
                tetapi berisi dokumen yang keliru. Keduanya harus hijau sebelum tagihan
                bisa disetujui.
            </p>
        </article>

        <article class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-zinc-950/5">
            <p class="text-xs font-semibold tracking-wider text-zinc-500 uppercase">Langkah 3</p>
            <h3 class="mt-2 text-lg font-semibold">Posisinya terlihat setiap hari</h3>
            <p class="mt-2 text-zinc-600">
                Progres dihitung dari bobot, tidak pernah dari cacah tagihan. Dua
                puluh tagihan ringan yang selesai tidak menggeser angka sebanyak satu
                elemen berbobot besar — dan dasbor mengatakannya apa adanya.
            </p>
        </article>
    </div>

    <div class="mt-10 rounded-xl border-l-4 border-amber-500 bg-amber-50 p-6">
        <h3 class="font-semibold">Nilai tinggi belum berarti Unggul</h3>
        <p class="mt-2 text-zinc-700">
            Lima syarat perlu adalah gerbang terpisah dari nilai. Nilai Akreditasi
            tertinggi sekalipun tetap berujung "Terakreditasi" bila salah satu dari
            kelimanya belum terpenuhi. Karena itu dasbor tidak pernah menampilkan
            progres tanpa menampilkan gerbangnya.
        </p>
    </div>
</section>

{{-- ------------------------------------------------------------------ --}}
{{-- Peran dan manual                                                    --}}
{{-- ------------------------------------------------------------------ --}}
<section id="peran" class="border-y border-zinc-950/5 bg-white">
    <div class="mx-auto max-w-6xl px-6 py-16">
        <h2 class="text-2xl font-bold tracking-tight">Enam peran, masing-masing dengan manualnya</h2>
        <p class="mt-2 max-w-3xl text-zinc-600">
            Tidak ada peran yang bisa semuanya — termasuk administrator sistem, yang
            justru tidak boleh menyetujui tagihan atau menulis narasi. Menu yang muncul
            pun berbeda per peran. Klik satu kartu untuk membuka manualnya.
        </p>

        <div class="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($peran as $p)
                <a href="{{ url('/manual/'.$p['kode'].'.html') }}"
                   class="group flex flex-col rounded-xl bg-zinc-50 p-6 shadow-sm ring-1 ring-zinc-950/5 transition hover:ring-emerald-500">
                    <h3 class="font-semibold group-hover:text-emerald-700">
                        {{ $p['nama'] }}
                    </h3>
                    <p class="mt-1 text-sm text-zinc-600">{{ $p['ringkas'] }}</p>

                    @if (! empty($p['tidak_boleh']))
                        <p class="mt-3 text-xs text-zinc-500">
                            <span class="font-semibold text-amber-700">Tidak boleh:</span>
                            {{ implode(', ', $p['tidak_boleh']) }}.
                        </p>
                    @endif

                    <p class="mt-3 text-xs text-zinc-500">
                        <span class="font-semibold">Menu:</span> {{ implode(' · ', $p['menu']) }}
                    </p>

                    <span class="mt-4 text-sm font-medium text-emerald-700">
                        Buka manual &rarr;
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- ------------------------------------------------------------------ --}}
{{-- Manual                                                              --}}
{{-- ------------------------------------------------------------------ --}}
<section class="mx-auto max-w-6xl px-6 py-16">
    <div class="grid gap-10 lg:grid-cols-2 lg:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">Manual bergambar, bukan daftar istilah</h2>
            <p class="mt-3 text-zinc-600">
                Setiap gambar di manual ditangkap dari {{ \App\Support\Jati::nama() }}
                yang benar-benar berjalan, lewat peramban sungguhan. Tidak ada gambar
                stok, tidak ada mockup, dan tidak ada tangkapan layar dari aplikasi lain.
            </p>
            <p class="mt-3 text-zinc-600">
                Nama orang yang muncul di gambar adalah nama akun contoh, bukan dosen
                sungguhan, dan tidak ada satu pun bukti akreditasi di dalamnya.
            </p>
            <a href="{{ url('/manual') }}" class="mt-6 inline-block rounded-xl bg-emerald-600 px-6 py-3 font-semibold text-white hover:bg-emerald-500">
                Buka manual pengguna
            </a>
        </div>

        <figure>
            <img src="{{ url('/manual/gambar/anggota/01-tagihan-saya.png') }}" alt="Layar Tagihan Saya" loading="lazy"
                 class="w-full rounded-2xl border border-zinc-950/5 shadow-sm">
            <figcaption class="mt-3 text-sm text-zinc-500">
                Tagihan Saya — layar yang paling sering dibuka anggota pokja.
            </figcaption>
        </figure>
    </div>
</section>

{{-- ------------------------------------------------------------------ --}}
{{-- Footer                                                              --}}
{{-- ------------------------------------------------------------------ --}}
<footer class="border-t border-zinc-200">
    <div class="mx-auto flex max-w-6xl flex-col gap-4 px-6 py-8 text-sm text-zinc-500 sm:flex-row sm:items-center sm:justify-between">
        <p>{{ \App\Support\Jati::hakCipta() }}. {{ \App\Support\Jati::namaPanjang() }}.</p>
        <p class="flex gap-4">
            <a href="{{ url('/manual') }}" class="hover:text-emerald-700">Manual</a>
            <a href="{{ url('/panel') }}" class="hover:text-emerald-700">Masuk</a>
        </p>
    </div>
</footer>

</body>
</html>
