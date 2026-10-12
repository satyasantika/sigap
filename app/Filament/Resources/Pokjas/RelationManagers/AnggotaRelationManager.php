<?php

namespace App\Filament\Resources\Pokjas\RelationManagers;

use App\Enums\PeranPengguna;
use App\Models\User;
use App\Support\Izin;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Keanggotaan pokja. Wewenangnya `pokja.anggota`: admin dan ketua untuk semua
 * pokja, koordinator hanya untuk pokjanya sendiri.
 *
 * Keanggotaan inilah yang menentukan lingkup `pokjanya` dan `pokja_data`
 * pada kelas Izin, jadi perubahan di sini langsung mengubah wewenang orang.
 */
class AnggotaRelationManager extends RelationManager
{
    protected static string $relationship = 'anggota';

    protected static ?string $inverseRelationship = 'pokja';

    protected static ?string $title = 'Anggota pokja';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Izin::boleh(auth()->user(), 'pokja.anggota', $ownerRecord);
    }

    private function boleh(): bool
    {
        return Izin::boleh(auth()->user(), 'pokja.anggota', $this->getOwnerRecord());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama_lengkap')
            ->columns([
                TextColumn::make('nama_lengkap')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->label('Surel')->toggleable(),
                TextColumn::make('peran')->label('Peran pengguna')->badge()
                    ->formatStateUsing(fn (PeranPengguna $state) => $state->label())
                    ->color(fn (PeranPengguna $state) => $state->warna()),
                TextColumn::make('peran_dalam_pokja')->label('Peran dalam pokja')
                    ->placeholder('—'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Tambah anggota')
                    ->visible(fn () => $this->boleh())
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['nama_lengkap', 'email'])
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query->bisaDitugaskan())
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect()->label('Pengguna'),
                        Select::make('peran_dalam_pokja')
                            ->label('Peran dalam pokja')
                            ->options(['anggota' => 'Anggota', 'koordinator' => 'Koordinator'])
                            ->default('anggota')
                            ->native(false),
                    ]),
            ])
            ->recordActions([
                DetachAction::make()
                    ->label('Lepas')
                    ->visible(fn (User $record) => $this->boleh()),
            ])
            ->emptyStateHeading('Belum ada anggota')
            ->emptyStateDescription('Tambahkan pengguna agar mereka masuk lingkup pokja ini.');
    }
}
