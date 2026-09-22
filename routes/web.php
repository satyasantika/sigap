<?php

use Illuminate\Support\Facades\Route;

/**
 * SIGAP adalah aplikasi internal tanpa halaman publik. Akar situs langsung
 * mengarah ke panel; tidak ada halaman sambutan dan tidak ada pendaftaran.
 */
Route::redirect('/', '/panel');
