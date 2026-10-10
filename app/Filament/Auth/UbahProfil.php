<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\EditProfile;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

/**
 * Halaman profil SIGAP.
 *
 * Selain mengubah nama dan kata sandi, halaman ini mencabut tanda
 * `wajib_ganti_sandi` begitu kata sandi benar-benar diganti. Tanpa itu,
 * middleware PaksaGantiSandi terus mengalihkan pengguna ke profil dan menu
 * lain tidak pernah bisa dibuka.
 */
class UbahProfil extends EditProfile
{
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        if (filled($data['password'] ?? null)) {
            $data['wajib_ganti_sandi'] = false;
        }

        return parent::handleRecordUpdate($record, $data);
    }
}
