<?php

namespace App\Filament\Resources\Buktis\Pages;

use App\Filament\Resources\Buktis\BuktiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBuktis extends ListRecords
{
    protected static string $resource = BuktiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
