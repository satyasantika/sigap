<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simulasi dalam dua bentuk, keduanya bisa dibuat dan dihapus.
 *
 *   skor     "bagaimana jika E58 naik ke 4?" — menimpa skor elemen tertentu
 *            lalu menghitung ulang NA, TANPA menyentuh tabel `penilaian`.
 *   periode  periode sandbox berisi data latihan yang bisa dibuang utuh.
 *
 * Keduanya WAJIB ditandai jelas di layar. Simulasi yang tertukar dengan data
 * sungguhan adalah cara paling cepat membuat orang berhenti mempercayai
 * angka mana pun di sistem ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulasi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prodi_id')->constrained('prodi')->cascadeOnDelete();
            // Periode acuan: dari sinilah angka dasarnya diambil.
            $table->foreignUuid('periode_id')->constrained('periode')->cascadeOnDelete();
            $table->enum('jenis', ['skor', 'periode']);
            $table->string('nama', 120);
            $table->text('keterangan')->nullable();

            /**
             * Untuk jenis `skor`: {"elemen_id": skor, ...} dan
             * {"syarat_perlu": {"elemen_id": "lima"}}.
             * Untuk jenis `periode`: id periode sandbox yang dibuatnya.
             */
            $table->json('parameter')->nullable();

            // Hasil perhitungan terakhir, disimpan agar daftar simulasi bisa
            // menampilkan NA tanpa menghitung ulang seluruhnya tiap kali.
            $table->json('hasil')->nullable();

            $table->foreignUuid('periode_sandbox_id')->nullable()
                ->constrained('periode')->nullOnDelete();

            $table->foreignUuid('dibuat_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['periode_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulasi');
    }
};
