<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Masuk;
use App\Http\Middleware\PaksaGantiSandi;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
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
            ->colors([
                'primary' => Color::Emerald,
            ])
            // Urutan kelompok menu mengikuti vibecoding/docs/05-layar-dan-widget.md.
            // Kelompok Penilaian menyusul di tahap 6.
            ->navigationGroups([
                'Pengumpulan',
                'Data',
                'Referensi',
                'Pengaturan',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // Dasbor bento K1-K9 dibangun di tahap 6; untuk sekarang kosong.
            ->widgets([
                AccountWidget::class,
            ])
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
            ->authMiddleware([
                Authenticate::class,
                PaksaGantiSandi::class,
            ]);
    }
}
