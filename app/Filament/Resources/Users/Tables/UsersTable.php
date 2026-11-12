<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\PeranPengguna;
use App\Filament\Actions\AksiMasukSebagai;
use App\Models\User;
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
                // Surel menumpang di bawah nama: satu kolom lebih sedikit, dan
                // pencarian tetap menjangkau keduanya.
                TextColumn::make('nama_lengkap')->label('Nama')
                    ->description(fn (User $record) => $record->email)
                    ->searchable(['nama_lengkap', 'email'])->sortable(),
                // Peran tambahan menumpang di bawah peran utama, seperti surel
                // di bawah nama; kosong bila tidak ada.
                TextColumn::make('peran')->label('Peran')->badge()
                    ->formatStateUsing(fn (PeranPengguna $state) => $state->label())
                    ->color(fn (PeranPengguna $state) => $state->warna())
                    ->description(fn (User $record) => filled($record->peran_tambahan)
                        ? '+ '.collect($record->peran_tambahan)
                            ->map(fn (string $p) => PeranPengguna::tryFrom($p)?->label() ?? $p)->join(', ')
                        : null),
                TextColumn::make('jabatan')->label('Jabatan')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nidn')->label('NIDN')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
                TextColumn::make('terakhir_masuk_pada')->label('Terakhir masuk')
                    ->since()->dateTimeTooltip('d F Y, H:i')->placeholder('belum pernah')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('peran')->label('Peran')->options(PeranPengguna::pilihan())
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? $query->berperan($data['value'])
                        : $query),
                TernaryFilter::make('aktif')->label('Aktif')
                    ->trueLabel('Hanya yang aktif')
                    ->falseLabel('Hanya yang nonaktif')
                    ->placeholder('Semua'),
            ])
            ->recordActions([
                // Ikon saja; labelnya tetap ada sebagai tooltip dan nama aksesibel.
                EditAction::make()->label('Sunting')->iconButton()->tooltip('Sunting'),
                AksiMasukSebagai::buat()->iconButton()->tooltip('Masuk sebagai'),
            ])
            ->defaultSort('nama_lengkap')
            ->emptyStateHeading('Belum ada pengguna')
            ->emptyStateDescription('Pengguna dibuat di sini oleh admin. Tidak ada pendaftaran mandiri.');
    }
}
