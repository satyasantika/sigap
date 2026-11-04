{{--
    Satu lembar DKPS bergaya Excel. Kepala, tipe sel, dan kolom hitung dari
    SkemaLembar; setiap sel yang berubah langsung menyimpan barisnya lewat
    hook `updated` di komponen — tidak ada tombol simpan besar.

    Kolom Bukti, Sumber, Verifikasi, dan Aksi di kanan berada di luar format
    Excel, jadi tidak diberi nomor kolom dan dibedakan warnanya.
--}}
@php
    $skema = $this->skema();
    $kolom = $skema->kolom();
    $kepala = $skema->kepala();
    $bolehIsi = $this->bolehIsi;
    $tetap = $skema->adaBarisTetap();
    $barisTampil = $this->barisTampil();
    $ringkasan = $this->barisRingkasan($barisTampil);
    $lebar = count($kolom) + 5;
    $sumberPilihan = \App\Enums\SumberData::pilihan();
@endphp
<div class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
        @if ($bolehIsi)
            <label class="flex items-center gap-2">
                <span class="text-gray-600 dark:text-gray-400">Sumber baris baru</span>
                <select wire:model.live="sumberBaru" class="rounded border-gray-300 py-1 text-sm dark:border-white/10 dark:bg-white/5">
                    @foreach ($sumberPilihan as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <span class="text-gray-500 dark:text-gray-400">Sel kuning diisi; setiap sel tersimpan saat Anda pindah ke sel lain.</span>
        @else
            <span class="rounded bg-gray-100 px-2 py-1 text-gray-600 dark:bg-white/5 dark:text-gray-400">
                Hanya-baca: Anda tidak berwenang mengisi lembar ini.
            </span>
        @endif
    </div>

    <div class="overflow-x-auto">
        <table class="sigap-dkps">
            <thead>
                @foreach ($kepala as $i => $barisKepala)
                    <tr>
                        @foreach ($barisKepala as $sel)
                            <th @if ($sel['colspan'] > 1) colspan="{{ $sel['colspan'] }}" @endif @if ($sel['rowspan'] > 1) rowspan="{{ $sel['rowspan'] }}" @endif>{{ $sel['label'] }}</th>
                        @endforeach
                        @if ($i === 0)
                            <th rowspan="{{ count($kepala) + 1 }}" class="sigap-kolom-tambahan sigap-kolom-tambahan-awal">Bukti</th>
                            <th rowspan="{{ count($kepala) + 1 }}" class="sigap-kolom-tambahan">Sumber</th>
                            <th rowspan="{{ count($kepala) + 1 }}" class="sigap-kolom-tambahan">Verifikasi</th>
                            <th rowspan="{{ count($kepala) + 1 }}" class="sigap-kolom-tambahan">Aksi</th>
                        @endif
                    </tr>
                @endforeach
                <tr class="sigap-nomor-excel">
                    <th>{{ $this->nomorKolomPertama() }}</th>
                    @foreach ($kolom as $k)
                        <th>{{ $k['nomor_excel'] ?? '' }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php $kelompokSebelumnya = null; @endphp
                @forelse ($barisTampil as $r)
                    @php
                        $id = $r['id'];
                        $model = $r['model'];
                        $bisaSunting = $bolehIsi && ($model === null || ! $model->terverifikasi() || $this->bolehVerifikasi($model));
                        $galatBaris = $galat[$id] ?? [];
                    @endphp

                    @if ($r['kelompok'] !== null && $r['kelompok'] !== $kelompokSebelumnya)
                        <tr wire:key="kelompok-{{ $id }}"><td colspan="{{ $lebar }}" class="sigap-baris-kelompok">{{ $r['kelompok'] }}</td></tr>
                    @endif
                    @php $kelompokSebelumnya = $r['kelompok']; @endphp

                    <tr wire:key="baris-{{ $id }}">
                        <td class="sigap-sel-label">{{ $r['nomor'] }}</td>
                        @foreach ($kolom as $k)
                            @php
                                $kunci = $k['kunci'];
                                $nilai = $r['isian'][$kunci] ?? null;
                                $galatSel = $galatBaris[$kunci] ?? null;
                                $jalurSel = $r['jalur'].'.'.$kunci;
                            @endphp
                            @if (in_array($kunci, $r['nonaktif'], true))
                                <td class="sigap-sel-nonaktif" title="Tidak diisi untuk baris ini"></td>
                            @elseif ($k['tipe'] === 'hitung')
                                <td class="sigap-sel-hitung">{{ \App\Livewire\LembarDkps::tampil($k, $r['hitung'][$kunci] ?? null) }}</td>
                            @elseif (! $bisaSunting)
                                <td @class(['sigap-sel-galat' => $galatSel])>{{ \App\Livewire\LembarDkps::tampil($k, $nilai) }}</td>
                            @else
                                <td class="sigap-sel-isian{{ $galatSel ? ' sigap-sel-galat' : '' }}">
                                    @switch($k['tipe'])
                                        @case('angka')
                                        @case('tahun')
                                            <input type="number" step="1" wire:model.blur="{{ $jalurSel }}" aria-label="{{ $k['label'] }}">
                                            @break
                                        @case('desimal')
                                            <input type="number" step="any" wire:model.blur="{{ $jalurSel }}" aria-label="{{ $k['label'] }}">
                                            @break
                                        @case('tanggal')
                                            <input type="date" wire:model.blur="{{ $jalurSel }}" aria-label="{{ $k['label'] }}">
                                            @break
                                        @case('tautan')
                                            <input type="url" wire:model.blur="{{ $jalurSel }}" aria-label="{{ $k['label'] }}" placeholder="https://">
                                            @break
                                        @case('pilihan')
                                            <select wire:model.live="{{ $jalurSel }}" aria-label="{{ $k['label'] }}">
                                                <option value="">—</option>
                                                @foreach ($k['pilihan'] ?? [] as $p)
                                                    <option value="{{ $p }}">{{ $p }}</option>
                                                @endforeach
                                            </select>
                                            @break
                                        @case('centang')
                                            <label class="sigap-centang"><input type="checkbox" wire:model.live="{{ $jalurSel }}" aria-label="{{ $k['label'] }}"><span>V</span></label>
                                            @break
                                        @default
                                            <textarea rows="1" wire:model.blur="{{ $jalurSel }}" aria-label="{{ $k['label'] }}"></textarea>
                                    @endswitch
                                    @if ($galatSel)
                                        <p class="sigap-pesan-galat">{{ $galatSel }}</p>
                                    @endif
                                </td>
                            @endif
                        @endforeach

                        {{-- Bukti --}}
                        <td class="sigap-kolom-tambahan sigap-kolom-tambahan-awal">
                            @if ($model)
                                <span>{{ $model->bukti->count() }} bukti</span>
                                @if ($bolehIsi)
                                    <button type="button" wire:click="bukaBukti('{{ $id }}')" class="sigap-tombol-kecil">Tautkan</button>
                                @endif
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>

                        {{-- Sumber --}}
                        <td class="sigap-kolom-tambahan">
                            @php $sumber = $model?->sumber ?? \App\Enums\SumberData::tryFrom($sumberBaru); @endphp
                            @if ($sumber)
                                <x-filament::badge :color="$sumber->warna()" size="sm">{{ $sumber->value }}</x-filament::badge>
                            @endif
                        </td>

                        {{-- Verifikasi --}}
                        <td class="sigap-kolom-tambahan">
                            @if ($model?->terverifikasi())
                                <span class="text-success-600 dark:text-success-400">✓ {{ $model->verifikator?->nama_lengkap ?? $model->verifikator?->name }}</span>
                            @elseif ($model && $this->bolehVerifikasi($model))
                                <button type="button" wire:click="verifikasi('{{ $id }}')" class="sigap-tombol-kecil">Verifikasi</button>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>

                        {{-- Aksi --}}
                        <td class="sigap-kolom-tambahan">
                            @if ($model && $this->disimpanTerakhir === $model->id)
                                <span x-data="{ tampak: true }" x-init="setTimeout(() => tampak = false, 2000)" x-show="tampak" x-transition.opacity class="text-success-600 dark:text-success-400">Tersimpan</span>
                            @endif
                            @if (str_starts_with($id, 'baru:') && ($wajib = $this->kolomWajibKosong($r['isian'])) !== [])
                                <span class="block text-xs text-gray-500 dark:text-gray-400">Belum tersimpan — isi {{ implode(', ', $wajib) }}.</span>
                            @endif
                            @if ($bisaSunting && (str_starts_with($id, 'baru:') || ($model && ! $model->terverifikasi())))
                                <button type="button" wire:click="hapusBaris('{{ $id }}')" wire:confirm="Hapus baris ini?" class="sigap-tombol-kecil sigap-tombol-bahaya">Hapus</button>
                            @endif
                            @if (isset($galatBaris['_baris']))
                                <p class="sigap-pesan-galat">{{ $galatBaris['_baris'] }}</p>
                            @endif
                        </td>
                    </tr>

                    @foreach ($ringkasan as $sub)
                        @if ($sub['setelah'] !== null && $sub['setelah'] === $r['kunciBaris'])
                            @include('livewire.lembar-dkps-ringkasan', ['sub' => $sub, 'kolom' => $kolom])
                        @endif
                    @endforeach
                @empty
                    <tr><td colspan="{{ $lebar }}" class="text-center text-gray-500 dark:text-gray-400">Belum ada baris.</td></tr>
                @endforelse

                @foreach ($ringkasan as $sub)
                    @if ($sub['setelah'] === null)
                        @include('livewire.lembar-dkps-ringkasan', ['sub' => $sub, 'kolom' => $kolom])
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
        @if ($bolehIsi && ! $tetap)
            <x-filament::button size="sm" color="gray" icon="heroicon-o-plus" wire:click="tambahBaris">Tambah baris</x-filament::button>
        @else
            <span></span>
        @endif

        @if (! $tetap && $this->jumlahHalaman() > 1)
            <div class="flex items-center gap-2">
                <x-filament::button size="xs" color="gray" wire:click="$set('halaman', {{ $halaman - 1 }})" :disabled="$halaman <= 1">Sebelumnya</x-filament::button>
                <span>Halaman {{ $halaman }} dari {{ $this->jumlahHalaman() }} · {{ $this->totalBaris() }} baris</span>
                <x-filament::button size="xs" color="gray" wire:click="$set('halaman', {{ $halaman + 1 }})" :disabled="$halaman >= $this->jumlahHalaman()">Berikutnya</x-filament::button>
            </div>
        @elseif (! $tetap)
            <span class="text-gray-500 dark:text-gray-400">{{ $this->totalBaris() }} baris tersimpan</span>
        @endif
    </div>

    @if ($this->barisLama->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">Baris lama yang perlu dipindahkan</x-slot>
            <x-slot name="description">
                Baris ini diisi sebelum format DKPS 3.0 dan kolomnya tidak cocok dengan lembar ini.
                Salin isinya ke kolom yang benar di atas, lalu hapus baris lamanya.
            </x-slot>
            <div class="space-y-3">
                @foreach ($this->barisLama as $lama)
                    <div wire:key="lama-{{ $lama->id }}" class="rounded border border-gray-200 p-3 text-sm dark:border-white/10">
                        <dl class="grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-[max-content_1fr]">
                            @foreach ($lama->data ?? [] as $kunci => $isi)
                                <dt class="font-medium text-gray-600 dark:text-gray-400">{{ $kunci }}</dt>
                                <dd>{{ is_scalar($isi) ? $isi : json_encode($isi, JSON_UNESCAPED_UNICODE) }}</dd>
                            @endforeach
                        </dl>
                        @if (isset($galat[$lama->id]['_baris']))
                            <p class="sigap-pesan-galat">{{ $galat[$lama->id]['_baris'] }}</p>
                        @endif
                        @if ($bolehIsi)
                            <div class="mt-2">
                                <x-filament::button size="xs" color="danger" wire:click="hapusBaris('{{ $lama->id }}')" wire:confirm="Hapus baris lama ini?">Hapus baris lama</x-filament::button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    <x-filament::modal id="{{ $this->idModalBukti() }}" width="lg">
        <x-slot name="heading">Tautkan bukti ke baris</x-slot>
        <div class="space-y-3 text-sm">
            <label class="block">
                <span class="font-medium">URL bukti</span>
                <input type="url" wire:model="buktiUrl" class="mt-1 w-full rounded border-gray-300 dark:border-white/10 dark:bg-white/5" placeholder="https://drive.google.com/…">
                @error('buktiUrl') <span class="text-danger-600">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="font-medium">Judul</span>
                <input type="text" wire:model="buktiJudul" class="mt-1 w-full rounded border-gray-300 dark:border-white/10 dark:bg-white/5">
                @error('buktiJudul') <span class="text-danger-600">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="font-medium">Tanggal kejadian</span>
                <input type="date" wire:model="buktiTanggal" class="mt-1 w-full rounded border-gray-300 dark:border-white/10 dark:bg-white/5">
                @error('buktiTanggal') <span class="text-danger-600">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="font-medium">Sumber</span>
                <select wire:model="buktiSumber" class="mt-1 w-full rounded border-gray-300 dark:border-white/10 dark:bg-white/5">
                    @foreach ($sumberPilihan as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('buktiSumber') <span class="text-danger-600">{{ $message }}</span> @enderror
            </label>
        </div>
        <x-slot name="footerActions">
            <x-filament::button wire:click="tautkanBukti">Tautkan</x-filament::button>
        </x-slot>
    </x-filament::modal>
</div>
