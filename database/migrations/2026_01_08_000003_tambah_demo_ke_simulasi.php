<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demo hidup: periode latihan yang bisa dicoba orang luar dengan kode akses.
 *
 * Hanya berlaku bagi simulasi berjenis `periode` — demo adalah periode latihan
 * yang dibukakan pintunya, bukan jenis simulasi ketiga.
 *
 * Kode akses, bukan pintu terbuka. Halaman muka SIGAP publik di domain
 * fakultas; tombol yang langsung membuat sesi berarti sesi sungguhan untuk
 * siapa pun di internet, dan setiap celah otorisasi berubah menjadi celah yang
 * bisa dicoba tanpa akun. Kode dibagikan admin kepada orang yang memang perlu:
 * asesor, anggota baru, pimpinan yang ingin melihat.
 *
 * Masa berlaku wajib ada. Demo yang dibuat sekali lalu dilupakan adalah pintu
 * yang dibiarkan terbuka bertahun-tahun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('simulasi', function (Blueprint $table) {
            $table->string('kode_demo', 24)->nullable()->unique()->after('periode_sandbox_id');
            $table->timestamp('demo_berlaku_sampai')->nullable()->after('kode_demo');
        });
    }

    public function down(): void
    {
        Schema::table('simulasi', function (Blueprint $table) {
            $table->dropUnique(['kode_demo']);
            $table->dropColumn(['kode_demo', 'demo_berlaku_sampai']);
        });
    }
};
