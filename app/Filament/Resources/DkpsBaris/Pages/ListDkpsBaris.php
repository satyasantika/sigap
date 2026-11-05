<?php

namespace App\Filament\Resources\DkpsBaris\Pages;

use App\Filament\Resources\DkpsBaris\DkpsBarisResource;
use Filament\Resources\Pages\ListRecords;

/** Tanpa tombol "buat": baris DKPS diisi di grid Tabel DKPS. */
class ListDkpsBaris extends ListRecords
{
    protected static string $resource = DkpsBarisResource::class;
}
