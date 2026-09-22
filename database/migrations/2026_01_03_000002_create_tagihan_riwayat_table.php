<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append only: tanpa updated_at, tanpa soft delete.
 *
 * Riwayat status adalah jejak audit — siapa menyetujui apa dan kapan. Kalau ia
 * bisa disunting, ia berhenti menjadi bukti. Penolakannya ditegakkan di model
 * lewat event `updating` dan `deleting`, bukan hanya di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan_riwayat', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tagihan_id')->constrained('tagihan')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('status_dari', 20)->nullable();
            $table->string('status_ke', 20);
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tagihan_id', 'created_at']);
            // Dipakai KalkulatorLaju di tahap 6: bobot yang berpindah ke
            // `disetujui` dalam 28 hari terakhir.
            $table->index(['status_ke', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan_riwayat');
    }
};
