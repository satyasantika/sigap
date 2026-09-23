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
        //
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
