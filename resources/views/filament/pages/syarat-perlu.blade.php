{{--
    Lima kartu. Ambang tiga dan lima tahun berdampingan dengan nilai
    terukurnya — pada E17 dan E51 ambang skor 4 justru lebih rendah daripada
    ambang lima tahun, jadi menampilkan salah satunya saja mengundang
    kesimpulan yang salah.
--}}
<x-filament-panels::page>
    @php $kartu = $this->kartu(); @endphp

    @if ($kartu === [])
        <x-filament::section>
            <x-slot name="heading">Belum ada periode berjalan</x-slot>
            Tetapkan satu periode berstatus &ldquo;berjalan&rdquo; lebih dulu.
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">
                {{ $this->sudahDiLima() }} dari {{ count($kartu) }} syarat perlu di level 5 tahun
            </x-slot>
            <x-slot name="description">
                Kelimanya harus terpenuhi. Empat dari lima tetap berarti tidak terpenuhi, dan
                Nilai Akreditasi setinggi apa pun tanpa kelimanya tetap berujung &ldquo;Terakreditasi&rdquo;.
            </x-slot>
        </x-filament::section>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach ($kartu as $k)
                <x-filament::section>
                    <x-slot name="heading">
                        E{{ $k['elemen']->no }} — {{ $k['elemen']->nama }}
                    </x-slot>
                    <x-slot name="description">
                        Bobot {{ number_format((float) $k['elemen']->bobot, 2, ',', '.') }}
                    </x-slot>

                    <div class="mb-3">
                        <span @class(['rounded px-2 py-1 text-xs font-medium',
                            'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-400' => $k['level']->memenuhiLima(),
                            'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-400' => $k['level'] === \App\Enums\LevelSyaratPerlu::Tiga,
                            'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-400' => $k['level'] === \App\Enums\LevelSyaratPerlu::Belum])>
                            {{ $k['level']->label() }}
                        </span>
                    </div>

                    <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Ambang 3 tahun</dt>
                            <dd class="mt-1 text-sm">{{ $k['syarat']->ambang_3_tahun }}</dd>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Ambang 5 tahun</dt>
                            <dd class="mt-1 text-sm">{{ $k['syarat']->ambang_5_tahun }}</dd>
                        </div>
                    </dl>

                    @if ($k['dari_rumus'])
                        <div class="mt-3 rounded-lg bg-gray-50 p-3 text-sm dark:bg-white/5">
                            <span class="font-medium">{{ $k['dari_rumus']['kode'] }}</span>
                            = {{ number_format((float) $k['dari_rumus']['nilai'], 2, ',', '.') }}
                            <span class="text-gray-500 dark:text-gray-400">
                                (dihitung {{ $k['dari_rumus']['dihitung']->translatedFormat('d F Y') }})
                            </span>
                            @if ($k['dari_rumus']['syarat5'] !== null)
                                <div class="mt-1 text-xs">
                                    Menurut rumus:
                                    3 tahun {{ $k['dari_rumus']['syarat3'] ? '✓' : '✗' }} ·
                                    5 tahun {{ $k['dari_rumus']['syarat5'] ? '✓' : '✗' }}
                                </div>
                            @endif
                        </div>
                    @endif

                    @if (filled($k['nilai_terukur']))
                        <p class="mt-3 text-sm"><span class="font-medium">Nilai terukur:</span> {{ $k['nilai_terukur'] }}</p>
                    @endif

                    {{-- Catatan dari data instrumen: pada E17 dan E51 ia
                         menyebut bahwa skor 4 tidak cukup. --}}
                    @if (filled($k['syarat']->catatan))
                        <p class="mt-3 rounded-lg bg-danger-50 p-3 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">
                            {{ $k['syarat']->catatan }}
                        </p>
                    @endif
                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
