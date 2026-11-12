{{--
    Daftar Tabel DKPS per pokja pemilik. Satu kartu per pokja; satu baris per
    lembar, tertaut ke layar tabelnya. Isian ada di TabelDkpsLayar; di sini hanya
    delegasi: tombol Anggota per pokja dan ikon Tugaskan per tabel.
--}}
<x-filament-panels::page>
    @php
        $periode = $this->periode();
        $progres = $this->progres();
    @endphp

    @if ($periode === null)
        <x-filament::section>
            <x-slot name="heading">Belum ada periode berjalan</x-slot>
            Tetapkan satu periode berstatus &ldquo;berjalan&rdquo; lebih dulu.
        </x-filament::section>
    @elseif ($progres === [])
        <x-filament::section>
            <x-slot name="heading">Belum ada tabel</x-slot>
            Anda belum terdaftar di pokja yang memegang tabel DKPS.
        </x-filament::section>
    @else
        @foreach ($progres as $p)
            <x-filament::section>
                <x-slot name="heading">{{ $p['kode'] }} · {{ $p['nama'] }}</x-slot>
                @if ($p['sebagian'])
                    {{-- Pokja lain: hanya tabel yang dititipkan, tanpa angka progres pokjanya. --}}
                    <x-slot name="description">Ditugaskan kepada Anda</x-slot>
                @else
                    <x-slot name="description">
                        {{ $p['terverifikasi'] }} / {{ $p['total'] }} lembar terverifikasi
                        · {{ $p['lengkap'] }} lengkap · {{ $p['terisi'] }} terisi
                    </x-slot>

                    <x-slot name="afterHeader">
                        {{ ($this->anggotaAction)(['pokja' => $p['kode']]) }}
                    </x-slot>

                    <div class="mb-3 h-2 rounded-full bg-gray-200 dark:bg-white/10">
                        <div class="h-2 rounded-full bg-success-500" style="width: {{ $p['rasio'] * 100 }}%"></div>
                    </div>
                @endif

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-200 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <tr>
                                <th class="py-2 pe-3 font-medium">Lembar</th>
                                <th class="py-2 pe-3 text-right font-medium">Baris</th>
                                <th class="py-2 pe-3 text-right font-medium">Lengkap</th>
                                <th class="py-2 pe-3 text-right font-medium">Terverifikasi</th>
                                <th class="py-2 pe-3 font-medium">Status</th>
                                <th class="py-2 pe-3 font-medium">Tenggat</th>
                                <th class="py-2 font-medium">Penanggung jawab</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($p['tabel'] as $t)
                                @php($tagihan = $this->tagihanTabel($t['butir']))
                                @foreach ($t['lembar'] as $kode => $s)
                                    @php([$label, $warna] = \App\Filament\Pages\DkpsLayar::lencana($s['status']))
                                    <tr>
                                        <td class="py-2 pe-3">
                                            <a href="{{ \App\Filament\Pages\TabelDkpsLayar::urlUntuk($t['butir']->kode, $kode) }}"
                                                class="text-primary-600 hover:underline dark:text-primary-400">
                                                {{ $t['butir']->lembarMentah($kode)['judul'] }}
                                            </a>
                                        </td>
                                        <td class="py-2 pe-3 text-right tabular-nums">
                                            {{ $s['baris'] }}@if ($s['target'] !== null) / {{ $s['target'] }}@endif
                                        </td>
                                        <td class="py-2 pe-3 text-right tabular-nums">{{ $s['lengkap'] }}</td>
                                        <td class="py-2 pe-3 text-right tabular-nums">{{ $s['terverifikasi'] }}</td>
                                        <td class="py-2 pe-3">
                                            <x-filament::badge :color="$warna" size="sm" class="w-fit">{{ $label }}</x-filament::badge>
                                        </td>
                                        @if ($loop->first)
                                            <td class="py-2 pe-3 align-top" rowspan="{{ count($t['lembar']) }}">
                                                {{ $tagihan?->tenggat?->translatedFormat('d F Y') ?? '—' }}
                                            </td>
                                            <td class="py-2 align-top" rowspan="{{ count($t['lembar']) }}">
                                                <div class="flex items-center gap-1">
                                                    @if ($tagihan?->penanggungJawab)
                                                        <span>{{ $tagihan->penanggungJawab->nama_lengkap }}</span>
                                                    @elseif ($tagihan)
                                                        <span class="text-gray-500 dark:text-gray-400">Seluruh pokja</span>
                                                    @else
                                                        <span>—</span>
                                                    @endif
                                                    {{ ($this->tugaskanAction)(['butir' => $t['butir']->id]) }}
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endforeach
    @endif
</x-filament-panels::page>
