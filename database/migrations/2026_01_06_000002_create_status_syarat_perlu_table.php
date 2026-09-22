<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Level pemenuhan kelima syarat perlu, ditetapkan manusia.
 *
 * Sengaja TIDAK diturunkan otomatis dari nilai_rumus: sebagian ambangnya
 * majemuk dan berbunyi kalimat ("PDS3 >= 50% doktor DAN >= 4 DTPS minimal
 * lektor kepala"), dan tiga dari lima berupa ambang skor yang lahir dari
 * penilaian rubrik, bukan dari rumus. `nilai_terukur` menyimpan angka
 * pendukungnya supaya keputusannya bisa ditelusuri.
 *
 * Kelima baris inilah gerbang kedua status Unggul — NA 361 tanpa mereka tetap
 * berujung "Terakreditasi".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_syarat_perlu', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prodi_id')->constrained('prodi')->cascadeOnDelete();
            $table->foreignUuid('periode_id')->constrained('periode')->cascadeOnDelete();
            $table->foreignUuid('elemen_id')->constrained('elemen')->cascadeOnDelete();
            $table->enum('level', ['belum', 'tiga', 'lima'])->default('belum');
            $table->string('nilai_terukur', 100)->nullable();
            $table->text('catatan')->nullable();
            $table->foreignUuid('diperbarui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['periode_id', 'elemen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_syarat_perlu');
    }
};
