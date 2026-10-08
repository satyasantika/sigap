<?php

namespace App\Support\Impor;

use App\Enums\PeranPengguna;
use App\Models\ImporBatch;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Profil impor untuk pengguna — satu atau banyak lewat tempel-tabel, dipakai
 * dari tombol "Impor tempel" di layar Pengguna (hanya admin).
 *
 * TIDAK terikat periode: pengguna hanya terikat prodi, bukan periode
 * akreditasi — lihat App\Support\Impor\ProfilImpor::cariYangAda() dan
 * App\Livewire\ImporTempel yang membiarkan periodeId null untuk profil ini.
 *
 * Kunci duplikasinya EMAIL ternormalkan: itu satu-satunya pengenal yang
 * wajib dan unik pada tabel users.
 *
 * Tidak ada alur kirim sandi lewat surel (vibecoding/docs/08-auth-dan-izin.md
 * bagian 1) — setiap baris baru mendapat sandi acak 12 karakter yang
 * DISIMPAN SEMENTARA di properti instance ini (lihat sandiBaruDibuat()) agar
 * admin bisa menyalinnya satu kali dari layar hasil impor. Baris yang
 * diperbarui TIDAK mendapat sandi baru — memperbarui diam-diam mengganti
 * sandi orang yang sedang bekerja adalah kesalahan, bukan bantuan.
 */
class ImporPengguna implements MemilikiSandiBaru, ProfilImpor
{
    /** @var array<int, array{email: string, sandi: string}> */
    private array $sandiBaru = [];

    public function nama(): string
    {
        return 'Pengguna';
    }

    public function medan(): array
    {
        return [
            'nama_lengkap' => ['label' => 'Nama lengkap', 'wajib' => true, 'tipe' => 'teks',
                'contoh' => 'Ahmad Fauzi'],
            'email' => ['label' => 'Surel', 'wajib' => true, 'tipe' => 'teks',
                'contoh' => 'ahmad.fauzi@unsil.ac.id'],
            'peran' => ['label' => 'Peran', 'wajib' => true, 'tipe' => 'pilihan',
                'contoh' => 'anggota'],
            'prodi_kode' => ['label' => 'Kode prodi', 'wajib' => false, 'tipe' => 'teks',
                'contoh' => 'PPG-UNSIL'],
            'nidn' => ['label' => 'NIDN', 'wajib' => false, 'tipe' => 'teks', 'contoh' => '0412088001'],
            'jabatan' => ['label' => 'Jabatan', 'wajib' => false, 'tipe' => 'teks',
                'contoh' => 'Koordinator Pokja Pendidikan'],
        ];
    }

    public function kunciDuplikat(array $baris): ?string
    {
        return $baris['_kunci'] ?? null;
    }

    public function normalkan(array $baris): array
    {
        $baris['nama_lengkap'] = trim($baris['nama_lengkap'] ?? '');
        $baris['email'] = mb_strtolower(trim($baris['email'] ?? ''));
        $baris['peran'] = mb_strtolower(trim($baris['peran'] ?? ''));
        $baris['prodi_kode'] = trim($baris['prodi_kode'] ?? '');
        $baris['nidn'] = preg_replace('/\D/', '', (string) ($baris['nidn'] ?? ''));
        $baris['jabatan'] = trim($baris['jabatan'] ?? '');

        $baris['_kunci'] = NormalisasiKunci::dari('pengguna', $baris['email']);

        return $baris;
    }

    public function validasi(array $baris): array
    {
        $galat = [];

        if (blank($baris['nama_lengkap'] ?? null)) {
            $galat[] = 'Nama lengkap kosong.';
        }

        if (blank($baris['email'] ?? null) || ! filter_var($baris['email'], FILTER_VALIDATE_EMAIL)) {
            $galat[] = "Surel `{$baris['email']}` tidak sah.";
        }

        if (PeranPengguna::tryFrom($baris['peran'] ?? '') === null) {
            $galat[] = 'Peran harus salah satu dari: '
                .collect(PeranPengguna::cases())->map(fn ($p) => $p->value)->join(', ').'.';
        }

        if (filled($baris['prodi_kode'] ?? null) && $this->idProdi($baris['prodi_kode']) === null) {
            $galat[] = "Prodi berkode `{$baris['prodi_kode']}` tidak ditemukan.";
        }

        if (filled($baris['nidn'] ?? null) && strlen($baris['nidn']) !== 10) {
            $galat[] = "NIDN `{$baris['nidn']}` bukan sepuluh digit.";
        }

        return $galat;
    }

    public function simpan(array $baris, ImporBatch $batch): Model
    {
        $sandi = Str::password(12, symbols: false);

        $pengguna = User::create([
            'name' => $baris['nama_lengkap'],
            'nama_lengkap' => $baris['nama_lengkap'],
            'email' => $baris['email'],
            'password' => Hash::make($sandi),
            'peran' => $baris['peran'],
            'prodi_id' => $this->idProdi($baris['prodi_kode'] ?? null),
            'nidn' => $baris['nidn'] ?: null,
            'jabatan' => $baris['jabatan'] ?: null,
            'aktif' => true,
            'wajib_ganti_sandi' => true,
            'kunci_normal' => $baris['_kunci'] ?? null,
            'impor_batch_id' => $batch->getKey(),
        ]);

        $this->sandiBaru[] = ['email' => $pengguna->email, 'sandi' => $sandi];

        return $pengguna;
    }

    /**
     * Memperbarui TIDAK mengganti sandi: mengganti sandi orang yang sedang
     * bekerja diam-diam adalah kesalahan, bukan bantuan — konsisten dengan
     * ImporBukti yang juga tidak menimpa kolom yang bukan urusan impor.
     */
    public function perbarui(Model $lama, array $baris, ImporBatch $batch): Model
    {
        $lama->update([
            'name' => $baris['nama_lengkap'],
            'nama_lengkap' => $baris['nama_lengkap'],
            'peran' => $baris['peran'],
            'prodi_id' => $this->idProdi($baris['prodi_kode'] ?? null) ?? $lama->prodi_id,
            'nidn' => $baris['nidn'] ?: $lama->nidn,
            'jabatan' => $baris['jabatan'] ?: $lama->jabatan,
            'impor_batch_id' => $batch->getKey(),
        ]);

        return $lama;
    }

    public function cariYangAda(string $kunci, ?string $periodeId): ?Model
    {
        // Pengguna tidak terikat periode; $periodeId sengaja diabaikan.
        //
        // Dicocokkan lewat EMAIL, bukan kunci_normal: pengguna yang dibuat
        // manual lewat formulir admin (jalur biasa, bukan impor) tidak
        // pernah mengisi kunci_normal, jadi mencocokkan lewat kolom itu
        // membuat baris impor yang emailnya sama dengan pengguna manual
        // lolos sebagai "baru" dan menabrak unique constraint saat disimpan.
        // Kunci sudah berbentuk "pengguna|<email ternormalkan>" (lihat
        // normalkan()), dan email pada baris itu dinormalkan dengan
        // transformasi yang sama (strtolower + trim), sehingga mengambil
        // bagian setelah tanda "|" sudah cukup.
        $email = Str::after($kunci, '|');

        return User::bukanDemo()->where('email', $email)->first();
    }

    public function sandiBaruDibuat(): array
    {
        return $this->sandiBaru;
    }

    private function idProdi(?string $kode): ?string
    {
        if (blank($kode)) {
            return null;
        }

        return Prodi::where('kode', $kode)->value('id');
    }
}
