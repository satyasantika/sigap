<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pemberitahuan basis data bawaan Laravel. Tidak ada di skeleton Laravel 13
 * (biasanya dibangkitkan `make:notifications-table`), jadi ditulis di sini
 * dengan kunci utama UUID sesuai aturan proyek 4b.
 *
 * `notifiable_id` sengaja uuid, bukan morphs() bawaan yang menghasilkan bigint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->uuid('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
