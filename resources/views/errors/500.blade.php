<x-errors.layout kode="500" judul="Ada yang salah di sisi kami"
    warna="#b91c1c" warna-lembut="#fef2f2">

    <p>
        Permintaan Anda gagal diproses karena kesalahan di dalam SIGAP, bukan karena
        yang Anda lakukan. Kesalahannya sudah tercatat lengkap beserta jejak
        tumpukannya.
    </p>

    <h2>Kode rujukan</h2>
    <p>
        <span class="rujukan">{{ \App\Exceptions\KodeRujukan::kode() }}</span>
    </p>
    <p class="lembut">
        Sertakan kode ini saat melapor. Kode yang sama tersimpan di berkas log, jadi
        satu perintah <code>grep</code> langsung menunjuk baris yang tepat — tanpa
        kode ini, mencarinya berarti menebak jam kejadian.
    </p>

    <x-slot:langkah>
        <ol>
            <li><strong>Jangan mengulang tindakan yang sama berkali-kali.</strong> Bila
                tindakan itu sempat menulis sebagian data, pengulangan bisa menggandakannya.</li>
            <li>Salin kode rujukan di atas, catat apa yang sedang Anda lakukan, lalu
                laporkan ke administrator sistem.</li>
            <li>Periksa dulu apakah pekerjaan Anda benar-benar gagal: buka ulang layarnya
                di tab baru. Sebagian kesalahan terjadi setelah data tersimpan.</li>
        </ol>
    </x-slot:langkah>
</x-errors.layout>
