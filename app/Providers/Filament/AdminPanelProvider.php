<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Masuk;
use App\Filament\Sistem\MenuPengguna;
use App\Http\Middleware\PaksaGantiSandi;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('panel')
            ->path('panel')
            ->login(Masuk::class)
            // Tujuh hal yang dibangun, tidak lebih: masuk, keluar, dan ubah
            // profil sendiri. Tanpa ->registration(), tanpa
            // ->passwordReset(), tanpa ->emailVerification().
            // Lihat vibecoding/docs/08-auth-dan-izin.md bagian 1.
            ->profile(isSimple: false)
            ->brandName('SIGAP')
            // Tema terang adalah bawaan (CLAUDE.md bagian 7 butir 10). Penukar
            // tema tetap ada — yang ditetapkan di sini adalah tampilan pertama
            // bagi pengguna baru, bukan larangan memakai tema gelap. Sebagian
            // besar pekerjaan SIGAP dilakukan siang hari di ruang kerja terang,
            // dan tangkapan layar pada manual pengguna dibuat dengan tema ini.
            ->defaultThemeMode(ThemeMode::Light)
            // Tanpa tema sendiri, kelas Tailwind di Blade kita tidak ikut
            // terkompilasi dan dasbor bento tampil sebagai teks polos.
            ->viteTheme('resources/css/filament/panel/theme.css')
            ->colors([
                'primary' => Color::Emerald,
            ])
            // Urutan kelompok menu mengikuti vibecoding/docs/05-layar-dan-widget.md.
            ->navigationGroups([
                'Pengumpulan',
                'Penilaian',
                'Data',
                'Referensi',
                'Pengaturan',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // Dasbor bawaan Filament diganti halaman Dasbor sendiri
            // (app/Filament/Pages/Dasbor.php) yang memuat bento K1-K9.
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // Menu keluar diganti: selalu meminta konfirmasi, dan menyediakan
            // "Kembali ke akun saya" selama penyamaran berjalan.
            ->userMenuItems(MenuPengguna::item())
            ->renderHook(
                PanelsRenderHook::TOPBAR_BEFORE,
                fn (): string => view('sigap.spanduk-impersonasi')->render()
                    .view('sigap.spanduk-demo')->render(),
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn (): string => view('sigap.footer')->render(),
            )
            ->authMiddleware([
                Authenticate::class,
                PaksaGantiSandi::class,
            ]);
    }
}
