{{--
    Bento sembilan ubin.

    Urutan DOM mengikuti urutan menumpuk di layar sempit yang ditetapkan
    dokumen 10: K1, K2, K9, K3, K4, K5, K6, K7, K8. Ubin daftar (K9) naik ke
    atas karena di ponsel orang membuka dasbor untuk tahu apa yang harus
    dikerjakan, bukan untuk mengagumi angka. Di layar lebar, `order-*`
    mengembalikannya ke posisi kisi yang semestinya.

    Setiap persen didampingi angka absolut, dan setiap warna semantik disertai
    label teks — mengandalkan warna saja menyingkirkan pembaca yang tidak
    membedakan merah dan hijau.
--}}
<x-filament-panels::page>
    @php
        $periode = $this->periode();
        $na = $this->na();
        $bobot = $this->bobotSelesai();
        $dkps = $this->dkps();
        $laju = $this->laju();
        $lajuAngka = $this->lajuAngka();
    @endphp

    @if ($periode === null)
        <x-filament::section>
            <x-slot name="heading">Belum ada periode berjalan</x-slot>
            Tetapkan satu periode berstatus &ldquo;berjalan&rdquo; di menu Pengaturan lebih dulu.
        </x-filament::section>
    @else
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">

            {{-- K1 Gerbang Unggul — 6×2. Tidak pernah dipisah dari K2. --}}
            @if ($this->lihat('K1') && $na)
                <div class="order-1 lg:col-span-6">
                    <x-filament::section>
                        <x-slot name="heading">Gerbang Unggul</x-slot>

                        <div class="flex flex-wrap items-baseline gap-3">
                            <span @class(['text-5xl font-bold tabular-nums',
                                'text-success-600 dark:text-success-400' => $na->warna() === 'success',
                                'text-warning-600 dark:text-warning-400' => $na->warna() === 'warning',
                                'text-info-600 dark:text-info-400' => $na->warna() === 'info',
                                'text-danger-600 dark:text-danger-400' => $na->warna() === 'danger'])>
                                {{ $this->angka($na->na) }}
                            </span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                Nilai Akreditasi proyeksi · surplus {{ $this->angka($na->surplus) }}
                            </span>
                        </div>

                        <p class="mt-1 text-base font-medium">{{ $na->kalimatStatus() }}</p>

                        {{-- Skala 100–400 dengan penanda di 200, 321, 361.
                             Bukan skala persen: ambangnya bukan persentase. --}}
                        <div class="relative mt-4 h-3 rounded-full bg-gray-200 dark:bg-white/10">
                            <div class="absolute inset-y-0 left-0 rounded-full bg-primary-500"
                                style="width: {{ $this->posisiNa($na->na) }}%"></div>
                            @foreach ($this->penandaSkala() as $nilai => $posisi)
                                <div class="absolute inset-y-0 w-px bg-gray-600 dark:bg-white/50"
                                    style="left: {{ $posisi }}%" title="Ambang {{ $nilai }}"></div>
                            @endforeach
                        </div>
                        <div class="mt-1 flex justify-between text-[11px] text-gray-500 dark:text-gray-400">
                            <span>100</span><span>200</span><span>321</span><span>361</span><span>400</span>
                        </div>

                        {{-- Lima titik syarat perlu di samping angkanya: progres
                             tinggi tanpa syarat perlu tetap bukan Unggul. --}}
                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Syarat perlu:</span>
                            @foreach ($this->titikSyaratPerlu() as $s)
                                <span @class(['inline-flex h-6 items-center rounded-full px-2 text-[11px] font-medium',
                                    'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-400' => $s['level']->memenuhiLima(),
                                    'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-400' => $s['level'] === \App\Enums\LevelSyaratPerlu::Tiga,
                                    'bg-gray-200 text-gray-600 dark:bg-white/10 dark:text-gray-400' => $s['level'] === \App\Enums\LevelSyaratPerlu::Belum])
                                    title="E{{ $s['no'] }} — {{ $s['nama'] }}: {{ $s['level']->label() }}">
                                    E{{ $s['no'] }} {{ $s['level']->ringkas() }}
                                </span>
                            @endforeach
                        </div>

                        {{-- Kaki ubin: berapa elemen belum dinilai. Tanpa ini,
                             proyeksi dibaca sebagai kepastian. --}}
                        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                            {{ $na->kalimatKeyakinan() }}
                        </p>
                    </x-filament::section>
                </div>
            @endif

            {{-- K2 Progres bobot — 3×2. Tidak pernah cacah tagihan. --}}
            @if ($this->lihat('K2'))
                <div class="order-2 lg:col-span-3">
                    <x-filament::section>
                        <x-slot name="heading">Progres bobot</x-slot>
                        <div class="text-5xl font-bold tabular-nums">{{ $this->angka($bobot) }}</div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">dari 100 bobot</p>
                        <div class="mt-3 h-2 rounded-full bg-gray-200 dark:bg-white/10">
                            <div class="h-2 rounded-full bg-primary-500" style="width: {{ $bobot }}%"></div>
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Bobot elemen yang seluruh tagihannya sudah disetujui.
                        </p>
                    </x-filament::section>
                </div>
            @endif

            {{-- K9 Yang perlu dikerjakan — 12×2. Daftar, bukan grafik.
                 Di layar sempit ia naik ke posisi ketiga. --}}
            @if ($this->lihat('K9'))
                <div class="order-3 lg:order-9 lg:col-span-12">
                    <x-filament::section>
                        <x-slot name="heading">Yang perlu dikerjakan</x-slot>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($this->yangPerluDikerjakan() as $k)
                                <a href="{{ $k['url'] }}"
                                    class="rounded-lg border border-gray-200 p-4 transition hover:border-primary-400 hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5">
                                    <div @class(['text-3xl font-bold tabular-nums',
                                        'text-danger-600 dark:text-danger-400' => $k['warna'] === 'danger' && $k['jumlah'] > 0,
                                        'text-warning-600 dark:text-warning-400' => $k['warna'] === 'warning' && $k['jumlah'] > 0,
                                        'text-info-600 dark:text-info-400' => $k['warna'] === 'info' && $k['jumlah'] > 0,
                                        'text-gray-400' => $k['jumlah'] === 0])>
                                        {{ $k['jumlah'] }}
                                    </div>
                                    <div class="mt-1 text-sm">{{ $k['judul'] }}</div>
                                </a>
                            @endforeach
                        </div>
                    </x-filament::section>
                </div>
            @endif

            {{-- K3 Syarat perlu — 3×1 --}}
            @if ($this->lihat('K3') && $na)
                @php
                    $titik = collect($this->titikSyaratPerlu());
                    $diLima = $titik->filter(fn ($s) => $s['level']->memenuhiLima());
                    $belum = $titik->reject(fn ($s) => $s['level']->memenuhiLima());
                @endphp
                <div class="order-4 lg:col-span-3">
                    <x-filament::section>
                        <x-slot name="heading">Syarat perlu</x-slot>
                        <div class="text-4xl font-bold tabular-nums">
                            {{ $diLima->count() }} / {{ $titik->count() }}
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">di level 5 tahun</p>
                        @if ($belum->isNotEmpty())
                            <p class="mt-2 text-xs text-danger-600 dark:text-danger-400">
                                Belum: {{ $belum->map(fn ($s) => 'E'.$s['no'])->join(', ') }}
                            </p>
                        @endif
                    </x-filament::section>
                </div>
            @endif

            {{-- K4 Bukti bermasalah — 3×1. Tiap satuannya klaim yang akan mati. --}}
            @if ($this->lihat('K4'))
                @php $bermasalah = $this->buktiBermasalah(); @endphp
                <div class="order-5 lg:col-span-3">
                    {{-- Jalur dibangun dari Resource, bukan ditulis "/panel/buktis".
                         Jalur absolut akan patah begitu aplikasi dipasang di
                         bawah subfolder seperti /sigap. --}}
                    <a href="{{ \App\Filament\Resources\Buktis\BuktiResource::getUrl(parameters: ['tableFilters' => ['bermasalah' => ['isActive' => true]]]) }}" class="block">
                        <x-filament::section>
                            <x-slot name="heading">Bukti bermasalah</x-slot>
                            <div @class(['text-4xl font-bold tabular-nums',
                                'text-danger-600 dark:text-danger-400' => $bermasalah > 0,
                                'text-success-600 dark:text-success-400' => $bermasalah === 0])>
                                {{ $bermasalah }}
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $bermasalah > 0 ? 'klaim yang akan mati di tangan asesor' : 'seluruh bukti terbaca dan sah' }}
                            </p>
                        </x-filament::section>
                    </a>
                </div>
            @endif

            {{-- K5 Tagihan terlambat — 3×1. Dua angka, bukan satu. --}}
            @if ($this->lihat('K5'))
                @php $terlambat = $this->tagihanTerlambat(); $tertahan = $this->bobotTertahan(); @endphp
                <div class="order-6 lg:col-span-3">
                    <x-filament::section>
                        <x-slot name="heading">Tagihan terlambat</x-slot>
                        <div @class(['text-4xl font-bold tabular-nums',
                            'text-danger-600 dark:text-danger-400' => $terlambat > 0])>
                            {{ $terlambat }}
                        </div>
                        {{-- Bobot tertahan disebut terpisah: sepuluh tagihan kecil
                             terlambat tidak sama gawatnya dengan satu tagihan
                             berbobot 3,00 yang terlambat. --}}
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            menahan {{ $this->angka($tertahan, 3) }} bobot
                        </p>
                    </x-filament::section>
                </div>
            @endif

            {{-- K6 Kesiapan DKPS — 3×1. Tidak pernah dicampur ke K2. --}}
            @if ($this->lihat('K6'))
                <div class="order-7 lg:col-span-3">
                    <x-filament::section>
                        <x-slot name="heading">Kesiapan DKPS</x-slot>
                        <div class="text-4xl font-bold tabular-nums">
                            {{ $dkps['terverifikasi'] }} / {{ $dkps['total'] }}
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">butir terverifikasi</p>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Butir DKPS tidak berbobot, jadi tidak ikut dihitung ke progres bobot.
                        </p>
                    </x-filament::section>
                </div>
            @endif

            {{-- K7 Progres per pokja — 6×2 --}}
            @if ($this->lihat('K7'))
                <div class="order-8 lg:col-span-6">
                    <x-filament::section>
                        <x-slot name="heading">Progres per pokja</x-slot>
                        <div class="space-y-3">
                            @foreach ($this->progresPokja() as $p)
                                <div>
                                    <div class="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                                        <span>
                                            {{ $p['kode'] }}
                                            @if ($p['syarat_perlu'])
                                                <span class="ml-1 rounded bg-danger-100 px-1.5 py-0.5 text-[10px] font-medium text-danger-700 dark:bg-danger-500/20 dark:text-danger-400">syarat perlu</span>
                                            @endif
                                        </span>
                                        <span class="tabular-nums text-gray-600 dark:text-gray-400">
                                            @if ($p['pakai_dkps'])
                                                {{ (int) $p['selesai'] }} / {{ (int) $p['total'] }} butir DKPS
                                            @else
                                                {{ $this->angka($p['selesai']) }} dari {{ $this->angka($p['total']) }} bobot
                                            @endif
                                        </span>
                                    </div>
                                    <div class="mt-1 h-2 rounded-full bg-gray-200 dark:bg-white/10">
                                        <div class="h-2 rounded-full {{ $p['pakai_dkps'] ? 'bg-warning-500' : 'bg-primary-500' }}"
                                            style="width: {{ $p['rasio'] * 100 }}%"></div>
                                    </div>
                                    @if ($p['pakai_dkps'])
                                        {{-- Keterangan wajib: tanpa ini, POKJA-DATA
                                             dibaca sebagai belum mengerjakan apa pun. --}}
                                        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                            Diukur dengan DKPS, bukan bobot — pokja ini memang tidak memegang elemen.
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </x-filament::section>
                </div>
            @endif

            {{-- K8 Laju dan perkiraan — 6×2 --}}
            @if ($this->lihat('K8'))
                <div class="order-9 lg:col-span-6">
                    <x-filament::section>
                        <x-slot name="heading">Laju dan perkiraan</x-slot>

                        @if ($lajuAngka['bisa_diperkirakan'] ?? false)
                            <div class="flex flex-wrap items-baseline gap-4">
                                <div>
                                    <div class="text-3xl font-bold tabular-nums">{{ $this->angka($lajuAngka['laju_mingguan'], 3) }}</div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">bobot per minggu</p>
                                </div>
                                <div>
                                    <div class="text-3xl font-bold tabular-nums">{{ $this->angka($lajuAngka['sisa_bobot']) }}</div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">bobot tersisa</p>
                                </div>
                            </div>
                        @endif

                        {{-- Kalimatnya berdiri sendiri; warnanya menyertai, tidak
                             menggantikan. --}}
                        <p @class(['mt-3 rounded-lg p-3 text-sm',
                            'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $laju['warna'] === 'success',
                            'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400' => $laju['warna'] === 'warning',
                            'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400' => $laju['warna'] === 'danger',
                            'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-400' => in_array($laju['warna'], ['gray', 'info'], true)])>
                            {{ $laju['kalimat'] }}
                        </p>
                    </x-filament::section>
                </div>
            @endif
        </div>
    @endif
</x-filament-panels::page>
