<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot murni: kunci utama gabungan, tanpa id sendiri
 * (vibecoding/docs/02-skema-data.md, ketentuan turunan butir 4).
 *
 * Keanggotaan di sini yang menentukan lingkup `pokjanya` dan `pokja_data`
 * pada kelas Izin — bukan kolom `peran` di tabel users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pokja_user', function (Blueprint $table) {
            $table->foreignUuid('pokja_id')->constrained('pokja')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('peran_dalam_pokja', 40)->nullable();

            $table->primary(['pokja_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pokja_user');
    }
};
