@php
    $u = auth()->user();
    $pesan = trim($exception?->getMessage() ?? '');
    // Laravel memakai "This action is unauthorized." sebagai pesan bawaan.
    // Menampilkannya apa adanya tidak menolong siapa pun.
    $pesanKhusus = $pesan !== '' && ! str_contains($pesan, 'unauthorized') ? $pesan : null;
@endphp

<x-errors.layout kode="403" judul="Halaman ini bukan wewenang Anda"
    warna="#b45309" warna-lembut="#fffbeb">

    @if ($pesanKhusus)
        <p>{{ $pesanKhusus }}</p>
    @endif

    <p>
        @if ($u)
            Anda masuk sebagai <strong>{{ $u->nama_lengkap }}</strong>
            dengan peran <strong>{{ $u->peran->label() }}</strong>.
            Peran itu tidak memegang wewenang atas halaman yang Anda buka.
        @else
            Permintaan ini ditolak karena tidak ada sesi yang sah.
        @endif
    </p>

    <p class="lembut">
        SIGAP membagi wewenang lewat satu matriks 24 aksi &times; 6 peran. Tidak ada
        peran yang bisa semuanya — termasuk administrator sistem, yang justru tidak
        boleh menyetujui tagihan atau menulis narasi. Penolakan ini kemungkinan besar
        bekerja sebagaimana mestinya, bukan salah pasang.
    </p>

    <x-slot:langkah>
        <ol>
            <li>Buka <strong>Pengaturan &rsaquo; Matriks Izin</strong> untuk melihat sendiri
                aksi mana yang dipegang peran Anda. Halaman itu terbuka untuk semua peran.</li>
            <li>Bila pekerjaan ini memang bagian dari tugas Anda, mintalah
                <strong>ketua task force</strong> menugaskannya lewat tagihan — bukan meminta
                peran Anda dinaikkan.</li>
            <li>Bila peran Anda memang keliru tercatat, hubungi
                <strong>administrator sistem</strong>; hanya ia yang bisa mengubahnya.</li>
        </ol>
    </x-slot:langkah>

    <x-slot:tombol>
        <a class="tombol" href="{{ url('/panel/matriks-izin') }}">Lihat matriks izin</a>
    </x-slot:tombol>
</x-errors.layout>
