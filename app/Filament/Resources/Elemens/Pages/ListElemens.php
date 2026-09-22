<?php

namespace App\Filament\Resources\Elemens\Pages;

use App\Filament\Resources\Elemens\ElemenResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListElemens extends ListRecords
{
    protected static string $resource = ElemenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
