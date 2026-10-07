<?php

namespace Database\Seeders;

use App\Enums\PeranPengguna;
use App\Enums\StatusPeriode;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Satu prodi, satu periode, enam pokja dari data/pokja.json, dan satu pengguna
 * contoh per peran.
 *
 * Idempoten lewat kunci alami (prodi.kode, periode.nama, pokja.kode,
 * users.email) — id boleh berbeda tiap bangun ulang.
 */
class OrganisasiSeeder extends Seeder
{
    public function run(): void
    {
        $prodi = Prodi::updateOrCreate(
            ['kode' => 'PPG-UNSIL'],
            [
                'nama' => 'Pendidikan Profesi Guru',
                'jenjang' => 'ppg',
                'upps' => 'Fakultas Keguruan dan Ilmu Pendidikan',
                'perguruan_tinggi' => 'Universitas Siliwangi',
                'aktif' => true,
            ],
        );

        // ts_tahun sengaja ditulis di seeder, bukan di kueri atau logika.
        // aturan proyek 3 melarang tahun mati di app/ dan migrasi, bukan di
        // data awal — justru di sinilah satu-satunya tempatnya ditetapkan.
        $periode = Periode::updateOrCreate(
            ['prodi_id' => $prodi->id, 'nama' => 'PPG 2027'],
            [
                'ts_tahun' => 2027,
                'versi_instrumen' => 'IAPSK 3.0',
                'status' => StatusPeriode::Berjalan,
            ],
        );

        $berkas = base_path('data/pokja.json');

        if (! is_file($berkas)) {
            throw new RuntimeException("data/pokja.json tidak ditemukan di {$berkas}.");
        }

        $daftarPokja = json_decode(file_get_contents($berkas), true, flags: JSON_THROW_ON_ERROR);

        if (count($daftarPokja) !== 6) {
            throw new RuntimeException('Seharusnya 6 pokja di data/pokja.json.');
        }

        foreach ($daftarPokja as $p) {
            Pokja::updateOrCreate(
                ['periode_id' => $periode->id, 'kode' => $p['kode']],
                ['nama' => $p['nama'], 'catatan' => $p['catatan'] ?? null],
            );
        }

        $contoh = [
            ['admin', 'Administrator Sistem', 'admin@sigap.test'],
            ['ketua', 'Ketua Task Force', 'ketua@sigap.test'],
            ['pimpinan', 'Pimpinan Fakultas', 'pimpinan@sigap.test'],
            ['koordinator', 'Koordinator Pokja Pendidikan', 'koordinator@sigap.test'],
            ['anggota', 'Anggota Pokja Pendidikan', 'anggota@sigap.test'],
            ['auditor', 'Auditor Mutu Internal', 'auditor@sigap.test'],
        ];

        foreach ($contoh as [$peran, $nama, $surel]) {
            $u = User::updateOrCreate(
                ['email' => $surel],
                [
                    'name' => $nama,
                    'nama_lengkap' => $nama,
                    'peran' => PeranPengguna::from($peran),
                    'prodi_id' => $prodi->id,
                    'aktif' => true,
                    'password' => 'password',
                    'wajib_ganti_sandi' => true,
                ],
            );

            // Koordinator dan anggota contoh ditempatkan di POKJA-DIK supaya
            // lingkup `pokjanya` punya arti sejak seed pertama.
            if (in_array($peran, ['koordinator', 'anggota'], true)) {
                $dik = Pokja::where('periode_id', $periode->id)->where('kode', 'POKJA-DIK')->first();
                $u->pokja()->syncWithoutDetaching([$dik->id => ['peran_dalam_pokja' => $peran]]);

                if ($peran === 'koordinator') {
                    $dik->update(['koordinator_id' => $u->id]);
                }
            }
        }

        $this->command?->info('Organisasi: 1 prodi, 1 periode, '.count($daftarPokja).' pokja, '.count($contoh).' pengguna');
    }
}
