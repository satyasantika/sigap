{{--
    Tata letak bersama seluruh halaman galat.

    Sengaja berdiri sendiri, tanpa komponen Filament: halaman galat harus tetap
    tampil ketika yang rusak justru panelnya. Gayanya ditulis langsung di sini
    karena berkas CSS yang gagal dimuat akan membuat halaman galat ikut rusak —
    dan halaman galat yang rusak tidak memberi tahu apa pun.

    Tiga hal yang wajib ada di setiap turunan, dan alasannya:
      judul      apa yang terjadi, dalam satu kalimat
      sebab      MENGAPA — ini yang biasanya hilang dan membuat orang menebak
      langkah    apa yang bisa dilakukan sekarang, bukan "hubungi administrator"
--}}
@props([
    'kode',
    'judul',
    'warna' => '#047857',
    'warnaLembut' => '#ecfdf5',
    'langkah' => null,
    'tombol' => null,
])
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $kode }} — {{ $judul }} · {{ \App\Support\Jati::nama() }}</title>
<style>
    :root {
        --tinta: #18181b; --tinta-lembut: #52525b; --garis: #e4e4e7;
        --latar: #fafafa; --kartu: #ffffff;
        --aksen: {{ $warna }};
        --aksen-lembut: {{ $warnaLembut }};
    }
    @media (prefers-color-scheme: dark) {
        :root {
            --tinta: #f4f4f5; --tinta-lembut: #a1a1aa; --garis: #27272a;
            --latar: #09090b; --kartu: #18181b;
            --aksen-lembut: #1c1917;
        }
    }
    * { box-sizing: border-box; }
    body {
        margin: 0; min-height: 100vh; display: flex; flex-direction: column;
        font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
        color: var(--tinta); background: var(--latar); line-height: 1.65;
    }
    main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 3rem 1rem; }
    .kartu {
        width: 100%; max-width: 40rem; background: var(--kartu);
        border: 1px solid var(--garis); border-radius: 0.9rem; padding: 2rem;
    }
    .kode {
        display: inline-block; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.08em;
        color: var(--aksen); background: var(--aksen-lembut);
        border-radius: 999px; padding: 0.2rem 0.7rem; margin-bottom: 1rem;
    }
    h1 { font-size: 1.6rem; line-height: 1.25; letter-spacing: -0.02em; margin: 0 0 0.75rem; }
    p { margin: 0 0 1rem; }
    .lembut { color: var(--tinta-lembut); }
    h2 { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.06em;
         color: var(--tinta-lembut); margin: 1.75rem 0 0.5rem; }
    ol, ul { margin: 0 0 1rem; padding-left: 1.3rem; }
    li { margin-bottom: 0.45rem; }
    code, .rujukan {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.875em;
        background: var(--latar); border: 1px solid var(--garis);
        border-radius: 0.25rem; padding: 0.05rem 0.35rem;
    }
    .rujukan { display: inline-block; font-size: 1rem; padding: 0.4rem 0.75rem; user-select: all; }
    .tombol {
        display: inline-block; margin-top: 0.5rem; margin-right: 0.5rem;
        background: var(--aksen); color: #fff; text-decoration: none;
        border-radius: 0.5rem; padding: 0.55rem 1.1rem; font-weight: 600; font-size: 0.95rem;
    }
    .tombol.sekunder { background: transparent; color: var(--tinta); border: 1px solid var(--garis); }
    footer { border-top: 1px solid var(--garis); padding: 1.25rem 1rem;
             text-align: center; font-size: 0.8rem; color: var(--tinta-lembut); }
</style>
</head>
<body>
<main>
  <div class="kartu">
    <span class="kode">{{ $kode }}</span>
    <h1>{{ $judul }}</h1>

    {{ $slot }}

    <h2>Yang bisa dilakukan sekarang</h2>
    {{ $langkah }}

    <div>
      {{ $tombol }}
      <a class="tombol sekunder" href="{{ url('/panel') }}">Kembali ke SIGAP</a>
    </div>
  </div>
</main>
<footer>
  {{ \App\Support\Jati::hakCipta() }}. {{ \App\Support\Jati::namaPanjang() }}.
</footer>
</body>
</html>
