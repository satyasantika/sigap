<?php

use App\Http\Controllers\ImpersonasiController;
use Illuminate\Support\Facades\Route;

/**
 * SIGAP adalah aplikasi internal tanpa halaman publik. Akar situs langsung
 * mengarah ke panel; tidak ada halaman sambutan dan tidak ada pendaftaran.
 */
Route::redirect('/', '/panel');

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
