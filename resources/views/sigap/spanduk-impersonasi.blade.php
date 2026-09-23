{{--
    Spanduk penyamaran. Tampil di setiap halaman panel selama sesi impersonasi
    berjalan, tidak bisa ditutup, dan sengaja memakai warna peringatan.

    Alasannya satu: admin yang lupa sedang menyamar akan mengira dirinya ketua,
    lalu menyetujui tagihan atas nama orang lain tanpa sadar. Jejaknya memang
    tersimpan, tetapi lebih baik salahnya tidak terjadi.
--}}
@php
    $impersonasi = app(\App\Services\Impersonasi::class);
@endphp

@if ($impersonasi->sedangBerlangsung())
    @php
        $admin = $impersonasi->adminAsli();
        $target = filament()->auth()->user();
    @endphp

    <div
        role="status"
        class="fi-sigap-spanduk flex flex-wrap items-center justify-between gap-3 border-b border-warning-300 bg-warning-100 px-4 py-2 text-sm text-warning-900 sm:px-6 lg:px-8 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-200"
    >
        <div class="flex items-center gap-2">
            <x-filament::icon
                icon="heroicon-o-eye"
                class="h-5 w-5 shrink-0"
            />
            <span>
                Anda sedang <strong>menyamar</strong> sebagai
                <strong>{{ $target?->nama_lengkap }}</strong>
                ({{ $target?->peran?->label() }}).
                Semua tindakan tercatat atas nama {{ $admin?->nama_lengkap ?? 'admin' }}.
            </span>
        </div>

        <form method="POST" action="{{ route('sigap.impersonasi.akhiri') }}">
            @csrf
            <button
                type="submit"
                class="fi-btn rounded-lg bg-warning-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-warning-500"
            >
                Kembali ke akun saya
            </button>
        </form>
    </div>
@endif
