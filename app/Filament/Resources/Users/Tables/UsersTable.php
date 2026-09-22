<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\PeranPengguna;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama_lengkap')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->label('Surel')->searchable()->toggleable(),
                TextColumn::make('peran')->label('Peran')->badge()
                    ->formatStateUsing(fn (PeranPengguna $state) => $state->label())
                    ->color(fn (PeranPengguna $state) => $state->warna()),
                TextColumn::make('jabatan')->label('Jabatan')->placeholder('—')->toggleable(),
                TextColumn::make('nidn')->label('NIDN')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
                TextColumn::make('terakhir_masuk_pada')->label('Terakhir masuk')
                    ->dateTime('d F Y, H:i')->placeholder('belum pernah')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('peran')->label('Peran')->options(PeranPengguna::pilihan()),
                TernaryFilter::make('aktif')->label('Aktif')
                    ->trueLabel('Hanya yang aktif')
                    ->falseLabel('Hanya yang nonaktif')
                    ->placeholder('Semua'),
            ])
            ->recordActions([EditAction::make()->label('Sunting')])
            ->defaultSort('nama_lengkap')
            ->emptyStateHeading('Belum ada pengguna')
            ->emptyStateDescription('Pengguna dibuat di sini oleh admin. Tidak ada pendaftaran mandiri.');
    }
}
