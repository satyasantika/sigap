<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 15 rumus instrumen. Disimpan sebagai rujukan yang dibaca manusia, BUKAN
 * sebagai ekspresi yang diurai mesin: `ekspresi` dan `aturan_skor` bercampur
 * notasi matematis, kalimat Indonesia, dan koma desimal, dan salah satunya
 * ("4 < RMS <= 5 -> berskala") bahkan belum punya rumus.
 *
 * Perhitungannya ditulis tangan satu metode per kode di tahap 5. Yang dibaca
 * dari tabel ini adalah AMBANGNYA, supaya perubahan instrumen kelak menjadi
 * perubahan data, bukan perubahan kode.
 *
 * `elemen_id` nullable karena NA adalah penjumlah global, tidak terikat elemen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rumus', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 20)->unique();
            $table->string('nama', 150);
            $table->foreignUuid('elemen_id')->nullable()->constrained('elemen')->nullOnDelete();
            $table->text('ekspresi');
            $table->json('variabel');
            $table->text('aturan_skor');
            $table->string('jendela', 40);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rumus');
    }
};
