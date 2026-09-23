<x-errors.layout kode="404" judul="Halaman ini tidak ada"
    warna="#0369a1" warna-lembut="#f0f9ff">

    <p>
        Alamat <code>{{ request()->path() }}</code> tidak dikenali SIGAP.
    </p>

    <p class="lembut">
        Dua sebab yang paling sering: tautan lama yang disimpan di penanda peramban
        setelah alamat halamannya berubah, atau baris data yang sudah dihapus —
        data akreditasi tidak hilang permanen, tetapi baris yang dihapus memang
        tidak lagi punya halaman sendiri.
    </p>

    <x-slot:langkah>
        <ol>
            <li>Periksa alamatnya, terutama bila Anda menempelkannya dari surel atau catatan.</li>
            <li>Cari lewat menu, bukan lewat alamat. Kotak pencarian di kanan atas
                menjangkau tagihan, bukti, dan elemen sekaligus.</li>
            <li>Bila tautan ini dikirim orang lain, mintalah ia membuka halamannya
                lalu menyalin ulang alamat dari bilah peramban.</li>
        </ol>
    </x-slot:langkah>
</x-errors.layout>
