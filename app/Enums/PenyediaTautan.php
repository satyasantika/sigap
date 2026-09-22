<?php

namespace App\Enums;

enum PenyediaTautan: string
{
    case Drive = 'drive';
    case OneDrive = 'onedrive';
    case SharePoint = 'sharepoint';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Drive => 'Google Drive',
            self::OneDrive => 'OneDrive',
            self::SharePoint => 'SharePoint',
            self::Lainnya => 'Lainnya',
        };
    }

    /** @return array<string, string> */
    public static function pilihan(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $p) => [$p->value => $p->label()])->all();
    }
}
