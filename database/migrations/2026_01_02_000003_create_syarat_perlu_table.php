<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lima syarat perlu — gerbang kedua status Unggul, terpisah dari Nilai
 * Akreditasi. NA 361 tanpa kelima syarat ini tetap "Terakreditasi", bukan
 * "Unggul".
 *
 * `nomor_di_tabel_1_3` menyimpan nomor versi Tabel 1.3 Buku 4, yang berbeda
 * dari nomor elemen untuk tiga dari lima butir. Selisih itu memang ada di
 * dokumen aslinya dan JANGAN "diperbaiki" — lihat
 * vibecoding/docs/01-domain-dan-aturan.md bagian terakhir.
 *
 * Ambang disimpan sebagai teks, bukan angka, karena bunyinya majemuk:
 * "PDS3 >= 50% doktor DAN >= 4 DTPS minimal lektor kepala". Yang memutuskan
 * terpenuhi atau tidak adalah manusia, dibantu nilai terukur dari rumus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syarat_perlu', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('elemen_id')->unique()->constrained('elemen')->cascadeOnDelete();
            $table->enum('jenis_ambang', ['ambang_skor', 'ambang_kuantitatif']);
            $table->string('ambang_3_tahun', 300);
            $table->string('ambang_5_tahun', 300);
            $table->text('catatan')->nullable();
            $table->unsignedTinyInteger('nomor_di_tabel_1_3');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syarat_perlu');
    }
};
