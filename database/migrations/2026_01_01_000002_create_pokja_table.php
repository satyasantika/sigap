<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pokja dimiliki per periode: pembagian elemen boleh berubah antarperiode
 * tanpa merusak riwayat periode sebelumnya.
 *
 * POKJA-DATA sengaja tidak memegang elemen satu pun (bobot 0,00). Ia memegang
 * 28 butir DKPS, penetapan TS, dan seluruh perhitungan rumus. Jangan menulis
 * uji yang menuntut setiap pokja punya minimal satu elemen — yang benar adalah
 * menguji keenam pokja membagi habis elemen 1..59.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pokja', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('periode_id')->constrained('periode')->cascadeOnDelete();
            $table->string('kode', 20);
            $table->string('nama', 120);
            $table->foreignUuid('koordinator_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['periode_id', 'kode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pokja');
    }
};
