<?php

namespace App\Filament\Resources\DkpsButirs\Pages;

use App\Filament\Resources\DkpsButirs\DkpsButirResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDkpsButirs extends ListRecords
{
    protected static string $resource = DkpsButirResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
