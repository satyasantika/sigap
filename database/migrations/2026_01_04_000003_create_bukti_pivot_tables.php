<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiga penghubung bukti.
 *
 * `bukti_elemen` BUKAN pivot sederhana: ia punya kunci utama sendiri karena
 * membawa `keterangan` — penjelasan mengapa bukti ini menopang elemen TERTENTU.
 * Satu SK bisa menopang tiga elemen dengan alasan berbeda-beda, dan alasan itu
 * yang dibaca ketua saat memvalidasi.
 *
 * Dua yang lain pivot murni: kunci utama gabungan, tanpa id sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bukti_elemen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bukti_id')->constrained('bukti')->cascadeOnDelete();
            $table->foreignUuid('elemen_id')->constrained('elemen')->cascadeOnDelete();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['bukti_id', 'elemen_id']);
        });

        Schema::create('bukti_tagihan', function (Blueprint $table) {
            $table->foreignUuid('bukti_id')->constrained('bukti')->cascadeOnDelete();
            $table->foreignUuid('tagihan_id')->constrained('tagihan')->cascadeOnDelete();

            $table->primary(['bukti_id', 'tagihan_id']);
        });

        // Tabel dkps_baris lahir di tahap 5; penghubungnya dibuat di sana
        // supaya migrasi ini tidak menunjuk tabel yang belum ada.
    }

    public function down(): void
    {
        Schema::dropIfExists('bukti_tagihan');
        Schema::dropIfExists('bukti_elemen');
    }
};
