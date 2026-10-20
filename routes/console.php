<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tautan yang tadinya terbuka bisa tertutup kapan saja tanpa memberi tahu
// siapa pun — seseorang memindahkan berkasnya atau menyetel ulang berbagi
// folder induknya. Karena itu diperiksa ulang setiap hari, bukan sekali saja
// saat diunggah.
Schedule::command('bukti:periksa-tautan')->dailyAt('02:00');

// Detak penjadwal: dibaca /api/health (peringatan bila > 5 menit). Cap waktu (int), bukan objek Carbon:
// cache database tidak mengembalikan objek (cache.serializable_classes = false).
Schedule::call(fn () => cache()->put(config('app.kode').':detak-penjadwal', now()->getTimestamp(), 600))
    ->everyMinute()->name('detak-penjadwal');
