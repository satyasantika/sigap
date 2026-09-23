<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            <span class="inline-flex items-center gap-2">
                <span class="rounded bg-warning-100 px-2 py-0.5 text-xs font-bold tracking-wide text-warning-800 dark:bg-warning-500/20 dark:text-warning-200">
                    SIMULASI
                </span>
                Angka di halaman ini bukan nilai akreditasi
            </span>
        </x-slot>
        <x-slot name="description">
            Dua bentuk simulasi tersedia, keduanya bisa dibuat dan dihapus seperlunya.
            <strong>Pengandaian skor</strong> menghitung ulang NA memakai skor yang Anda
            andaikan — tabel <code class="text-xs">penilaian</code> tidak tersentuh sama sekali.
            <strong>Periode latihan</strong> menyalin kerangka periode sungguhan ke periode
            bertanda SIMULASI yang bisa dikerjakan bebas lalu dibuang utuh; ia tidak pernah
            muncul sebagai periode berjalan, dan tidak pernah masuk perhitungan NA sungguhan.
        </x-slot>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
