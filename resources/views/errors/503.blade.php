<x-errors.layout kode="503" judul="SIGAP sedang dirawat"
    warna="#0369a1" warna-lembut="#f0f9ff">

    <p>
        Sistem sengaja dimatikan sementara — biasanya untuk pembaruan, migrasi basis
        data, atau pemulihan cadangan. Tidak ada data yang hilang; ia hanya tidak bisa
        diakses selama perawatan berlangsung.
    </p>

    <x-slot:langkah>
        <ol>
            <li>Tunggu beberapa menit lalu muat ulang. Perawatan terjadwal biasanya
                singkat.</li>
            <li>Bila Anda sedang mengejar tenggat tagihan, beri tahu koordinator pokja
                Anda sekarang — tenggat yang lewat karena perawatan bisa digeser.</li>
            <li>Bila keadaan ini berlangsung lebih dari satu jam tanpa pemberitahuan,
                hubungi administrator sistem.</li>
        </ol>
    </x-slot:langkah>
</x-errors.layout>
