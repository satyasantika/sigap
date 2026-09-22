<?php

namespace App\Filament\Resources\Rumuses\Pages;

use App\Filament\Resources\Rumuses\RumusResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRumuses extends ListRecords
{
    protected static string $resource = RumusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
