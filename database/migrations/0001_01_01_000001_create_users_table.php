<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel bawaan Laravel, ditulis ulang dengan kunci utama UUID.
 *
 * Lihat aturan proyek 4b dan vibecoding/docs/02-skema-data.md bagian
 * "Kunci utama: UUID, tanpa kecuali". Alasannya keamanan: id menaik
 * membocorkan cacah baris dan membuat URL bisa ditelusuri satu per satu,
 * padahal isinya nama dosen dan nomor serdik.
 *
 * Kolom tambahan SIGAP pada `users` ada di bagian bawah definisi tabel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();

            // --- Tambahan SIGAP (vibecoding/docs/02-skema-data.md bagian B) ---
            $table->string('nama_lengkap');
            $table->string('nidn')->nullable();
            $table->string('jabatan')->nullable();
            $table->foreignUuid('prodi_id')->nullable()->constrained('prodi')->nullOnDelete();
            $table->enum('peran', [
                'admin',
                'ketua',
                'pimpinan',
                'koordinator',
                'anggota',
                'auditor',
            ]);
            $table->boolean('aktif')->default(true);
            $table->timestamp('terakhir_masuk_pada')->nullable();

            // Dipaksa ganti sandi pada login pertama; kata sandi awal dibuat
            // admin, bukan dikirim lewat surel (vibecoding/docs/08-auth-dan-izin.md).
            $table->boolean('wajib_ganti_sandi')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['peran', 'aktif']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
