{{--
    Footer hak cipta. Terpasang lewat render hook panels::footer, yang dipakai
    baik oleh tata letak panel maupun tata letak sederhana — jadi halaman masuk
    pun memuatnya, tanpa perlu menyisipkannya satu per satu.

    Tahunnya datang dari config/sigap.php, bukan ditulis di sini: aturan 3
    melarang tahun mati di dalam app/, dan tahun hak cipta memang bukan TS —
    ia justru tidak boleh ikut bergeser saat periode berganti.
--}}
<footer class="fi-sigap-footer mt-8 border-t border-gray-200 px-4 py-4 text-center text-xs text-gray-500 sm:px-6 lg:px-8 dark:border-white/10 dark:text-gray-400">
    <p>{{ \App\Support\Jati::hakCipta() }}. {{ \App\Support\Jati::namaPanjang() }}.</p>
</footer>
