<?php

use App\Exceptions\KodeRujukan;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Closure ini dibiarkan tanpa konfigurasi proksi dengan sengaja.
         *
         * Laravel menjalankannya saat HttpKernel pertama kali dibuat — titik
         * yang terjadi SEBELUM bootstrapper LoadEnvironmentVariables memuat
         * .env untuk permintaan itu. env('TRUSTED_PROXIES') di titik ini
         * kembali NULL pada sebagian permintaan (tergantung proses PHP-FPM
         * mana yang menanganinya), sehingga server balik kadang dipercaya
         * kadang tidak, dan setiap tautan yang bergantung pada header
         * X-Forwarded-* — termasuk endpoint update Livewire — kadang benar
         * kadang rusak tanpa pola yang kelihatan dari luar.
         *
         * Pengaturan proksi yang sesungguhnya ada di
         * App\Providers\AppServiceProvider::percayaiProksi(), yang berjalan
         * di boot() setelah .env dan config penuh dimuat.
         */
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * Kode rujukan ikut ke SETIAP baris log galat, dan kode yang sama
         * ditampilkan halaman 500. Itulah gunanya: laporan "tadi pagi error"
         * berubah menjadi `grep SIGAP-7KQ3M2XA storage/logs`.
         *
         * KodeRujukan mengingat nilainya per permintaan, jadi satu permintaan
         * yang memicu beberapa baris log tetap membawa satu kode.
         */
        $exceptions->context(fn (): array => ['kode_rujukan' => KodeRujukan::kode()]);
    })->create();
