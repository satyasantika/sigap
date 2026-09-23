<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda periode sandbox.
 *
 * Tanpa kolom ini, periode latihan terlihat persis seperti periode sungguhan
 * di setiap daftar, setiap penyaring, dan setiap grafik. Satu periode simulasi
 * yang lolos ke laporan NA cukup untuk membuat seluruh angka SIGAP kehilangan
 * kepercayaan, dan kesalahan itu baru ketahuan setelah laporan dikirim.
 *
 * Bawaannya `false`: seluruh periode yang sudah ada adalah periode sungguhan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periode', function (Blueprint $table) {
            $table->boolean('simulasi')->default(false)->after('status');
            $table->index('simulasi');
        });
    }

    public function down(): void
    {
        Schema::table('periode', function (Blueprint $table) {
            $table->dropIndex(['simulasi']);
            $table->dropColumn('simulasi');
        });
    }
};
