<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 28 butir Data Kinerja Program Studi.
 *
 * `label_tabel` sengaja disimpan terpisah dari `no` dan tidak boleh diturunkan
 * darinya: Buku 3 melompati "Tabel 13", sehingga butir ke-13 berlabel
 * "Tabel 14" dan butir ke-28 berlabel "Tabel 29". Menurunkan salah satu dari
 * yang lain akan membuat rujukan ke Buku 3 meleset satu nomor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dkps_butir', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedTinyInteger('no')->unique();
            $table->string('nama', 200);
            $table->string('label_tabel', 20)->nullable();
            $table->string('jendela_data', 40);
            $table->text('keterangan');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dkps_butir');
    }
};
