<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `nilai_rumus.nilai` dilebarkan dari decimal(10,4) ke decimal(18,4).
 *
 * decimal(10,4) hanya muat sampai 999.999,9999, padahal nilai rumus DANA
 * adalah ROP dalam rupiah per mahasiswa per tahun — ambang skornya saja
 * 5, 10, dan 18 juta. Contoh kertas kerja (ROP ±58 ribu) kebetulan muat,
 * sehingga cacat ini baru tersingkap saat DKPS demo yang realistis
 * (ROP 16 juta) dihitung lewat PenghitungRumusDkps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nilai_rumus', function (Blueprint $table) {
            $table->decimal('nilai', 18, 4)->nullable()->change();
        });
    }

    /**
     * Pembalikan ditolak bila ada baris bernilai 1.000.000 atau lebih; baris
     * itu harus dikosongkan lebih dulu.
     */
    public function down(): void
    {
        Schema::table('nilai_rumus', function (Blueprint $table) {
            $table->decimal('nilai', 10, 4)->nullable()->change();
        });
    }
};
