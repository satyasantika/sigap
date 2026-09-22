<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hasil perhitungan rumus. TIDAK PERNAH menimpa baris lama.
 *
 * Angka akreditasi berubah sepanjang periode karena datanya masih masuk.
 * Menyimpan riwayatnya memungkinkan menjawab "kapan PDS3 kita turun di bawah
 * 50?" — pertanyaan yang muncul justru saat ada sengketa, dan tidak bisa
 * dijawab kalau tiap perhitungan menimpa yang sebelumnya.
 *
 * `komponen` menyimpan masukannya: tanpa itu, angka 41,67 tidak bisa
 * ditelusuri kembali ke "5 dari 12 DTPS".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nilai_rumus', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('periode_id')->constrained('periode')->cascadeOnDelete();
            $table->string('rumus_kode', 20);
            $table->decimal('nilai', 10, 4)->nullable();
            $table->tinyInteger('skor')->nullable();

            // Dua medan TERPISAH dari skor. Skor 4 tidak berarti syarat
            // perlunya terpenuhi — pada PDS3 dan PPDTPS ambang syarat perlu
            // justru lebih tinggi daripada ambang skor penuh.
            $table->boolean('memenuhi_syarat_3_tahun')->nullable();
            $table->boolean('memenuhi_syarat_5_tahun')->nullable();

            $table->json('komponen')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('dihitung_pada')->useCurrent();
            $table->foreignUuid('dihitung_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['periode_id', 'rumus_kode', 'dihitung_pada'], 'nilai_rumus_terakhir');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai_rumus');
    }
};
