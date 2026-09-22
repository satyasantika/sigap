<?php

namespace App\Filament\Resources\Pokjas\Pages;

use App\Filament\Resources\Pokjas\PokjaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPokjas extends ListRecords
{
    protected static string $resource = PokjaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
