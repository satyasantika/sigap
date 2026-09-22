<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `ts_tahun` adalah SATU-SATUNYA sumber tahun acuan di seluruh aplikasi.
 * AGENTS.md aturan 3: tidak boleh ada tahun yang ditulis mati di kueri,
 * migrasi, atau logika — semuanya diturunkan dari kolom ini lewat JendelaTs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prodi_id')->constrained('prodi')->cascadeOnDelete();
            $table->string('nama', 80);
            $table->year('ts_tahun');
            $table->date('tanggal_target_unggah')->nullable();
            $table->string('versi_instrumen', 20)->default('IAPSK 3.0');
            $table->enum('status', ['persiapan', 'berjalan', 'dikunci', 'selesai'])
                ->default('persiapan');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['prodi_id', 'nama']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periode');
    }
};
