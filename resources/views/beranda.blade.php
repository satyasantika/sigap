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
@include('sigap.font')
@vite(['resources/css/app.css'])
</head>
<body class="latar-kertas font-[Inter,ui-sans-serif,system-ui,sans-serif] text-[color:var(--tinta)] antialiased">

{{-- Bilah atas --}}
<header class="sticky top-0 z-20 border-b border-[color:var(--garis)] bg-[color:var(--kertas)]/85 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-3">
        <a href="#" class="flex items-center gap-2.5">
            <span class="font-judul inline-flex size-9 items-center justify-center rounded-full bg-[color:var(--zamrud)] text-lg font-bold text-white">S</span>
            <span class="font-judul text-xl font-bold">{{ \App\Support\Jati::nama() }}</span>
        </a>

        <nav class="flex items-center gap-1 text-sm">
            <a href="#cara-kerja" class="hidden rounded-full px-3 py-2 text-[color:var(--tinta-pudar)] hover:bg-[color:var(--kertas-dalam)] sm:block">Cara kerja</a>
            <a href="#peran" class="hidden rounded-full px-3 py-2 text-[color:var(--tinta-pudar)] hover:bg-[color:var(--kertas-dalam)] sm:block">Peran</a>
            <a href="{{ url('/manual/index.html') }}" class="rounded-full px-3 py-2 font-medium text-[color:var(--zamrud-tua)] hover:bg-[color:var(--zamrud-muda)]">Manual</a>
            <a href="{{ url('/panel') }}" class="ml-1 rounded-full bg-[color:var(--zamrud)] px-5 py-2 font-semibold text-white hover:bg-[color:var(--zamrud-tua)]">Masuk</a>
        </nav>
    </div>
</header>

{{-- Kepala --}}
<section class="mx-auto grid max-w-6xl items-center gap-12 px-6 pt-14 pb-16 sm:pt-20 lg:grid-cols-12">
    <div class="lg:col-span-6">
        <p class="mb-5 inline-flex items-center gap-2 rounded-full border border-[color:var(--garis)] bg-white/70 px-3 py-1 text-xs font-semibold tracking-wide text-[color:var(--zamrud-tua)] uppercase">
            <span class="size-1.5 rounded-full bg-[color:var(--zamrud)]"></span>
            Aplikasi internal · Prodi PPG
        </p>

        <h1 class="font-judul text-4xl leading-[1.08] font-bold text-balance sm:text-5xl">
            Bukti akreditasi dikumpulkan <em class="text-[color:var(--zamrud)] not-italic">sepanjang jalan</em>,
            bukan dicari saat asesor sudah datang.
        </h1>

        <p class="mt-6 max-w-xl text-lg text-[color:var(--tinta-pudar)]">
            {{ \App\Support\Jati::nama() }} memecah instrumen LAMDIK menjadi pekerjaan
            yang bisa ditugaskan, ditagih, dan diperiksa — lalu memperlihatkan posisinya
            setiap hari, bukan hanya di rapat terakhir.
        </p>

        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ url('/panel') }}" class="rounded-full bg-[color:var(--zamrud)] px-7 py-3 font-semibold text-white shadow-sm hover:bg-[color:var(--zamrud-tua)]">
                Masuk ke {{ \App\Support\Jati::nama() }}
            </a>
            <a href="{{ url('/manual/index.html') }}" class="rounded-full border border-[color:var(--tinta)]/20 bg-white/60 px-7 py-3 font-semibold hover:bg-white">
                Baca manual pengguna
            </a>
        </div>

        <p class="mt-5 text-sm text-[color:var(--tinta-pudar)]">
            Tidak ada pendaftaran mandiri. Akun dibuat administrator sistem.
        </p>
    </div>

    <figure class="relative lg:col-span-6">
        <div class="absolute -inset-4 -z-10 rotate-2 rounded-[2rem] bg-[color:var(--zamrud-muda)]"></div>
        <img src="{{ url('/manual/gambar/ketua/01-dasbor.png') }}" alt="Dasbor SIGAP" loading="lazy"
             class="w-full -rotate-1 rounded-2xl border border-[color:var(--garis)] bg-white shadow-xl shadow-emerald-900/10">
        <figcaption class="mt-5 text-sm text-[color:var(--tinta-pudar)]">
            Dasbor ketua task force. Tangkapan layar sungguhan dari sistem ini, dengan data contoh.
        </figcaption>
    </figure>
