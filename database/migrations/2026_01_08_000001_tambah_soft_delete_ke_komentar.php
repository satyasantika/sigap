<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Komentar ikut aturan 8: dihapus lunak, bukan permanen.
 *
 * Komentar adalah tulisan seseorang tentang bukti dan tagihan — sering berisi
 * alasan sebuah tagihan dikembalikan. Menghapusnya permanen menghilangkan
 * separuh percakapan dan menyisakan keputusan tanpa penjelasannya.
 *
 * Tabel ini tidak punya kendala unik, jadi baris terhapus tidak menghalangi
 * pembuatan baris baru — jebakan yang membuat soft delete TIDAK dipilih untuk
 * status_syarat_perlu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('komentar', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('komentar', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
