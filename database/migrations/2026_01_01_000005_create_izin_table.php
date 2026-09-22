<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matriks izin: 24 aksi x 6 peran = 144 baris, disemai dari data/izin.json.
 *
 * Ini satu-satunya sumber kebenaran otorisasi. Yang membacanya hanya
 * app/Support/Izin.php. Tidak ada perbandingan peran di controller, Resource,
 * atau Blade; tidak ada Gate::before; tidak ada peran super.
 * Lihat AGENTS.md aturan 10 dan vibecoding/docs/08-auth-dan-izin.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('izin', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('aksi', 40);
            $table->string('peran', 20);
            $table->enum('nilai', ['ya', 'tidak', 'pokjanya', 'miliknya', 'pokja_data']);
            $table->timestamps();

            $table->unique(['aksi', 'peran']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('izin');
    }
};
