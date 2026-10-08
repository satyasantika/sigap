<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom impor massal pada users, mengikuti pola yang sama dengan bukti
 * dan dkps_baris: impor_batch_id menandai asal baris, kunci_normal
 * menyimpan kunci duplikasi (email ternormalkan) agar bisa dicari dan
 * diindeks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('impor_batch_id')->nullable()
                ->after('wajib_ganti_sandi')
                ->constrained('impor_batch')->nullOnDelete();
            $table->string('kunci_normal', 255)->nullable()->after('impor_batch_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('impor_batch_id');
            $table->dropColumn('kunci_normal');
        });
    }
};
