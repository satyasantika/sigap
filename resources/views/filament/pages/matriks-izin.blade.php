{{--
    Matriks izin, hanya-baca. Sumber kebenarannya data/izin.json; halaman ini
    hanya menampilkannya apa adanya supaya siapa pun bisa memeriksa sendiri
    mengapa sebuah tombol tidak muncul.
--}}
<x-filament-panels::page>
    @php
        $matriks = $this->matriks();
        $labelAksi = $this->labelAksi();
        $peran = $this->peran();
        $lingkup = $this->lingkup();

        $gaya = [
            'ya' => 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400',
            'tidak' => 'bg-gray-100 text-gray-400 dark:bg-white/5 dark:text-gray-500',
            'pokjanya' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400',
            'miliknya' => 'bg-info-50 text-info-700 dark:bg-info-500/10 dark:text-info-400',
            'pokja_data' => 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400',
        ];
    @endphp

    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Cara membaca tabel ini</x-slot>
            <x-slot name="description">
                {{ $this->jumlahSel() }} sel: {{ count($matriks) }} aksi &times; {{ count($peran) }} peran.
                Tabel ini tidak bisa disunting di sini. Untuk mengubahnya, sunting
                <code class="text-xs">data/izin.json</code> lalu jalankan seeder.
            </x-slot>

            <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($lingkup as $nilai => $arti)
                    <div class="flex gap-2">
                        <dt>
                            <span @class(['rounded px-2 py-0.5 text-xs font-medium whitespace-nowrap', $gaya[$nilai] ?? ''])>
                                {{ $nilai }}
                            </span>
                        </dt>
                        <dd class="text-sm text-gray-600 dark:text-gray-400">{{ $arti }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Seluruh aksi</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-white/10">
                            <th class="py-2 pr-4 text-left font-medium">Aksi</th>
                            @foreach ($peran as $p)
                                <th class="px-2 py-2 text-center font-medium whitespace-nowrap">
                                    {{ $p->label() }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($matriks as $aksi => $sel)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="py-2 pr-4">
                                    <div class="font-medium">{{ $labelAksi[$aksi] ?? $aksi }}</div>
                                    <code class="text-xs text-gray-500 dark:text-gray-400">{{ $aksi }}</code>
                                </td>
                                @foreach ($peran as $p)
                                    @php $nilai = $sel[$p->value] ?? '—'; @endphp
                                    <td class="px-2 py-2 text-center">
                                        <span @class(['rounded px-2 py-0.5 text-xs font-medium whitespace-nowrap', $gaya[$nilai] ?? ''])>
                                            {{ $nilai }}
                                        </span>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
