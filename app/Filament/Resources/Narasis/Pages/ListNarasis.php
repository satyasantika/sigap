<?php

namespace App\Filament\Resources\Narasis\Pages;

use App\Filament\Resources\Narasis\NarasiResource;
use App\Models\Periode;
use App\Services\EksporNaskah;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListNarasis extends ListRecords
{
    protected static string $resource = NarasiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ekspor')
                ->label('Ekspor naskah')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $periode = Periode::aktif()->firstOrFail();
                    $ekspor = app(EksporNaskah::class);

                    return response()->streamDownload(
                        fn () => print $ekspor->markdown($periode),
                        $ekspor->namaBerkas($periode),
                        ['Content-Type' => 'text/markdown; charset=UTF-8'],
                    );
                }),
        ];
    }
}
