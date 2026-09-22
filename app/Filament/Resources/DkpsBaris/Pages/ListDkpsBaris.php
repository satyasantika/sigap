<?php

namespace App\Filament\Resources\DkpsBaris\Pages;

use App\Filament\Resources\DkpsBaris\DkpsBarisResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDkpsBaris extends ListRecords
{
    protected static string $resource = DkpsBarisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
