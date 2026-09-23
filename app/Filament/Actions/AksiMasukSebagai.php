<?php

namespace App\Filament\Actions;

use App\Models\User;
use App\Services\Impersonasi;
use App\Support\Izin;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * "Masuk sebagai" — memulai penyamaran dari daftar pengguna.
 *
 * Alasan WAJIB diisi. Itu bukan birokrasi: kolom alasan adalah satu-satunya
 * bagian dari jejak yang menjelaskan MENGAPA, dan tanpanya log hanya
 * memberitahu bahwa admin pernah menjadi ketua selama sebelas menit tanpa ada
 * yang bisa menilai apakah itu wajar.
 *
 * Selama menyamar, admin memegang wewenang penuh peran yang ditirunya —
 * termasuk menyetujui tagihan. Yang menjaga pertanggungjawaban adalah jejak,
 * bukan pagar. Lihat CLAUDE.md bagian 7 butir 8.
 */
class AksiMasukSebagai
{
    public static function buat(): Action
    {
        return Action::make('masuk_sebagai')
            ->label('Masuk sebagai')
            ->icon(Heroicon::OutlinedEye)
            ->color('warning')
            ->visible(fn (User $record) => self::bolehMenyamari($record))
            ->requiresConfirmation()
            ->modalHeading(fn (User $record) => 'Masuk sebagai '.$record->nama_lengkap.'?')
            ->modalDescription(fn (User $record) => 'Anda akan melihat dan mengerjakan SIGAP persis '
                .'seperti '.$record->nama_lengkap.' ('.$record->peran->label().'), dengan seluruh '
                .'wewenang peran itu. Setiap tindakan yang Anda lakukan tercatat sebagai '
                .'tindakan lewat penyamaran, lengkap dengan nama Anda.')
            ->modalSubmitActionLabel('Mulai menyamar')
            ->modalCancelActionLabel('Batal')
            ->schema([
                Textarea::make('alasan')
                    ->label('Alasan')
                    ->placeholder('Misalnya: menelusuri laporan bahwa tombol "Ajukan" tidak muncul.')
                    ->helperText('Tersimpan permanen di log aktivitas. Tulis supaya orang lain bisa menilai apakah penyamaran ini wajar.')
                    ->required()
                    ->minLength(10)
                    ->maxLength(500)
                    ->rows(3),
            ])
            ->action(function (User $record, array $data, Impersonasi $impersonasi) {
                $admin = Auth::user();

                try {
                    $impersonasi->mulai($admin, $record, $data['alasan']);
                } catch (RuntimeException $e) {
                    Notification::make()
                        ->title('Penyamaran tidak dimulai')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return null;
                }

                return redirect(Filament::getUrl());
            });
    }

    /**
     * Tombolnya muncul hanya bila penyamaran memang mungkin.
     *
     * Tiga syarat, tidak satu pun berupa perbandingan peran langsung:
     * pelakunya berwenang, sasarannya bukan orang yang juga berwenang menyamar,
     * dan belum ada penyamaran yang berjalan.
     */
    private static function bolehMenyamari(User $target): bool
    {
        $admin = Auth::user();

        return $admin !== null
            && Izin::bolehSistem($admin, 'impersonasi.mulai')
            && ! Izin::bolehSistem($target, 'impersonasi.mulai')
            && $target->aktif
            && ! app(Impersonasi::class)->sedangBerlangsung();
    }
}
