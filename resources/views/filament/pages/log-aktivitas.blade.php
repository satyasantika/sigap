<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Catatan ini tidak bisa diubah</x-slot>
        <x-slot name="description">
            Baris di sini hanya bertambah. Model <code class="text-xs">LogAktivitas</code>
            menolak <code class="text-xs">update</code> dan <code class="text-xs">delete</code>
            pada tingkat peristiwa Eloquent, jadi tidak ada layar — dan tidak ada
            perintah artisan — yang bisa merapikannya di belakang.
            Kolom <strong>Sebenarnya</strong> terisi hanya bila tindakan itu dilakukan
            seseorang yang sedang menyamar.
        </x-slot>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
