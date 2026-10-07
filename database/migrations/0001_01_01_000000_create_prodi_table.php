<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prodi berjalan lebih dulu dari `users` karena `users.prodi_id` menunjuk ke
 * sini. Sekarang hanya ada satu prodi (PPG), tetapi tabelnya tetap dibuat
 * sejak awal — aturan proyek 4: menambahkan `prodi_id` belakangan berarti
 * migrasi ulang seluruh basis data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prodi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 30)->unique();
            $table->string('nama', 150);
            $table->enum('jenjang', ['ppg', 's1', 's2', 's3']);
            $table->string('upps', 150);
            $table->string('perguruan_tinggi', 150);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prodi');
    }
};
