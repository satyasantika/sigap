<?php

namespace App\Filament\Resources\Pokjas\Pages;

use App\Filament\Resources\Pokjas\PokjaResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPokja extends EditRecord
{
    protected static string $resource = PokjaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
