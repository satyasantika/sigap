<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DKPS 3.0: kode tabel, skema lembar, dan pokja pemilik.
 *
 * Penomoran DKPS 3.0 bergeser dari penomoran lama (28 butir): tabel baru
 * "Tabel 4 Karya Inovatif" menyisip, sehingga nomor lama 4 ke atas naik satu.
 * Karena itu butir dan baris dicocokkan lewat `kode` yang stabil, bukan lewat
 * `no` — supaya baris isian lama tidak tertukar ke tabel yang salah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dkps_butir', function (Blueprint $table) {
            $table->string('kode', 8)->nullable()->after('no');
            $table->string('pokja_kode', 20)->nullable();
            $table->json('elemen')->nullable();
            $table->json('lembar')->nullable();
        });

        // `kode` baris lama diturunkan dari `no` lama, BUKAN dari `label_tabel`:
        // label lama untuk Tabel 4-12 menunjuk isi yang berbeda dari DKPS 3.0
        // (lama "Tabel 4" = Kepuasan Mahasiswa, baru "Tabel 4" = Karya Inovatif).
        // Nomor lama 1-3 tetap; nomor lama 4 ke atas bergeser satu.
        DB::statement('UPDATE dkps_butir SET kode = CAST(no AS CHAR) WHERE no <= 3');
        DB::statement('UPDATE dkps_butir SET kode = CAST(no + 1 AS CHAR) WHERE no >= 4');

        Schema::table('dkps_butir', function (Blueprint $table) {
            $table->unique('kode');
        });

        Schema::table('dkps_baris', function (Blueprint $table) {
            $table->string('lembar_kode', 8)->nullable()->index();
            $table->foreignUuid('pokja_id')->nullable()->constrained('pokja')->nullOnDelete();
        });

        DB::statement(
            'UPDATE dkps_baris SET lembar_kode = '
            .'(SELECT kode FROM dkps_butir WHERE dkps_butir.id = dkps_baris.dkps_butir_id)'
        );
        // Tabel 1 kini berlembar 1.1, 1.2, 1.3; baris lama jatuh ke lembar pertama.
        DB::statement("UPDATE dkps_baris SET lembar_kode = '1.1' WHERE lembar_kode = '1'");
        // Tabel 24 (no lama 23) kini berlembar 24.1, 24.2; baris lama ke 24.1.
        // Satu-satunya tabel berlembar banyak lainnya, Tabel 4, baru di 3.0
        // sehingga tak punya baris lama. dkps:selaraskan menangkap sisanya.
        DB::statement("UPDATE dkps_baris SET lembar_kode = '24.1' WHERE lembar_kode = '24'");
    }

    public function down(): void
    {
        Schema::table('dkps_baris', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pokja_id');
            $table->dropIndex(['lembar_kode']);
            $table->dropColumn('lembar_kode');
        });

        Schema::table('dkps_butir', function (Blueprint $table) {
            $table->dropUnique(['kode']);
            $table->dropColumn(['kode', 'pokja_kode', 'elemen', 'lembar']);
        });
    }
};