</section>

{{-- Angka instrumen --}}
<section class="border-y border-[color:var(--garis)] bg-[color:var(--kertas-dalam)]/60">
    <div class="mx-auto max-w-6xl px-6 py-16">
        <h2 class="font-judul text-3xl font-bold">Bentuk instrumennya</h2>
        <p class="mt-3 max-w-2xl text-[color:var(--tinta-pudar)]">
            Angka di bawah bukan capaian, melainkan ukuran pekerjaannya. Seluruhnya
            dibaca dari berkas yang sama dengan yang dipakai penyemai basis data,
            jadi halaman ini tidak bisa menyimpang dari isi sistemnya.
        </p>

        <dl class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($angka as $a)
                <div class="rounded-2xl border border-[color:var(--garis)] bg-white p-6">
                    <dt class="font-judul text-4xl font-bold text-[color:var(--zamrud)]">{{ $a['nilai'] }}</dt>
                    <dd class="mt-2 font-semibold">{{ $a['label'] }}</dd>
                    <dd class="mt-1 text-sm text-[color:var(--tinta-pudar)]">{{ $a['catatan'] }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>

{{-- Cara kerja --}}
<section id="cara-kerja" class="mx-auto max-w-6xl px-6 py-20">
    <h2 class="font-judul text-3xl font-bold">Cara kerjanya</h2>

    <ol class="mt-10 grid gap-8 lg:grid-cols-3">
        <li class="relative border-t-2 border-[color:var(--tinta)] pt-5">
            <span class="font-judul text-5xl font-bold text-[color:var(--zamrud)]/30">1</span>
            <h3 class="mt-2 text-lg font-semibold">Instrumen dipecah jadi tagihan</h3>
            <p class="mt-2 text-[color:var(--tinta-pudar)]">
                Setiap elemen melahirkan tagihan narasi, bukti, dan isian DKPS —
                masing-masing membawa bobotnya sendiri. Tagihan punya penanggung
                jawab dan tenggat, jadi "siapa mengerjakan apa" tidak lagi
                bergantung pada ingatan.
            </p>
        </li>

        <li class="relative border-t-2 border-[color:var(--tinta)] pt-5">
            <span class="font-judul text-5xl font-bold text-[color:var(--zamrud)]/30">2</span>
            <h3 class="mt-2 text-lg font-semibold">Bukti diperiksa dua kali</h3>
            <p class="mt-2 text-[color:var(--tinta-pudar)]">
                <strong class="text-[color:var(--tinta)]">Keterbacaan</strong> diperiksa mesin: tautan dibuka tanpa
                kredensial apa pun, persis seperti asesor akan membukanya.
                <strong class="text-[color:var(--tinta)]">Keabsahan</strong> dinilai manusia — tautan bisa terbuka lebar
                tetapi berisi dokumen yang keliru. Keduanya harus hijau sebelum tagihan
                bisa disetujui.
            </p>
        </li>

        <li class="relative border-t-2 border-[color:var(--tinta)] pt-5">
            <span class="font-judul text-5xl font-bold text-[color:var(--zamrud)]/30">3</span>
            <h3 class="mt-2 text-lg font-semibold">Posisinya terlihat setiap hari</h3>
            <p class="mt-2 text-[color:var(--tinta-pudar)]">
                Progres dihitung dari bobot, tidak pernah dari cacah tagihan. Dua
                puluh tagihan ringan yang selesai tidak menggeser angka sebanyak satu
                elemen berbobot besar — dan dasbor mengatakannya apa adanya.
            </p>
        </li>
    </ol>

    <div class="mt-14 rounded-2xl border border-[color:var(--emas)]/30 bg-[color:var(--emas-muda)] p-7">
        <h3 class="font-judul text-xl font-bold">Nilai tinggi belum berarti Unggul</h3>
        <p class="mt-2 max-w-3xl text-[color:var(--tinta)]/80">
            Lima syarat perlu adalah gerbang terpisah dari nilai. Nilai Akreditasi
            tertinggi sekalipun tetap berujung "Terakreditasi" bila salah satu dari
            kelimanya belum terpenuhi. Karena itu dasbor tidak pernah menampilkan
            progres tanpa menampilkan gerbangnya.
        </p>
    </div>
</section>

{{-- Peran dan manual --}}
<section id="peran" class="border-y border-[color:var(--garis)] bg-[color:var(--kertas-dalam)]/60">
    <div class="mx-auto max-w-6xl px-6 py-20">
        <h2 class="font-judul text-3xl font-bold">Enam peran, masing-masing dengan manualnya</h2>
        <p class="mt-3 max-w-3xl text-[color:var(--tinta-pudar)]">
            Tidak ada peran yang bisa semuanya — termasuk administrator sistem, yang
            justru tidak boleh menyetujui tagihan atau menulis narasi. Menu yang muncul
            pun berbeda per peran. Klik satu kartu untuk membuka manualnya.
        </p>

        <div class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($peran as $p)
                <a href="{{ url('/manual/'.$p['kode'].'.html') }}"
                   class="group flex flex-col rounded-2xl border border-[color:var(--garis)] border-l-4 border-l-[color:var(--zamrud)] bg-white p-6 transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-emerald-900/10">
                    <h3 class="font-judul text-lg font-bold group-hover:text-[color:var(--zamrud-tua)]">
                        {{ $p['nama'] }}
                    </h3>
                    <p class="mt-1 text-sm text-[color:var(--tinta-pudar)]">{{ $p['ringkas'] }}</p>

                    @if (! empty($p['tidak_boleh']))
                        <p class="mt-3 text-xs text-[color:var(--tinta-pudar)]">
                            <span class="font-semibold text-[color:var(--emas)]">Tidak boleh:</span>
                            {{ implode(', ', $p['tidak_boleh']) }}.
                        </p>
                    @endif

                    <p class="mt-3 text-xs text-[color:var(--tinta-pudar)]">
                        <span class="font-semibold">Menu:</span> {{ implode(' · ', $p['menu']) }}
                    </p>

                    <span class="mt-4 text-sm font-semibold text-[color:var(--zamrud-tua)]">
                        Buka manual &rarr;
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- Manual --}}
<section class="mx-auto max-w-6xl px-6 py-20">
    <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
        <div>
            <h2 class="font-judul text-3xl font-bold">Manual bergambar, bukan daftar istilah</h2>
            <p class="mt-4 text-[color:var(--tinta-pudar)]">
                Setiap gambar di manual ditangkap dari {{ \App\Support\Jati::nama() }}
                yang benar-benar berjalan, lewat peramban sungguhan. Tidak ada gambar
                stok, tidak ada mockup, dan tidak ada tangkapan layar dari aplikasi lain.
            </p>
            <p class="mt-3 text-[color:var(--tinta-pudar)]">
                Nama orang yang muncul di gambar adalah nama akun contoh, bukan dosen
                sungguhan, dan tidak ada satu pun bukti akreditasi di dalamnya.
            </p>
            <a href="{{ url('/manual/index.html') }}" class="mt-7 inline-block rounded-full bg-[color:var(--zamrud)] px-7 py-3 font-semibold text-white hover:bg-[color:var(--zamrud-tua)]">
                Buka manual pengguna
            </a>
        </div>

        <figure class="relative">
            <div class="absolute -inset-4 -z-10 -rotate-2 rounded-[2rem] bg-[color:var(--emas-muda)]"></div>
            <img src="{{ url('/manual/gambar/anggota/01-tagihan-saya.png') }}" alt="Layar Tagihan Saya" loading="lazy"
                 class="w-full rotate-1 rounded-2xl border border-[color:var(--garis)] bg-white shadow-xl shadow-amber-900/10">
            <figcaption class="mt-5 text-sm text-[color:var(--tinta-pudar)]">
                Tagihan Saya — layar yang paling sering dibuka anggota pokja.
            </figcaption>
        </figure>
    </div>
</section>

{{-- Footer --}}
<footer class="border-t border-[color:var(--garis)]">
    <div class="mx-auto flex max-w-6xl flex-col gap-4 px-6 py-8 text-sm text-[color:var(--tinta-pudar)] sm:flex-row sm:items-center sm:justify-between">
        <p>{{ \App\Support\Jati::hakCipta() }}. {{ \App\Support\Jati::namaPanjang() }}.</p>
        <p class="flex gap-4">
            <a href="{{ url('/manual/index.html') }}" class="hover:text-[color:var(--zamrud-tua)]">Manual</a>
            <a href="{{ url('/panel') }}" class="hover:text-[color:var(--zamrud-tua)]">Masuk</a>
        </p>
    </div>
</footer>

</body>
</html>
