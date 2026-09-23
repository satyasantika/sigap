<?php

use App\Http\Controllers\BerandaController;
use App\Http\Controllers\ImpersonasiController;
use App\Http\Controllers\TurController;
use Illuminate\Support\Facades\Route;

/**
 * Halaman muka. Terbuka tanpa masuk, tetapi TIDAK memuat satu pun angka
 * akreditasi — lihat BerandaController untuk alasannya. Pendaftaran mandiri
 * tetap tidak ada; akun dibuat administrator sistem.
 */
Route::get('/', BerandaController::class)->name('beranda');

/**
 * Manual pengguna disajikan sebagai berkas statis lewat symlink
 * `public/manual` -> `docs/manual`, pola yang sama dengan `public/storage`.
 *
 * Bukan lewat pengendali PHP, dan itu bukan pilihan gaya: berkas .png dan .css
 * ditangani nginx pada `location ~* \.(js|css|png|...)$` yang tidak pernah
 * meneruskan ke PHP, jadi rute Laravel untuk gambar manual tidak akan pernah
 * terpanggil. Symlink membuat ketiganya — HTML, CSS, gambar — disajikan satu
 * cara yang sama.
 *
 * Satu salinan saja, tetap di `docs/manual/` yang terlacak git. Dua salinan
 * akan menyimpang, dan manual yang menampilkan layar lama lebih menyesatkan
 * daripada tidak ada manual sama sekali.
 *
 * Rute di bawah hanya jaring pengaman untuk `php artisan serve` dan uji, yang
 * tidak melewati nginx. Di produksi nginx sudah mengalihkan `/manual` ke
 * `/manual/` lebih dulu.
 */
Route::redirect('/manual', '/manual/index.html')->name('manual');

/**
 * Tur terpandu per peran. Terbuka tanpa masuk, dan seperti halaman muka ia
 * tidak menyentuh basis data sama sekali — yang ditampilkan hanya tangkapan
 * layar dengan data contoh.
 */
Route::get('/tur', [TurController::class, 'index'])->name('tur');
Route::get('/tur/{peran}', [TurController::class, 'peran'])->name('tur.peran');
/**
 * Mengakhiri penyamaran. Satu-satunya rute di luar panel, dan sengaja begitu:
 * tombol di spanduk harus tetap bekerja walau JavaScript gagal dimuat.
 * Tanpa middleware `auth`: middleware itu mengarahkan tamu ke rute bernama
 * `login` yang tidak ada di SIGAP. Pemeriksaannya dilakukan pengendali, yang
 * mengarahkan tamu ke halaman masuk panel dan mengembalikan pengguna apa adanya
 * bila ternyata tidak ada penyamaran untuk diakhiri.
 */
Route::post('/impersonasi/akhiri', [ImpersonasiController::class, 'akhiri'])
    ->name('sigap.impersonasi.akhiri');
