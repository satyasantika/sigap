<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu pengguna boleh memegang lebih dari satu peran.
 *
 * `peran` tetap ada sebagai PERAN UTAMA (label, warna, bawaan tampilan);
 * peran lain disimpan sebagai larik nilai enum di `peran_tambahan`. Wewenang
 * efektif adalah gabungan seluruhnya, dihitung di App\Support\Izin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('peran_tambahan')->nullable()->after('peran');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('peran_tambahan');
        });
    }
};
