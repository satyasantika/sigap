{{--
    Tata letak halaman masuk: dua panel. Kiri panel merek (disembunyikan di
    layar sempit), kanan formulir. Menggantikan tata letak "simple" Filament
    yang hanya satu kartu di tengah. Isi $slot — judul, formulir, tombol —
    tetap dari Filament, jadi galat validasi dan Livewire berjalan seperti biasa.
--}}
@props([
    'livewire' => null,
])

@php
    use Filament\Support\Facades\FilamentView;
    use Filament\View\PanelsRenderHook;

    $livewire ??= null;
    $lingkup = $livewire?->getRenderHookScopes();
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    <div class="sigap-masuk">
        {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, scopes: $lingkup) }}

        <aside class="sigap-masuk-merek">
            <a href="{{ url('/') }}" class="sigap-masuk-logo">
                <span class="lambang" aria-hidden="true">S</span>
                <span class="nama">{{ \App\Support\Jati::nama() }}</span>
            </a>

            <div class="sigap-masuk-isi">
                <h2>Bukti akreditasi dikumpulkan <em>sepanjang jalan</em>, bukan dicari saat asesor sudah datang.</h2>
                <p>{{ \App\Support\Jati::namaPanjang() }}</p>

                <ul>
                    <li>Instrumen LAMDIK dipecah jadi tagihan yang jelas penanggung jawabnya.</li>
                    <li>Bukti diperiksa dua kali: keterbacaan oleh mesin, keabsahan oleh manusia.</li>
                    <li>Progres dihitung dari bobot dan selalu tampil bersama gerbangnya.</li>
                </ul>
            </div>

            <p class="sigap-masuk-catatan">Tidak ada pendaftaran mandiri. Akun dibuat administrator sistem.</p>
        </aside>

        <div class="sigap-masuk-form">
            {{-- Panel merek tersembunyi di layar sempit; logo kecil menggantikannya. --}}
            <a href="{{ url('/') }}" class="sigap-masuk-logo sigap-masuk-logo-kecil">
                <span class="lambang" aria-hidden="true">S</span>
                <span class="nama">{{ \App\Support\Jati::nama() }}</span>
            </a>

            <main id="fi-main-content" tabindex="-1" class="fi-simple-main">
                {{ $slot }}
            </main>

            {{ FilamentView::renderHook(PanelsRenderHook::FOOTER, scopes: $lingkup) }}
        </div>

        {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_LAYOUT_END, scopes: $lingkup) }}
    </div>
</x-filament-panels::layout.base>
