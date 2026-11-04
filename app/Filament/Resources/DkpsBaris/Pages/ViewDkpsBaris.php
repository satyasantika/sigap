<?php

namespace App\Filament\Resources\DkpsBaris\Pages;

use App\Filament\Resources\DkpsBaris\DkpsBarisResource;
use Filament\Resources\Pages\ViewRecord;

/** Tampilan baca saja; penyuntingan lewat grid Tabel DKPS. */
class ViewDkpsBaris extends ViewRecord
{
    protected static string $resource = DkpsBarisResource::class;
}
