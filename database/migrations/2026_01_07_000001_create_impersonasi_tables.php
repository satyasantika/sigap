<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personifikasi pengguna, beserta harga yang harus dibayarnya.
 *
 * `vibecoding/docs/08-auth-dan-izin.md` melarang fitur ini; larangan itu
 * dibatalkan keputusan manusia (CLAUDE.md bagian 7 butir 8). Selama impersonasi
 * admin memegang wewenang penuh peran yang ditirunya — termasuk menyetujui
 * tagihan.
 *
 * Itulah sebabnya tabel ini ada. Tanpa pencatatan, `disetujui_oleh` akan
 * menunjuk ketua padahal admin yang menekan, dan persetujuan akreditasi
 * berhenti bisa dipertanggungjawabkan. Kolom `impersonasi_oleh` yang
 * ditambahkan ke tabel riwayat di bawah adalah penebus itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impersonasi_sesi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('target_id')->constrained('users')->restrictOnDelete();
            $table->string('alasan', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('dimulai_pada')->useCurrent();
            $table->timestamp('diakhiri_pada')->nullable();

            $table->index(['admin_id', 'dimulai_pada']);
            $table->index(['target_id', 'dimulai_pada']);
            // Satu admin hanya boleh punya satu sesi berjalan.
            $table->index('diakhiri_pada');
        });

        /**
         * Catatan aksi selama impersonasi.
         *
         * Sengaja append only dan terpisah dari tabel riwayat masing-masing
         * modul: riwayat menjawab "apa yang terjadi pada tagihan ini", log ini
         * menjawab "apa saja yang dilakukan admin selama menyamar" — pertanyaan
         * yang muncul saat ada sengketa, dan tidak bisa dijawab dari riwayat
         * yang tersebar di sepuluh tabel.
         */
        Schema::create('log_aktivitas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            // Admin di balik layar; null berarti tindakan biasa, bukan impersonasi.
            $table->foreignUuid('impersonasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('impersonasi_sesi_id')->nullable()->constrained('impersonasi_sesi')->nullOnDelete();
            $table->string('aksi', 60);
            $table->string('subjek_tipe')->nullable();
            $table->uuid('subjek_id')->nullable();
            $table->text('keterangan')->nullable();
            $table->json('konteks')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['impersonasi_oleh', 'created_at']);
            $table->index(['subjek_tipe', 'subjek_id']);
        });

        // Jejak impersonasi menempel langsung pada riwayat modulnya, supaya
        // layar detail bisa menampilkan "disetujui oleh Ketua (melalui
        // impersonasi oleh Administrator)" tanpa menggabungkan tabel lain.
        Schema::table('tagihan_riwayat', function (Blueprint $table) {
            $table->foreignUuid('impersonasi_oleh')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('narasi_versi', function (Blueprint $table) {
            $table->foreignUuid('impersonasi_oleh')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('komentar', function (Blueprint $table) {
            $table->foreignUuid('impersonasi_oleh')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['tagihan_riwayat', 'narasi_versi', 'komentar'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->dropConstrainedForeignId('impersonasi_oleh');
            });
        }

        Schema::dropIfExists('log_aktivitas');
        Schema::dropIfExists('impersonasi_sesi');
    }
};
