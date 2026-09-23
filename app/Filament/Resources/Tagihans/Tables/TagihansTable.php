<?php

namespace App\Filament\Resources\Tagihans\Tables;

use App\Enums\JenisTagihan;
use App\Enums\StatusTagihan;
use App\Models\Kriteria;
use App\Models\Tagihan;
use App\Models\User;
use App\Notifications\TagihanDitugaskan;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TagihansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('judul')->label('Tagihan')->searchable()->wrap()
                    ->description(fn (Tagihan $r) => $r->elemen
                        ? 'E'.$r->elemen->no.($r->elemen->syarat_perlu ? ' · ⚠ SYARAT PERLU' : '')
                        : $r->dkpsButir?->label_tabel)
                    ->color(fn (Tagihan $r) => $r->elemen?->syarat_perlu ? 'danger' : null),
                TextColumn::make('jenis')->label('Jenis')->badge()
                    ->formatStateUsing(fn (JenisTagihan $state) => $state->label())
                    ->color(fn (JenisTagihan $state) => $state->warna()),
                TextColumn::make('pokja.kode')->label('Pokja')->badge()->sortable(),
                TextColumn::make('penanggungJawab.nama_lengkap')->label('Penanggung jawab')
                    ->placeholder('belum ditugaskan')->searchable(),
                TextColumn::make('tenggat')->label('Tenggat')->date('d M Y')->sortable()
                    ->placeholder('—')
                    // Baris terlambat ditandai merah di kolom tenggatnya, bukan
                    // lewat kolom terpisah: yang dicari mata adalah tanggalnya.
                    ->color(fn (Tagihan $r) => $r->terlambat() ? 'danger' : null)
                    ->weight(fn (Tagihan $r) => $r->terlambat() ? 'bold' : null)
                    ->description(fn (Tagihan $r) => $r->terlambat() ? 'terlambat' : null),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (StatusTagihan $state) => $state->label())
                    ->color(fn (StatusTagihan $state) => $state->warna())
                    ->sortable(),
                TextColumn::make('bobot_terkait')->label('Bobot')->sortable()->toggleable()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 3, ',', '.')),
            ])
            ->filters([
                SelectFilter::make('pokja')->label('Pokja')->relationship('pokja', 'kode'),
                SelectFilter::make('jenis')->label('Jenis')->options(JenisTagihan::pilihan()),
                SelectFilter::make('status')->label('Status')->options(StatusTagihan::pilihan())->multiple(),
                SelectFilter::make('penanggung_jawab_id')->label('Penanggung jawab')
                    ->options(fn () => User::bisaDitugaskan()->orderBy('nama_lengkap')
                        ->pluck('nama_lengkap', 'id')->all())
                    ->searchable(),
                SelectFilter::make('kriteria')->label('Kriteria')
                    ->options(fn () => Kriteria::orderBy('urutan')->pluck('kode', 'id')->all())
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $v) => $query->whereHas('elemen', fn (Builder $e) => $e->where('kriteria_id', $v)),
                    )),
                Filter::make('terlambat')->label('Hanya yang terlambat')
                    ->query(fn (Builder $query) => $query->terlambat()),
                Filter::make('syarat_perlu')->label('Hanya elemen syarat perlu')
                    ->query(fn (Builder $query) => $query->whereHas('elemen', fn (Builder $e) => $e->where('syarat_perlu', true))),
                Filter::make('tanpa_pj')->label('Belum ditugaskan')
                    ->query(fn (Builder $query) => $query->whereNull('penanggung_jawab_id')),
            ])
            ->recordActions([
                ViewAction::make()->label('Lihat'),
                EditAction::make()->label('Sunting'),
            ])
            ->toolbarActions([
                // Tanpa aksi massal, menugaskan 137 tagihan di awal periode
                // berarti 137 kali membuka dan menyimpan satu per satu.
                BulkActionGroup::make([
                    BulkAction::make('tugaskan')
                        ->label('Tugaskan penanggung jawab')
                        ->icon('heroicon-o-user-plus')
                        ->schema([
                            Select::make('penanggung_jawab_id')
                                ->label('Penanggung jawab')
                                ->options(fn () => User::bisaDitugaskan()->orderBy('nama_lengkap')
                                    ->pluck('nama_lengkap', 'id')->all())
                                ->searchable()->required()->native(false),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $pj = User::findOrFail($data['penanggung_jawab_id']);
                            $oleh = auth()->user();
                            $berubah = 0;

                            foreach ($records as $t) {
                                // Policy diperiksa ulang per baris: pemilihan
                                // massal tidak boleh menembus lingkup pokja.
                                if (! $oleh->can('tugaskan', $t)) {
                                    continue;
                                }

                                $t->update(['penanggung_jawab_id' => $pj->getKey()]);
                                $pj->notify(new TagihanDitugaskan($t, $oleh));
                                $berubah++;
                            }

                            Notification::make()
                                ->title("{$berubah} tagihan ditugaskan kepada {$pj->nama_lengkap}")
                                ->body($berubah < $records->count()
                                    ? ($records->count() - $berubah).' dilewati karena di luar wewenang Anda.'
                                    : null)
                                ->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('tenggat')
                        ->label('Tetapkan tenggat')
                        ->icon('heroicon-o-calendar-days')
                        ->schema([
                            DatePicker::make('tenggat')->label('Tenggat')
                                ->displayFormat('d F Y')->required()->native(false),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $oleh = auth()->user();
                            $berubah = 0;

                            foreach ($records as $t) {
                                if (! $oleh->can('ubahTenggat', $t)) {
                                    continue;
                                }

                                $t->update(['tenggat' => $data['tenggat']]);
                                $berubah++;
                            }

                            Notification::make()
                                ->title("Tenggat {$berubah} tagihan ditetapkan")
                                ->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('urutan')
            ->paginated([25, 50, 100]);
    }
}
