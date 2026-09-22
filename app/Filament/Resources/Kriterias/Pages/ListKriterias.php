<?php

namespace App\Filament\Resources\Kriterias\Pages;

use App\Filament\Resources\Kriterias\KriteriaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKriterias extends ListRecords
{
    protected static string $resource = KriteriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
