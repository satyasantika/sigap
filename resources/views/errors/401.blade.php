<x-errors.layout kode="401" judul="Anda belum masuk"
    warna="#0369a1" warna-lembut="#f0f9ff">

    <p>
        Halaman yang Anda buka menuntut sesi yang sah, dan sesi Anda sudah tidak ada —
        entah karena berakhir sendiri, atau karena Anda keluar di tab lain.
    </p>

    <x-slot:langkah>
        <ol>
            <li>Masuk kembali, lalu buka ulang halaman yang tadi Anda tuju.</li>
            <li>Tidak ada pendaftaran mandiri di SIGAP. Bila Anda belum punya akun,
                administrator sistem yang membuatkannya.</li>
        </ol>
    </x-slot:langkah>

    <x-slot:tombol>
        <a class="tombol" href="{{ url('/panel/login') }}">Masuk</a>
    </x-slot:tombol>
</x-errors.layout>
