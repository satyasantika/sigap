<?php

namespace App\Filament\Resources\Narasis\Pages;

use App\Filament\Resources\Narasis\NarasiResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditNarasi extends EditRecord
{
    protected static string $resource = NarasiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
