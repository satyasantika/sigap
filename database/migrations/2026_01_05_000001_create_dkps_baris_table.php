<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Isian DKPS: satu baris per butir per tahun acuan.
 *
 * Kolom `data` berbentuk json karena bentuk tabel tiap butir BERBEDA — butir
 * "Mahasiswa" punya kolom lain sama sekali dari butir "Publikasi Ilmiah DTPS".
 * Membuat 28 tabel terpisah akan menghasilkan 28 migrasi yang isinya hampir
 * sama dan tetap harus diubah tiap kali instrumen berganti.
 *
 * Bentuknya divalidasi di tingkat aplikasi, bukan basis data. JANGAN memakai
 * whereJsonContains — di MariaDB kolom JSON hanyalah alias LONGTEXT dan
 * Laravel melemparkan kesalahan.
 * Penyaringan memakai kolom nyata di bawah ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dkps_baris', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prodi_id')->constrained('prodi')->cascadeOnDelete();
            $table->foreignUuid('periode_id')->constrained('periode')->cascadeOnDelete();
            $table->foreignUuid('dkps_butir_id')->constrained('dkps_butir')->cascadeOnDelete();

            // TS, TS-1, ... TS-4 — relatif terhadap periode.ts_tahun, tidak
            // pernah tahun mutlak. AGENTS.md aturan 3.
            $table->enum('tahun_acuan', ['TS', 'TS-1', 'TS-2', 'TS-3', 'TS-4']);

            $table->json('data');
            $table->enum('sumber', ['siakad', 'manual', 'pddikti', 'eksternal']);

            $table->foreignUuid('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_pada')->nullable();

            // Kunci duplikasi impor. NIDN dan DOI hidup di dalam `data`, jadi
            // kunci ternormalkannya disalin ke kolom nyata yang terindeks —
            // mencarinya di dalam json tidak mungkin di MariaDB.
            $table->string('kunci_normal', 255)->nullable();
            $table->foreignUuid('impor_batch_id')->nullable()->constrained('impor_batch')->nullOnDelete();

            $table->timestamps(3);
            $table->softDeletes();

            $table->index(['periode_id', 'dkps_butir_id', 'tahun_acuan'], 'dkps_baris_pencarian');
            $table->index('kunci_normal');
            $table->index('diverifikasi_pada');
        });

        // Penghubung bukti ke baris DKPS. Dijanjikan dokumen 07 tetapi baru
        // bisa dibuat sekarang, setelah dkps_baris ada.
        Schema::create('bukti_dkps_baris', function (Blueprint $table) {
            $table->foreignUuid('bukti_id')->constrained('bukti')->cascadeOnDelete();
            $table->foreignUuid('dkps_baris_id')->constrained('dkps_baris')->cascadeOnDelete();

            $table->primary(['bukti_id', 'dkps_baris_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bukti_dkps_baris');
        Schema::dropIfExists('dkps_baris');
    }
};
