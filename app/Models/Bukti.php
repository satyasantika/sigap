<?php

namespace App\Models;

use App\Enums\AksesTautan;
use App\Enums\JenisBukti;
use App\Enums\SumberData;
use App\Enums\ValidasiBukti;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bukti extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'bukti';

    /**
     * Cap waktu ditulis sampai milidetik, bukan detik.
     *
     * Kolomnya sudah timestamp(3), tetapi Laravel tetap menulis "Y-m-d H:i:s"
     * kecuali format ini dinyatakan. Tanpa milidetik, suntingan yang terjadi
     * pada detik yang sama dengan impornya tidak terlihat, dan pagar
     * "pembatalan ditutup setelah barisnya disunting orang lain" tidak pernah
     * menutup.
     */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'prodi_id', 'periode_id', 'judul', 'deskripsi', 'keterangan', 'jenis',
        'path', 'nama_asli', 'mime', 'ukuran', 'sha256',
        'url', 'penyedia', 'url_kanonik', 'tautan_id', 'tautan_bentuk',
        'tanggal_kejadian', 'sumber', 'diunggah_oleh',
        'versi', 'bukti_induk_id', 'kunci_normal', 'impor_batch_id',
    ];

    /**
     * akses_* diisi PemeriksaTautan, validasi_* diisi PengelolaBukti —
     * keduanya lewat forceFill supaya tidak ada yang bisa menyatakan
     * buktinya terbuka atau sah lewat pembaruan biasa.
     */
    protected $attributes = [
        'akses_status' => 'belum_diperiksa',
        'validasi_status' => 'belum_divalidasi',
        'akses_percobaan' => 0,
        'versi' => 1,
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisBukti::class,
            'sumber' => SumberData::class,
            'akses_status' => AksesTautan::class,
            'validasi_status' => ValidasiBukti::class,
            'tanggal_kejadian' => 'date',
            'akses_diperiksa_pada' => 'datetime',
            'divalidasi_pada' => 'datetime',
            'ukuran' => 'integer',
            'versi' => 'integer',
            'akses_percobaan' => 'integer',
        ];
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diunggah_oleh');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'divalidasi_oleh');
    }

    public function induk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'bukti_induk_id');
    }

    public function versiBaru(): HasMany
    {
        return $this->hasMany(self::class, 'bukti_induk_id');
    }

    public function elemen(): BelongsToMany
    {
        return $this->belongsToMany(Elemen::class, 'bukti_elemen')
            ->using(BuktiElemen::class)
            ->withPivot(['id', 'keterangan'])
            ->withTimestamps();
    }

    public function tagihan(): BelongsToMany
    {
        return $this->belongsToMany(Tagihan::class, 'bukti_tagihan');
    }

    public function dkpsBaris(): BelongsToMany
    {
        return $this->belongsToMany(DkpsBaris::class, 'bukti_dkps_baris');
    }

    public function komentar(): MorphMany
    {
        return $this->morphMany(Komentar::class, 'commentable')->latest('created_at');
    }

    /**
     * Pokja pemilik bukti ini, diturunkan dari elemen yang ditopangnya.
     *
     * Dipakai lingkup `pokjanya` pada kelas Izin. Bukti yang belum menopang
     * elemen mana pun tidak punya pokja, dan itu disengaja: ia belum menjadi
     * urusan pokja siapa pun.
     */
    public function getPokjaIdAttribute(): ?string
    {
        $kode = $this->elemen->pluck('pokja_kode')->first();

        if ($kode === null) {
            return null;
        }

        return Pokja::where('periode_id', $this->periode_id)
            ->where('kode', $kode)->value('id');
    }

    /** Antrean validasi: yang belum diputuskan dan yang diragukan. */
    public function scopePerluDitinjau(Builder $q): Builder
    {
        return $q->whereIn('validasi_status', ValidasiBukti::perluDitinjau());
    }

    /** Bukti yang tidak bisa dibuka asesor atau dinyatakan tidak sah. */
    public function scopeBermasalah(Builder $q): Builder
    {
        return $q->where(fn (Builder $q) => $q
            ->whereIn('akses_status', [
                AksesTautan::PerluIzin, AksesTautan::TidakDitemukan, AksesTautan::GagalPeriksa,
            ])
            ->orWhere('validasi_status', ValidasiBukti::TidakSah));
    }

    /** Kedua sumbu hijau. */
    public function layakDipakai(): bool
    {
        return $this->aksesLayak() && $this->validasi_status->bolehDisetujui();
    }

    /**
     * Berkas terunggah tidak punya sumbu keterbacaan: ia ada di disk kita
     * sendiri, jadi asesor pasti bisa membukanya lewat ekspor.
     */
    public function aksesLayak(): bool
    {
        return $this->jenis === JenisBukti::Berkas
            || $this->akses_status->bolehDisetujui();
    }

    /**
     * Alasan bukti ini belum layak, satu per satu.
     * Dua sumbu disebut TERPISAH — menggabungkannya menyembunyikan salah satu.
     *
     * @return array<int, string>
     */
    public function alasanBelumLayak(): array
    {
        $alasan = [];

        if (! $this->aksesLayak()) {
            $alasan[] = "\"{$this->judul}\" ".$this->akses_status->alasanPenolakan().'.';
        }

        if (! $this->validasi_status->bolehDisetujui()) {
            $alasan[] = "\"{$this->judul}\" ".$this->validasi_status->alasanPenolakan().'.';
        }

        return $alasan;
    }
}
