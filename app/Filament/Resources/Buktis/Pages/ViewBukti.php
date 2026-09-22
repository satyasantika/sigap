<?php

namespace App\Filament\Resources\Buktis\Pages;

use App\Enums\ValidasiBukti;
use App\Filament\Resources\Buktis\BuktiResource;
use App\Services\PemeriksaTautan;
use App\Services\PengelolaBukti;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewBukti extends ViewRecord
{
    protected static string $resource = BuktiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->aksiValidasi(ValidasiBukti::Sah),
            $this->aksiValidasi(ValidasiBukti::Meragukan),
            $this->aksiValidasi(ValidasiBukti::TidakSah),
            $this->aksiPeriksaUlang(),
            EditAction::make()->label('Sunting'),
        ];
    }

    private function aksiValidasi(ValidasiBukti $status): Action
    {
        return Action::make('validasi_'.$status->value)
            ->label('Tandai '.strtolower($status->label()))
            ->color($status->warna())
            ->icon(match ($status) {
                ValidasiBukti::Sah => 'heroicon-o-check-badge',
                ValidasiBukti::Meragukan => 'heroicon-o-question-mark-circle',
                default => 'heroicon-o-x-circle',
            })
            ->visible(fn () => auth()->user()->can('validasi', $this->record)
                && $this->record->validasi_status !== $status)
            ->schema($status->butuhCatatan() ? [
                Textarea::make('catatan')
                    ->label('Alasan')
                    ->helperText('Wajib diisi. Tanpa alasan, pengunggah akan mengulang kesalahan yang sama.')
                    ->required()->rows(4),
            ] : [])
            ->action(function (array $data) use ($status): void {
                try {
                    app(PengelolaBukti::class)->validasi(
                        $this->record, $status, auth()->user(), $data['catatan'] ?? null,
                    );

                    Notification::make()->title('Keabsahan diperbarui: '.$status->label())->success()->send();
                } catch (\RuntimeException $e) {
                    Notification::make()->title('Ditolak')->body($e->getMessage())->danger()->send();
                }
            });
    }

    /**
     * Memeriksa ulang keterbacaan sekarang juga.
     *
     * Berguna setelah pengunggah memperbaiki setelan berbagi: tanpa ini ia
     * harus menunggu penjadwalan harian untuk tahu apakah perbaikannya berhasil.
     */
    private function aksiPeriksaUlang(): Action
    {
        return Action::make('periksa_ulang')
            ->label('Periksa ulang keterbacaan')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->visible(fn () => filled($this->record->url_kanonik))
            ->action(function (): void {
                $status = app(PemeriksaTautan::class)->periksa($this->record);

                Notification::make()
                    ->title('Hasil pemeriksaan: '.$status->label())
                    ->body($this->record->fresh()->akses_pesan)
                    ->color($status->warna())
                    ->send();
            });
    }
}
