<?php

namespace App\Filament\Resources\DkpsBaris\Pages;

use App\Filament\Resources\DkpsBaris\DkpsBarisResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDkpsBaris extends EditRecord
{
    protected static string $resource = DkpsBarisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
