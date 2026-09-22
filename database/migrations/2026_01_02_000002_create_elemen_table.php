<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 59 elemen penilaian — inti seluruh instrumen.
 *
 * `no` adalah kunci alami untuk seeder, bukan `id`: id boleh berbeda tiap
 * bangun ulang, nomor elemen tidak.
 *
 * Uji wajib yang tidak boleh dihapus: COUNT(*) = 59 dan SUM(bobot) = 100.00.
 * Satu angka bobot yang tergeser diam-diam merusak seluruh aritmetika Nilai
 * Akreditasi tanpa ada yang terlihat salah di layar.
 *
 * Empat kolom teks terakhir hanya terisi sebagian: `parameter`,
 * `pertanyaan_pemandu`, dan `bukti_pendukung` kosong pada 9 elemen berjenis
 * `refleksi`; sebaliknya `evaluasi_refleksi` hanya terisi pada kesembilan
 * elemen itu. Karena itu semuanya nullable, bukan wajib.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elemen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kriteria_id')->constrained('kriteria')->cascadeOnDelete();
            $table->unsignedTinyInteger('no')->unique();
            $table->string('nama', 200);
            $table->decimal('bobot', 4, 2);
            $table->enum('jenis', ['data', 'rubrik', 'refleksi']);
            $table->boolean('syarat_perlu')->default(false);
            $table->string('pokja_kode', 20);
            $table->text('panduan');
            $table->text('pertanyaan_pemandu')->nullable();
            $table->text('parameter')->nullable();
            $table->text('bukti_pendukung')->nullable();
            $table->text('evaluasi_refleksi')->nullable();
            $table->text('tindak_lanjut')->nullable();
            $table->timestamps();

            $table->index(['kriteria_id', 'no']);
            $table->index('pokja_kode');
            $table->index('syarat_perlu');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elemen');
    }
};
