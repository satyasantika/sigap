{{--
    Empat langkah dalam SATU modal. Perhatikan tidak ada satu pun aksi di sini
    yang menutup modalnya: orang yang kehilangan tempelannya di langkah ketiga
    tidak akan mencoba lagi.
--}}
<div class="space-y-4">
    @if ($galat)
        <div class="rounded-lg border border-danger-300 bg-danger-50 p-4 text-sm text-danger-700 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-400">
            {{ $galat }}
        </div>
    @endif

    {{-- Langkah 1: tempel --}}
    @if ($langkah === 1)
        <div class="space-y-3">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Salin dari Excel atau Google Sheets, lalu tempel di sini. Pemisah tab, titik koma,
                dan koma dikenali otomatis. Maksimal {{ \App\Support\Impor\PenguraiTempelan::BATAS_BARIS }} baris.
            </p>
            <textarea wire:model="tempelan" rows="12"
                class="w-full rounded-lg border-gray-300 font-mono text-xs dark:border-white/10 dark:bg-white/5"
                placeholder="Judul&#9;Tautan&#9;Tanggal kejadian&#9;Sumber"></textarea>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="barisPertamaKepala" class="rounded">
                Baris pertama adalah judul kolom
            </label>
            <x-filament::button wire:click="uraikan" :disabled="blank($tempelan)">Lanjut: petakan kolom</x-filament::button>
        </div>
    @endif

    {{-- Langkah 2: petakan --}}
    @if ($langkah === 2)
        <div class="space-y-3">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ count($barisMentah) }} baris terbaca. Cocokkan kolom tempelan dengan kolom sistem.
            </p>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b dark:border-white/10">
                        <th class="py-2 text-left">Kolom sistem</th>
                        <th class="py-2 text-left">Kolom tempelan</th>
                        <th class="py-2 text-left">Contoh dari baris pertama</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->medan() as $kunci => $def)
                        <tr class="border-b dark:border-white/5">
                            <td class="py-2">
                                {{ $def['label'] }}
                                @if ($def['wajib'])
                                    <span class="text-danger-600">*</span>
                                @endif
                            </td>
                            <td class="py-2">
                                <select wire:model="pemetaan.{{ $kunci }}"
                                    @class(['rounded border text-sm dark:bg-white/5',
                                        'border-danger-400' => $def['wajib'] && ($pemetaan[$kunci] ?? null) === null,
                                        'border-gray-300 dark:border-white/10' => ! ($def['wajib'] && ($pemetaan[$kunci] ?? null) === null)])>
                                    <option value="">— tidak dipetakan —</option>
                                    @foreach ($kepala as $i => $judul)
                                        <option value="{{ $i }}">{{ $judul }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="py-2 font-mono text-xs text-gray-500">
                                {{ ($pemetaan[$kunci] ?? null) !== null ? ($barisMentah[0][$pemetaan[$kunci]] ?? '—') : $def['contoh'] }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="flex gap-2">
                <x-filament::button color="gray" wire:click="ulangi">Kembali</x-filament::button>
                <x-filament::button wire:click="pratinjaukan">Lanjut: pratinjau</x-filament::button>
            </div>
        </div>
    @endif

    {{-- Langkah 3: pratinjau dan deteksi duplikasi --}}
    @if ($langkah === 3)
        @php $r = $this->ringkasan(); @endphp
        <div class="space-y-3">
            <p class="text-sm font-medium">{{ $this->kalimatRingkasan() }}</p>

            <div class="flex flex-wrap gap-2 text-xs">
                <x-filament::button size="xs" color="success" wire:click="semua('baru', 'impor')">Impor semua yang baru</x-filament::button>
                <x-filament::button size="xs" color="gray" wire:click="semua('duplikat_basis_data', 'lewati')">Lewati semua duplikat</x-filament::button>
                <x-filament::button size="xs" color="warning" wire:click="semua('duplikat_basis_data', 'perbarui')">Perbarui semua duplikat</x-filament::button>
            </div>

            <div class="max-h-96 overflow-auto rounded-lg border dark:border-white/10">
                <table class="w-full text-xs">
                    <thead class="sticky top-0 bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="p-2 text-left">#</th>
                            <th class="p-2 text-left">Status</th>
                            <th class="p-2 text-left">Judul</th>
                            <th class="p-2 text-left">Tautan</th>
                            <th class="p-2 text-left">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pratinjau as $baris)
                            @php
                                $warna = match ($baris['status']) {
                                    'baru' => 'text-success-700 dark:text-success-400',
                                    'duplikat_tempelan' => 'text-warning-700 dark:text-warning-400',
                                    'duplikat_basis_data' => 'text-orange-700 dark:text-orange-400',
                                    default => 'text-danger-700 dark:text-danger-400',
                                };
                            @endphp
                            <tr class="border-b dark:border-white/5">
                                <td class="p-2">{{ $baris['no'] }}</td>
                                <td class="p-2 {{ $warna }}">
                                    {{ str_replace('_', ' ', $baris['status']) }}
                                    @if ($baris['galat'])
                                        <div class="mt-1 text-[11px]">{{ implode(' ', $baris['galat']) }}</div>
                                    @endif
                                </td>
                                <td class="p-2">{{ $baris['data']['judul'] ?? '—' }}</td>
                                <td class="p-2 font-mono text-[11px]">{{ \Illuminate\Support\Str::limit($baris['data']['url_kanonik'] ?? '—', 48) }}</td>
                                <td class="p-2">
                                    @if ($baris['status'] === 'galat')
                                        <span class="text-gray-400">dilewati</span>
                                    @else
                                        <select wire:model="keputusan.{{ $baris['no'] }}" class="rounded border-gray-300 text-xs dark:border-white/10 dark:bg-white/5">
                                            <option value="impor">impor</option>
                                            <option value="lewati">lewati</option>
                                            @if ($baris['status'] === 'duplikat_basis_data')
                                                <option value="perbarui">perbarui</option>
                                            @endif
                                        </select>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex gap-2">
                <x-filament::button color="gray" wire:click="ulangi">Mulai ulang</x-filament::button>
                <x-filament::button wire:click="jalankan">Jalankan impor</x-filament::button>
            </div>
        </div>
    @endif

    {{-- Langkah 4: hasil, tetap di modal yang sama --}}
    @if ($langkah === 4)
        <div class="space-y-3">
            <div class="rounded-lg border border-success-300 bg-success-50 p-4 text-sm dark:border-success-500/30 dark:bg-success-500/10">
                {{ $hasil }}
            </div>
            <div class="flex gap-2">
                <x-filament::button color="danger" wire:click="batalkanImpor">Batalkan impor ini</x-filament::button>
                <x-filament::button color="gray" wire:click="ulangi">Tempel lagi</x-filament::button>
            </div>
        </div>
    @endif
</div>
