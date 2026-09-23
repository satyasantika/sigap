<x-errors.layout kode="419" judul="Halaman ini sudah kedaluwarsa"
    warna="#b45309" warna-lembut="#fffbeb">

    <p>
        Formulir yang Anda kirim dibuat terlalu lama sebelum dikirim, jadi tanda
        pengamannya sudah tidak berlaku. SIGAP menolaknya demi keamanan, bukan
        karena isiannya salah.
    </p>

    <p class="lembut">
        Ini biasa terjadi bila sebuah tab dibiarkan terbuka semalaman, atau bila
        Anda masuk lagi di tab lain sementara tab ini masih menampilkan halaman lama.
    </p>

    <x-slot:langkah>
        <ol>
            <li><strong>Sebelum memuat ulang</strong>, salin dulu isian panjang yang belum
                tersimpan — narasi LED terutama. Memuat ulang akan mengosongkannya.</li>
            <li>Muat ulang halamannya, lalu tempelkan kembali isian itu dan kirim lagi.</li>
            <li>Bila ini berulang dalam hitungan menit, kemungkinan sesi Anda berakhir
                di tempat lain. Keluar lalu masuk kembali sekali.</li>
        </ol>
    </x-slot:langkah>
</x-errors.layout>
