<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akun demo menjadi yatim, bukan lenyap, saat periode demonya dibuang.
 *
 * Semula `periode_demo_id` memakai `cascadeOnDelete`: membuang periode latihan
 * ikut menghapus akun demonya di tingkat basis data. Rapi — sampai akun itu
 * pernah dipakai. Begitu ia masuk sekali, `log_aktivitas` menunjuk kepadanya,
 * dan penghapusan berantai menabrak kendala kunci asing:
 *
 *   Cannot delete or update a parent row: a foreign key constraint fails
 *   (`log_aktivitas`, CONSTRAINT `log_aktivitas_user_id_foreign`)
 *
 * Uji tidak menangkapnya karena uji menutup demo yang belum pernah dimasuki.
 * Yang menangkapnya adalah mencobanya sungguhan di peramban.
 *
 * Sekarang `periode_demo_id` menjadi null, akunnya tetap ada sebagai yatim,
 * dan `LingkupPeriode` sudah menolak akun demo tanpa periode: ia melihat
 * kosong. Jejaknya utuh, pintunya tertutup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['periode_demo_id']);
            $table->foreign('periode_demo_id')->references('id')->on('periode')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['periode_demo_id']);
            $table->foreign('periode_demo_id')->references('id')->on('periode')->cascadeOnDelete();
        });
    }
};
