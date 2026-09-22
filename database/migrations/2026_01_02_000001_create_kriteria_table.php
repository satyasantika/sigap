<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sembilan kriteria IAPSK 3.0. Disemai dari data/kriteria.json, tidak pernah
 * disunting lewat antarmuka — Resource-nya hanya-baca.
 *
 * `bobot` di sini adalah jumlah bobot elemen di dalamnya; totalnya 100,00.
 * Angkanya redundan terhadap tabel elemen dengan sengaja: kalau keduanya
 * berselisih, itu tanda seeder atau datanya rusak, dan ada uji yang menjaganya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kriteria', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('kode', 2)->unique();
            $table->string('nama', 120);
            $table->unsignedTinyInteger('urutan');
            $table->decimal('bobot', 5, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kriteria');
    }
};
