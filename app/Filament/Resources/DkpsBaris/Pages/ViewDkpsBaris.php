<?php

namespace App\Filament\Resources\DkpsBaris\Pages;

use App\Filament\Resources\DkpsBaris\DkpsBarisResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDkpsBaris extends ViewRecord
{
    protected static string $resource = DkpsBarisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
