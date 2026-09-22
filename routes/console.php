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
