<?php

namespace App\Filament\Resources\Tagihans\Pages;

use App\Enums\JenisTagihan;
use App\Enums\StatusTagihan;
use App\Filament\Resources\Tagihans\TagihanResource;
use App\Models\Bukti;
use App\Models\Komentar;
use App\Services\AlurTagihan;
use App\Services\PengelolaNarasi;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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

        if ($this->record->jenis === JenisTagihan::Narasi && $this->record->elemen_id !== null) {
            $aksi[] = $this->aksiTulisNarasi();
        }

        $aksi[] = $this->aksiTautkanBukti();

        foreach (app(AlurTagihan::class)->tujuanTersedia($this->record, auth()->user()) as $ke) {
            $aksi[] = $this->aksiPindah($ke);
        }

        $aksi[] = $this->aksiKomentar();

        return $aksi;
    }

    /**
     * Kotak penulisan naskah LED dengan penghitung kata hidup.
     *
     * Penanda 200/600 muncul sebagai teks bantuan yang berubah saat mengetik,
     * bukan sebagai galat setelah menyimpan: orang yang baru tahu naskahnya
     * kurang 40 kata setelah menekan Ajukan akan menambahnya asal-asalan.
     */
    private function aksiTulisNarasi(): Action
    {
        $pengelola = app(PengelolaNarasi::class);

        $narasi = $pengelola->untukElemen(
            $this->record->periode_id,
            $this->record->elemen_id,
            $this->record->prodi_id,
        );

        return Action::make('tulis_narasi')
            ->label($narasi->jumlah_kata > 0 ? 'Sunting naskah' : 'Tulis naskah')
            ->icon('heroicon-o-pencil-square')
            ->color('primary')
            ->modalWidth('5xl')
            ->visible(fn () => auth()->user()->can('update', $narasi))
            ->fillForm(fn () => ['isi' => $narasi->isi])
            ->schema([
                Textarea::make('isi')
                    ->label('Naskah LED')
                    ->rows(20)
                    ->live(onBlur: true)
                    ->helperText(function ($state) use ($pengelola) {
                        $kata = $pengelola->jumlahKata($state);

                        return match (true) {
                            $kata === 0 => 'Belum ada tulisan. Sasaran 200–600 kata.',
                            $kata < PengelolaNarasi::MINIMAL_KATA => "{$kata} kata — kurang "
                                .(PengelolaNarasi::MINIMAL_KATA - $kata).' kata dari minimal 200.',
                            $kata > PengelolaNarasi::MAKSIMAL_KATA => "{$kata} kata — melewati anjuran 600 kata. "
                                .'Boleh diajukan, tetapi pertimbangkan meringkasnya.',
                            default => "{$kata} kata — sudah di dalam rentang 200–600.",
                        };
                    })
                    ->columnSpanFull(),
            ])
            ->action(function (array $data) use ($pengelola, $narasi): void {
                $pengelola->simpan($narasi, $data['isi'] ?? null, auth()->user());

                Notification::make()
                    ->title('Naskah tersimpan')
                    ->body('Versi sebelumnya tetap tersimpan dan bisa ditelusuri.')
                    ->success()->send();
            });
    }

    /**
     * Menautkan bukti yang SUDAH ADA, bukan hanya mengunggah baru.
     *
     * Satu SK sering menopang beberapa elemen sekaligus; memaksa orang
     * mengunggah ulang berkas yang sama menghasilkan lima salinan yang
     * harus divalidasi lima kali.
     */
    private function aksiTautkanBukti(): Action
    {
        return Action::make('tautkan_bukti')
            ->label('Tautkan bukti')
            ->icon('heroicon-o-paper-clip')
            ->color('gray')
            ->visible(fn () => auth()->user()->can('create', Bukti::class))
            ->schema([
                Select::make('bukti_id')
                    ->label('Pilih bukti yang sudah ada')
                    ->options(fn () => Bukti::where('periode_id', $this->record->periode_id)
                        ->orderByDesc('created_at')->limit(200)
                        ->pluck('judul', 'id')->all())
                    ->searchable()
                    ->required()
                    ->helperText('Belum ada yang cocok? Unggah lebih dulu lewat menu Bukti, lalu tautkan di sini.')
                    ->native(false),
            ])
            ->action(function (array $data): void {
                $this->record->bukti()->syncWithoutDetaching([$data['bukti_id']]);

                Notification::make()->title('Bukti ditautkan')->success()->send();
            });
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
