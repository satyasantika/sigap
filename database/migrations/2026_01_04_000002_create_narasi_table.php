<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Naskah LED: satu narasi per elemen per periode.
 *
 * narasi_versi bersifat append only, sama seperti tagihan_riwayat. Naskah
 * akreditasi ditulis berbulan-bulan oleh banyak orang; tanpa jejak versi,
 * kalimat yang hilang tidak bisa dikembalikan dan tidak ada yang tahu siapa
 * menghapusnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('narasi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prodi_id')->constrained('prodi')->cascadeOnDelete();
            $table->foreignUuid('periode_id')->constrained('periode')->cascadeOnDelete();
            $table->foreignUuid('elemen_id')->constrained('elemen')->cascadeOnDelete();
            $table->longText('isi')->nullable();
            $table->smallInteger('jumlah_kata')->default(0);
            $table->foreignUuid('penulis_id')->nullable()->constrained('users')->nullOnDelete();
            $table->smallInteger('versi')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['periode_id', 'elemen_id']);
        });

        Schema::create('narasi_versi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('narasi_id')->constrained('narasi')->cascadeOnDelete();
            $table->longText('isi')->nullable();
            $table->smallInteger('jumlah_kata')->default(0);
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['narasi_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('narasi_versi');
        Schema::dropIfExists('narasi');
    }
};
