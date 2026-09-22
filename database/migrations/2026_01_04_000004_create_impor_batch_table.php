<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu baris per penjalanan impor tempel-tabel.
 *
 * `ringkasan` menyimpan nilai baris SEBELUM diperbarui, supaya "Batalkan
 * impor" bisa mengembalikannya. Dokumen 09 menuntut pembatalan itu tetapi
 * tidak menyebut di mana nilai lamanya disimpan; di sinilah tempatnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impor_batch', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('periode_id')->constrained('periode')->cascadeOnDelete();
            $table->string('profil', 50);
            $table->foreignUuid('dijalankan_oleh')->constrained('users')->restrictOnDelete();
            $table->integer('jumlah_baris')->default(0);
            $table->integer('jumlah_impor')->default(0);
            $table->integer('jumlah_lewati')->default(0);
            $table->integer('jumlah_perbarui')->default(0);
            $table->json('ringkasan')->nullable();
            $table->timestamp('dibatalkan_pada')->nullable();
            $table->foreignUuid('dibatalkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('bukti', function (Blueprint $table) {
            $table->foreign('impor_batch_id')->references('id')->on('impor_batch')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bukti', function (Blueprint $table) {
            $table->dropForeign(['impor_batch_id']);
        });

        Schema::dropIfExists('impor_batch');
    }
};
