<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asesmen mandiri: skor 1..4 per elemen, per penilai.
 *
 * Kunci uniknya (periode, elemen, penilai) — BUKAN (periode, elemen). Dua
 * penilai sengaja boleh menskor terpisah, meniru mekanisme dua asesor pada
 * asesmen kecukupan. Selisih di antara keduanya justru informasi yang berharga:
 * elemen yang dinilai 4 oleh satu orang dan 2 oleh yang lain hampir selalu
 * elemen yang buktinya belum meyakinkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penilaian', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prodi_id')->constrained('prodi')->cascadeOnDelete();
            $table->foreignUuid('periode_id')->constrained('periode')->cascadeOnDelete();
            $table->foreignUuid('elemen_id')->constrained('elemen')->cascadeOnDelete();
            $table->unsignedTinyInteger('skor');
            $table->text('catatan')->nullable();
            $table->foreignUuid('penilai_id')->constrained('users')->restrictOnDelete();
            $table->date('tanggal');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['periode_id', 'elemen_id', 'penilai_id'], 'penilaian_unik');
            $table->index(['periode_id', 'penilai_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penilaian');
    }
};
