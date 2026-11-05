{{--
    Satu tabel DKPS: kepala tabel, tab per lembar, lalu grid LembarDkps.
    Tab berupa tautan `?lembar=` — setiap lembar komponennya sendiri.
--}}
<x-filament-panels::page>
    @php
        $periode = $this->periode();
        $skema = $this->butir->skema($this->lembar);
        $tetangga = $this->tetangga();
        $tab = $this->tabLembar();
    @endphp

    <x-filament::section>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-[max-content_1fr]">
            <dt class="font-medium text-gray-600 dark:text-gray-400">Jendela data</dt>
            <dd>{{ $this->jendela() }}</dd>
            <dt class="font-medium text-gray-600 dark:text-gray-400">Pokja pemilik</dt>
            <dd>{{ $this->butir->pokja_kode ?? '—' }}@if ($nama = $this->namaPokja()) · {{ $nama }}@endif</dd>
            <dt class="font-medium text-gray-600 dark:text-gray-400">Dibaca elemen</dt>
            <dd>{{ collect($this->butir->elemen ?? [])->map(fn ($e) => 'E'.$e)->implode(', ') ?: '—' }}</dd>
            @if ($skema->catatan())
                <dt class="font-medium text-gray-600 dark:text-gray-400">Catatan</dt>
                <dd>{{ $skema->catatan() }}</dd>
            @endif
        </dl>

        @if ($tetangga['sebelum'] || $tetangga['sesudah'])
            <div class="mt-3 flex flex-wrap justify-between gap-2 text-sm">
                @if ($tetangga['sebelum'])
                    <a href="{{ \App\Filament\Pages\TabelDkpsLayar::urlUntuk($tetangga['sebelum']->kode) }}" class="text-primary-600 hover:underline dark:text-primary-400">← {{ $tetangga['sebelum']->label_tabel }} {{ $tetangga['sebelum']->nama }}</a>
                @else
                    <span></span>
                @endif
                @if ($tetangga['sesudah'])
                    <a href="{{ \App\Filament\Pages\TabelDkpsLayar::urlUntuk($tetangga['sesudah']->kode) }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $tetangga['sesudah']->label_tabel }} {{ $tetangga['sesudah']->nama }} →</a>
                @endif
            </div>
        @endif
    </x-filament::section>

    @if (count($tab) > 1)
        <x-filament::tabs>
            @foreach ($tab as $kode => $judul)
                <x-filament::tabs.item tag="a" :href="\App\Filament\Pages\TabelDkpsLayar::urlUntuk($this->butir->kode, $kode)" :active="$kode === $this->lembar">
                    {{ $judul }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>
    @endif

    @if ($periode === null)
        <x-filament::section>
            <x-slot name="heading">Belum ada periode berjalan</x-slot>
            Tetapkan satu periode berstatus &ldquo;berjalan&rdquo; lebih dulu.
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">{{ $skema->judul() }}</x-slot>
            @livewire(\App\Livewire\LembarDkps::class, [
                'butirId' => $this->butir->id,
                'kodeLembar' => $this->lembar,
                'periodeId' => $periode->id,
            ], key('lembar-'.$this->butir->kode.'-'.$this->lembar))
        </x-filament::section>
    @endif
</x-filament-panels::page>
