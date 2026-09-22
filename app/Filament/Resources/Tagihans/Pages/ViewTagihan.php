<?php

namespace App\Filament\Resources\Tagihans\Pages;

use App\Enums\StatusTagihan;
use App\Filament\Resources\Tagihans\TagihanResource;
use App\Models\Komentar;
use App\Services\AlurTagihan;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * Tombol status dibangkitkan dari AlurTagihan::tujuanTersedia(), yang sudah
 * menanyakan Izin. Tombolnya memang disembunyikan bila tidak berwenang, TETAPI
 * pemanggilan pindah() memeriksa ulang di sisi server — menyembunyikan tombol
 * bukan pengamanan.
 */
class ViewTagihan extends ViewRecord
{
    protected static string $resource = TagihanResource::class;

    protected function getHeaderActions(): array
    {
        $aksi = [];

        foreach (app(AlurTagihan::class)->tujuanTersedia($this->record, auth()->user()) as $ke) {
            $aksi[] = $this->aksiPindah($ke);
        }

        $aksi[] = $this->aksiKomentar();

        return $aksi;
    }

    private function aksiPindah(StatusTagihan $ke): Action
    {
        return Action::make('pindah_'.$ke->value)
            ->label($this->labelAksi($ke))
            ->color($ke->warna())
            ->requiresConfirmation()
            ->modalHeading($this->labelAksi($ke))
            ->schema($ke->butuhCatatan() ? [
                Textarea::make('catatan')
                    ->label('Alasan pengembalian')
                    ->helperText('Wajib diisi. Tanpa alasan, penanggung jawab hanya bisa menebak apa yang harus diperbaiki.')
                    ->required()
                    ->rows(4),
            ] : [])
            ->action(function (array $data) use ($ke): void {
                try {
                    app(AlurTagihan::class)->pindah(
                        $this->record, $ke, auth()->user(), $data['catatan'] ?? null,
                    );

                    Notification::make()
                        ->title('Status diperbarui menjadi '.$ke->label())
                        ->success()->send();
                } catch (\DomainException $e) {
                    Notification::make()
                        ->title('Perpindahan ditolak')
                        ->body($e->getMessage())
                        ->danger()->send();
                }
            });
    }

    private function labelAksi(StatusTagihan $ke): string
    {
        return match ($ke) {
            StatusTagihan::Dikerjakan => 'Mulai kerjakan',
            StatusTagihan::Diajukan => 'Ajukan untuk reviu',
            StatusTagihan::Direviu => 'Loloskan reviu',
            StatusTagihan::Disetujui => 'Setujui',
            StatusTagihan::Dikembalikan => 'Kembalikan',
            StatusTagihan::Belum => 'Kembalikan ke belum',
        };
    }

    private function aksiKomentar(): Action
    {
        return Action::make('komentar')
            ->label('Tulis komentar')
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->color('gray')
            ->visible(fn () => auth()->user()->can('komentari', $this->record))
            ->schema([
                Textarea::make('isi')->label('Komentar')->required()->rows(4),
            ])
            ->action(function (array $data): void {
                Komentar::create([
                    'commentable_type' => $this->record->getMorphClass(),
                    'commentable_id' => $this->record->getKey(),
                    'user_id' => auth()->id(),
                    'isi' => $data['isi'],
                ]);

                Notification::make()->title('Komentar ditambahkan')->success()->send();
            });
    }
}
