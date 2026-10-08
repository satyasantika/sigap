<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Livewire\ImporTempel;
use App\Support\Impor\ImporPengguna;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Livewire;
use Filament\Support\Icons\Heroicon;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('impor_tempel')
                ->label('Impor tempel')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->color('gray')
                ->modalHeading('Impor pengguna lewat tempel-tabel')
                ->modalWidth('5xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup')
                ->schema([
                    Livewire::make(ImporTempel::class, [
                        'profilKelas' => ImporPengguna::class,
                    ])->key('impor-tempel-pengguna'),
                ]),
            CreateAction::make(),
        ];
    }
}
