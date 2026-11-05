{{--
    Katalog variabel DKPS dan nilainya pada periode aktif. Hanya-baca: angka
    di sini dibaca dari isian Tabel DKPS, dan setiap lembar tertaut ke layar
    tabelnya supaya sumber angka bisa diperiksa langsung.
--}}
<x-filament-panels::page>
    @php
        $periode = $this->periode();
        $baris = $this->baris();
    @endphp

    @if ($periode === null)
        <x-filament::section>
            <x-slot name="heading">Tidak ada periode aktif.</x-slot>
            Tetapkan satu periode berstatus &ldquo;berjalan&rdquo; lebih dulu.
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">{{ $periode->nama }} · TS {{ $periode->ts_tahun }}</x-slot>
            <x-slot name="description">
                &ldquo;—&rdquo; berarti lembar sumbernya belum berisi baris, berbeda dari 0 yang berarti
                lembar terisi tetapi memang nol.
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                        <tr>
                            <th class="py-2 pe-3 font-medium">Kode</th>
                            <th class="py-2 pe-3 font-medium">Label</th>
                            <th class="py-2 pe-3 text-right font-medium">Nilai</th>
                            <th class="py-2 pe-3 font-medium">Lembar</th>
                            <th class="py-2 pe-3 text-right font-medium">Baris</th>
                            <th class="py-2 pe-3 text-right font-medium">Belum terverifikasi</th>
                            <th class="py-2 font-medium">Rumus</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($baris as $b)
                            <tr>
                                <td class="py-2 pe-3 font-mono text-xs">{{ $b['kode'] }}</td>
                                <td class="py-2 pe-3">{{ $b['label'] }}</td>
                                <td class="py-2 pe-3 text-right tabular-nums">
                                    @if ($b['nilai'] === null)
                                        —
                                        @if ($b['catatan'])
                                            <span class="block text-xs text-gray-500">{{ $b['catatan'] }}</span>
                                        @endif
                                    @else
                                        {{ $b['nilai'] }}
                                    @endif
                                </td>
                                <td class="py-2 pe-3">
                                    @foreach ($b['lembar'] as $l)
                                        <a href="{{ $l['url'] }}"
                                            class="text-primary-600 hover:underline dark:text-primary-400">{{ $l['kode'] }}</a>@if (! $loop->last), @endif
                                    @endforeach
                                </td>
                                <td class="py-2 pe-3 text-right tabular-nums">{{ $b['jumlah_baris'] }}</td>
                                <td class="py-2 pe-3 text-right tabular-nums">{{ $b['belum_terverifikasi'] }}</td>
                                <td class="py-2">{{ $b['rumus'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
