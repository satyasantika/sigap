{{--
    Spanduk sesi demo. Tampil di setiap halaman panel selama sesi demo,
    tidak bisa ditutup, dan sengaja berwarna berbeda dari spanduk penyamaran.

    Alasannya sama pentingnya: orang yang lupa sedang berada di demo akan
    mengira pekerjaannya tersimpan ke data sungguhan — dan sebaliknya, orang
    yang mengira sedang di demo padahal tidak akan memperlakukan data
    akreditasi sebagai mainan.
--}}
@php
    $demo = app(\App\Services\Demo::class);
@endphp

@if ($demo->sedangBerjalan())
    @php
        $pengguna = filament()->auth()->user();
        $simulasi = $demo->simulasiSesiIni();
    @endphp

    <div
        role="status"
        class="fi-sigap-spanduk-demo flex flex-wrap items-center justify-between gap-3 border-b border-info-300 bg-info-100 px-4 py-2 text-sm text-info-900 sm:px-6 lg:px-8 dark:border-info-500/30 dark:bg-info-500/10 dark:text-info-200"
    >
        <div class="flex items-center gap-2">
            <x-filament::icon icon="heroicon-o-beaker" class="h-5 w-5 shrink-0" />
            <span>
                <strong>Ini demo.</strong>
                Anda mencoba {{ $pengguna?->peran?->label() }} pada periode latihan
                @if ($simulasi)<strong>{{ $simulasi->nama }}</strong>@endif.
                Tidak ada data akreditasi sungguhan yang terlihat atau tersentuh dari sini.
            </span>
        </div>

        <form method="POST" action="{{ route('demo.keluar') }}">
            @csrf
            <button type="submit"
                    class="fi-btn rounded-lg bg-info-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-info-500">
                Akhiri demo
            </button>
        </form>
    </div>
@endif
