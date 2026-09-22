<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bukti akreditasi — berkas terunggah atau tautan ke penyimpanan awan.
 *
 * DUA SUMBU YANG TERPISAH, dan keduanya harus hijau sebelum tagihan bisa
 * disetujui:
 *
 *   akses_status     keterbacaan — diperiksa MESIN, harian, tanpa kredensial
 *                    apa pun. Meniru asesor yang tidak punya akses.
 *   validasi_status  keabsahan — dinilai MANUSIA (ketua atau koordinator
 *                    pokja). Mesin tidak bisa menilai apakah sebuah SK benar
 *                    menopang klaim elemen.
 *
 * Menggabungkan keduanya adalah kesalahan yang mahal: tautan yang terbuka
 * belum tentu sah, dan dokumen yang sah belum tentu bisa dibuka asesor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bukti', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prodi_id')->constrained('prodi')->cascadeOnDelete();
            $table->foreignUuid('periode_id')->constrained('periode')->cascadeOnDelete();

            $table->string('judul', 200);
            $table->text('deskripsi')->nullable();
            // Menjelaskan ISI bukti dan bagian mana yang relevan — halaman
            // berapa, baris mana, notulen tanggal berapa.
            $table->text('keterangan')->nullable();

            $table->enum('jenis', ['berkas', 'tautan']);

            // --- Bukti berupa berkas ---
            $table->string('path', 255)->nullable();
            // Nama di disk diacak; nama asli disimpan terpisah supaya nama
            // berkas pengguna tidak menjadi jalur tebak-tebakan di URL.
            $table->string('nama_asli', 255)->nullable();
            $table->string('mime', 100)->nullable();
            $table->bigInteger('ukuran')->nullable();
            $table->char('sha256', 64)->nullable();

            // --- Bukti berupa tautan (tujuh kolom dari dokumen 07) ---
            $table->string('url', 500)->nullable();
            $table->enum('penyedia', ['drive', 'onedrive', 'sharepoint', 'lainnya'])->nullable();
            $table->string('url_kanonik', 500)->nullable();
            $table->string('tautan_id', 100)->nullable();
            $table->enum('tautan_bentuk', ['berkas', 'folder', 'dokumen', 'lembar', 'slide'])->nullable();
            $table->enum('akses_status', [
                'belum_diperiksa', 'terbuka', 'perlu_izin', 'tidak_ditemukan', 'gagal_periksa',
            ])->default('belum_diperiksa');
            $table->timestamp('akses_diperiksa_pada')->nullable();
            $table->string('akses_pesan', 255)->nullable();
            // Dokumen 07 menuntut `gagal_periksa` dicoba ulang maksimal tiga
            // kali sebelum dilaporkan ke manusia, tetapi tidak menyediakan
            // tempat menyimpan cacahnya. Kolom ini yang menyimpannya.
            $table->unsignedTinyInteger('akses_percobaan')->default(0);

            // --- Wajib, ditolak di FormRequest bukan sekadar diperingatkan ---
            $table->date('tanggal_kejadian');
            $table->enum('sumber', ['siakad', 'manual', 'pddikti', 'eksternal']);

            $table->foreignUuid('diunggah_oleh')->constrained('users')->restrictOnDelete();

            // --- Versi: pengganti membuat baris baru, yang lama tetap ada ---
            $table->smallInteger('versi')->default(1);
            $table->foreignUuid('bukti_induk_id')->nullable()->constrained('bukti')->nullOnDelete();

            // --- Sumbu kedua: keabsahan, dinilai manusia ---
            $table->enum('validasi_status', [
                'belum_divalidasi', 'sah', 'meragukan', 'tidak_sah',
            ])->default('belum_divalidasi');
            $table->foreignUuid('divalidasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('divalidasi_pada')->nullable();
            $table->text('catatan_validasi')->nullable();

            // Kunci duplikasi impor, dinormalkan lebih dulu.
            $table->string('kunci_normal', 255)->nullable();
            $table->foreignUuid('impor_batch_id')->nullable();

            // Presisi milidetik, bukan detik. Pembatalan impor ditutup begitu
            // ada baris hasil impor yang disunting orang lain, dan dengan
            // presisi detik suntingan yang terjadi pada detik yang sama dengan
            // impornya tidak terlihat sama sekali.
            $table->timestamps(3);
            $table->softDeletes();

            $table->index(['periode_id', 'akses_status']);
            $table->index(['periode_id', 'validasi_status']);
            $table->index('kunci_normal');
            $table->index('tanggal_kejadian');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bukti');
    }
};
