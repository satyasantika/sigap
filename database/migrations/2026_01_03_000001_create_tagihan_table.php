<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entitas pusat sistem: satu satuan pekerjaan pengumpulan yang ditugaskan
 * kepada seseorang. Semua yang lain menempel pada tabel ini.
 *
 * `bobot_terkait` decimal(5,3) — tiga desimal, bukan dua. Bobot satu elemen
 * dibagi rata ke tagihan narasi dan bukti miliknya, dan pembagian 1,25 / 2
 * menghasilkan 0,625. Dua desimal akan membulatkannya dan jumlah seluruh
 * periode tidak lagi 100,000.
 *
 * prodi_id dipasang sesuai AGENTS.md aturan 4 meski docs/02 tidak menyebutnya —
 * lihat CLAUDE.md bagian 7 butir 3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prodi_id')->constrained('prodi')->cascadeOnDelete();
            $table->foreignUuid('periode_id')->constrained('periode')->cascadeOnDelete();
            $table->foreignUuid('pokja_id')->constrained('pokja')->cascadeOnDelete();
            $table->foreignUuid('elemen_id')->nullable()->constrained('elemen')->cascadeOnDelete();
            $table->foreignUuid('dkps_butir_id')->nullable()->constrained('dkps_butir')->cascadeOnDelete();
            $table->enum('jenis', ['narasi', 'bukti', 'data_dkps', 'perbaikan_praktik']);
            $table->string('judul', 200);
            $table->text('deskripsi')->nullable();
            $table->foreignUuid('penanggung_jawab_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tenggat')->nullable();
            $table->enum('status', [
                'belum', 'dikerjakan', 'diajukan', 'direviu', 'dikembalikan', 'disetujui',
            ])->default('belum');
            $table->decimal('bobot_terkait', 5, 3)->default(0);
            $table->enum('prioritas', ['biasa', 'tinggi', 'kritis'])->default('biasa');
            $table->smallInteger('urutan')->default(0);
            $table->foreignUuid('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disetujui_pada')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['periode_id', 'status']);
            $table->index(['penanggung_jawab_id', 'status']);
            $table->index(['pokja_id', 'status']);
            $table->index('tenggat');

            // Kunci alami pembangkit: satu tagihan per (periode, jenis, elemen)
            // dan per (periode, jenis, butir DKPS). Tanpa ini, menjalankan
            // `tagihan:bangkitkan` dua kali akan menggandakan 146 baris dan
            // jumlah bobotnya menjadi 200,000.
            $table->unique(['periode_id', 'jenis', 'elemen_id'], 'tagihan_elemen_unik');
            $table->unique(['periode_id', 'jenis', 'dkps_butir_id'], 'tagihan_dkps_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan');
    }
};
