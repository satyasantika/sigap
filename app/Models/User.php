<?php

namespace App\Models;

use App\Enums\PeranPengguna;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'nama_lengkap',
        'nidn',
        'jabatan',
        'prodi_id',
        'peran',
        'aktif',
        'demo',
        'periode_demo_id',
        'terakhir_masuk_pada',
        'wajib_ganti_sandi',
        'impor_batch_id',
        'kunci_normal',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'peran' => PeranPengguna::class,
            'aktif' => 'boolean',
            'demo' => 'boolean',
            'wajib_ganti_sandi' => 'boolean',
            'terakhir_masuk_pada' => 'datetime',
        ];
    }

    /**
     * Pengguna yang boleh dipilih sebagai penanggung jawab, penilai, atau
     * koordinator.
     *
     * Aktif DAN bukan akun demo. Akun demo hidup hanya selama demonstrasi
     * berjalan; menugaskan tagihan sungguhan kepadanya berarti tagihan itu
     * kehilangan penanggung jawab saat demonya dibuang.
     *
     * Sengaja satu scope bernama, bukan `where('aktif', true)` yang diulang di
     * lima layar — lima tempat yang harus diingat adalah lima tempat yang bisa
     * terlewat.
     */
    public function scopeBisaDitugaskan(Builder $q): Builder
    {
        return $q->where('aktif', true)->where('demo', false);
    }

    public function scopeBukanDemo(Builder $q): Builder
    {
        return $q->where('demo', false);
    }

    /** Akun ini lahir dari sebuah demonstrasi, bukan dari daftar pengguna. */
    public function akunDemo(): bool
    {
        return (bool) $this->demo;
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    public function pokja(): BelongsToMany
    {
        return $this->belongsToMany(Pokja::class, 'pokja_user')
            ->withPivot('peran_dalam_pokja');
    }

    public function tagihan(): HasMany
    {
        return $this->hasMany(Tagihan::class, 'penanggung_jawab_id');
    }

    /** Pokja yang dikoordinasi pengguna ini. */
    public function pokjaDikoordinasi(): HasMany
    {
        return $this->hasMany(Pokja::class, 'koordinator_id');
    }

    /**
     * Pengguna nonaktif ditolak masuk panel dengan pesan yang jelas.
     * vibecoding/docs/08-auth-dan-izin.md bagian 1 butir 6.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->aktif;
    }

    /** Id pokja yang diikuti, dipakai lingkup `pokjanya` pada kelas Izin. */
    public function idPokjanya(): array
    {
        return $this->pokja()->pluck('pokja.id')->all();
    }

    /** Lingkup `pokja_data` bergantung keanggotaan, bukan peran. */
    public function anggotaPokjaData(): bool
    {
        return $this->pokja()->where('pokja.kode', 'POKJA-DATA')->exists();
    }
}
