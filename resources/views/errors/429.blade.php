@php
    $tunggu = (int) (request()->header('Retry-After') ?? 0);
@endphp

<x-errors.layout kode="429" judul="Terlalu banyak percobaan"
    warna="#b45309" warna-lembut="#fffbeb">

    <p>
        SIGAP membatasi jumlah percobaan dari satu alamat dalam waktu singkat.
        @if ($tunggu > 0)
            Coba lagi dalam <strong>{{ $tunggu }} detik</strong>.
        @else
            Tunggu sekitar satu menit sebelum mencoba lagi.
        @endif
    </p>

    <p class="lembut">
        Pembatasan ini menahan penebakan kata sandi. Bila Anda sendiri yang terkena,
        hampir selalu karena kata sandi salah diketik berulang kali — bukan karena
        akun Anda terkunci.
    </p>

    <x-slot:langkah>
        <ol>
            <li>Tunggu, lalu coba sekali lagi dengan hati-hati. Menekan tombol berulang
                justru memperpanjang masa tunggunya.</li>
            <li>Bila Anda lupa kata sandi, berhenti menebak — SIGAP tidak punya
                pemulihan lewat surel. Hubungi administrator sistem untuk menyetelnya ulang.</li>
            <li>Bila Anda yakin tidak pernah mencoba masuk, laporkan ke administrator
                sistem: ada orang lain yang mencoba memakai alamat surel Anda.</li>
        </ol>
    </x-slot:langkah>
</x-errors.layout>
