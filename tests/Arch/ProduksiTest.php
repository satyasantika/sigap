<?php

namespace Tests\Arch;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Produksi Profil Ringan (STANDAR-TEKNIS §1b dan §7): basis data MariaDB NATIVE di host,
 * tanpa container database, Redis, atau Horizon; setiap container berbatas memori dan lognya dirotasi.
 */
class ProduksiTest extends TestCase
{
    private function komposProduksi(): array
    {
        return Yaml::parseFile(base_path('compose.prod.yaml'));
    }

    #[Test]
    public function hanya_memuat_layanan_php_web_scheduler_dan_cadangan(): void
    {
        $this->assertEqualsCanonicalizing(
            ['php', 'web', 'scheduler', 'cadangan'],
            array_keys($this->komposProduksi()['services']),
        );
    }

    #[Test]
    public function tidak_memuat_container_database_redis_atau_queue(): void
    {
        $kompos = $this->komposProduksi();

        foreach (['queue', 'horizon', 'redis', 'db', 'mysql', 'mariadb', 'meilisearch'] as $layanan) {
            $this->assertArrayNotHasKey($layanan, $kompos['services']);
        }

        foreach (['redis', 'data', 'db-data'] as $volume) {
            $this->assertArrayNotHasKey($volume, $kompos['volumes'] ?? []);
        }

        // hasil parse (tanpa komentar): tidak ada image/perintah Redis, Horizon, atau SQLite
        $this->assertDoesNotMatchRegularExpression('/horizon|redis|sqlite|mysql:|mariadb:/i', json_encode($kompos));
    }

    #[Test]
    public function menghubungkan_container_ke_basis_data_host_lewat_host_gateway(): void
    {
        $kompos = $this->komposProduksi();

        foreach (['php', 'scheduler', 'cadangan'] as $nama) {
            $this->assertContains('host.docker.internal:host-gateway', $kompos['services'][$nama]['extra_hosts']);
        }

        $this->assertStringContainsString(
            "\nDB_HOST=host.docker.internal\n",
            file_get_contents(base_path('.env.production.example')),
        );
    }

    #[Test]
    public function menjalankan_penjadwal_sebagai_pemicu_worker(): void
    {
        $penjadwal = $this->komposProduksi()['services']['scheduler'];

        $this->assertSame('php artisan schedule:work', $penjadwal['command']);
        $this->assertSame('60s', $penjadwal['stop_grace_period']);
    }

    #[Test]
    public function membatasi_memori_dan_merotasi_log_setiap_container(): void
    {
        foreach ($this->komposProduksi()['services'] as $layanan) {
            $this->assertArrayHasKey('mem_limit', $layanan);
            $this->assertSame('json-file', $layanan['logging']['driver']);
            $this->assertSame(['max-size' => '10m', 'max-file' => '3'], $layanan['logging']['options']);
        }
    }

    #[Test]
    public function menjaga_dockerfile_berbasis_php_85_dengan_klien_mariadb_tanpa_redis_horizon_dan_sqlite(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));

        $this->assertDoesNotMatchRegularExpression('/redis|horizon|sqlite/i', $dockerfile);
        $this->assertStringContainsString('ARG PHP_VERSION=8.5', $dockerfile);
        $this->assertStringContainsString('pdo_mysql', $dockerfile);
        $this->assertStringContainsString('mariadb-client', $dockerfile);
    }

    #[Test]
    public function menyetel_php_fpm_ondemand_dengan_empat_worker(): void
    {
        $pool = file_get_contents(base_path('docker/php/www.conf'));

        $this->assertMatchesRegularExpression('/^pm = ondemand$/m', $pool);
        $this->assertMatchesRegularExpression('/^pm\.max_children = 4$/m', $pool);
        $this->assertMatchesRegularExpression('/^pm\.process_idle_timeout = 10s$/m', $pool);
    }

    #[Test]
    public function tidak_menyisakan_skrip_sqlite_di_docker(): void
    {
        foreach (['docker/scripts/entrypoint.sh', 'docker/scripts/cadangan.sh'] as $berkas) {
            $this->assertDoesNotMatchRegularExpression('/sqlite/i', file_get_contents(base_path($berkas)));
        }
    }

    #[Test]
    public function mensyaratkan_php_85_di_composer_json(): void
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);

        $this->assertSame('^8.5', $composer['require']['php']);
    }
}
