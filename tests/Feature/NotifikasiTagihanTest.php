<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusPeriode;
use App\Enums\StatusTagihan;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Prodi;
use App\Models\Tagihan;
use App\Models\User;
use App\Notifications\TagihanDikembalikan;
use App\Services\AlurTagihan;
use Database\Seeders\IzinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Dua kejadian saja: tagihan ditugaskan dan tagihan dikembalikan.
 *
 * vibecoding/docs/04-peran-dan-alur.md menyebut empat, tetapi prompt tahap 3
 * mempersempitnya menjadi dua. Dua yang lain belum dibangun dan itu disengaja —
 * lihat laporan akhir tahap ini.
 */
class NotifikasiTagihanTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function penanggung_jawab_diberi_tahu_saat_tagihannya_dikembalikan(): void
    {
        Notification::fake();

        $this->seed(IzinSeeder::class);

        $prodi = Prodi::create([
            'kode' => 'X', 'nama' => 'X', 'jenjang' => 'ppg',
            'upps' => 'X', 'perguruan_tinggi' => 'X', 'aktif' => true,
        ]);
        $periode = Periode::create([
            'prodi_id' => $prodi->id, 'nama' => 'P', 'ts_tahun' => 2027,
            'versi_instrumen' => 'IAPSK 3.0', 'status' => StatusPeriode::Berjalan,
        ]);
        $pokja = Pokja::create(['periode_id' => $periode->id, 'kode' => 'POKJA-DIK', 'nama' => 'Dik']);

        $pj = User::create([
            'name' => 'a', 'nama_lengkap' => 'Anggota', 'email' => 'a@notif.test',
            'password' => 'rahasia123', 'peran' => PeranPengguna::Anggota,
            'prodi_id' => $prodi->id, 'aktif' => true,
        ]);
        $pj->pokja()->attach($pokja->id);

        $ketua = User::create([
            'name' => 'k', 'nama_lengkap' => 'Ketua', 'email' => 'k@notif.test',
            'password' => 'rahasia123', 'peran' => PeranPengguna::Ketua,
            'prodi_id' => $prodi->id, 'aktif' => true,
        ]);

        $t = Tagihan::create([
            'prodi_id' => $prodi->id, 'periode_id' => $periode->id, 'pokja_id' => $pokja->id,
            'jenis' => 'bukti', 'judul' => 'Uji', 'penanggung_jawab_id' => $pj->id,
            'bobot_terkait' => 1,
        ]);
        $t->forceFill(['status' => StatusTagihan::Diajukan])->save();

        app(AlurTagihan::class)->pindah($t, StatusTagihan::Dikembalikan, $ketua, 'Kurang lampiran.');

        Notification::assertSentTo($pj, TagihanDikembalikan::class, function ($notif) {
            // Alasan ikut dibawa di isi pemberitahuan, bukan sekadar ditautkan:
            // itu hal pertama yang ingin dibaca orang.
            return $notif->toArray($notif)['catatan'] === 'Kurang lampiran.';
        });
    }
}
