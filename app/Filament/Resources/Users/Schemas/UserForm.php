<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\PeranPengguna;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

/**
 * Tidak ada pendaftaran mandiri: pengguna dibuat admin lewat formulir ini.
 * Kata sandi juga diatur ulang dari sini, bukan lewat surel — aplikasi ini
 * sengaja tidak bergantung pada SMTP yang berfungsi.
 * Lihat vibecoding/docs/08-auth-dan-izin.md bagian 1.
 */
class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nama_lengkap')
                ->label('Nama lengkap')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $set) => $set('name', $state)),
            TextInput::make('name')
                ->label('Nama panggilan')
                ->helperText('Dipakai pada sapaan di panel. Terisi otomatis dari nama lengkap.')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label('Surel')
                ->helperText('Dipakai sebagai identitas masuk. Tidak pernah diverifikasi lewat surel.')
                ->email()
                ->required()
                ->unique(ignoreRecord: true),
            TextInput::make('password')
                ->label('Kata sandi')
                ->password()
                ->revealable()
                ->minLength(8)
                ->helperText('Minimal delapan karakter. Kosongkan bila tidak ingin mengubah.')
                ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null)
                ->dehydrated(fn (?string $state) => filled($state))
                ->required(fn (string $operation) => $operation === 'create'),
            Select::make('peran')
                ->label('Peran')
                ->options(PeranPengguna::pilihan())
                ->helperText('Peran menentukan wewenang lewat matriks izin, bukan lewat menyembunyikan tombol.')
                ->required()
                ->native(false),
            Select::make('prodi_id')
                ->label('Prodi')
                ->relationship('prodi', 'nama')
                ->native(false),
            TextInput::make('nidn')
                ->label('NIDN')
                ->maxLength(255),
            TextInput::make('jabatan')
                ->label('Jabatan')
                ->maxLength(255),
            Toggle::make('aktif')
                ->label('Aktif')
                ->helperText('Pengguna nonaktif ditolak masuk dengan pesan yang jelas.')
                ->default(true),
            Toggle::make('wajib_ganti_sandi')
                ->label('Wajib ganti sandi saat masuk')
                ->default(true),
        ]);
    }
}
