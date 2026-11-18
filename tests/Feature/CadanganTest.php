<?php

namespace Tests\Feature;

use App\Services\CadanganService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;
use Tests\TestCase;

/**
 * Tahap 6 butir 7: cadangan terenkripsi, pemulihan, dan retensi.
 *
 * Pengujian memakai SQLite berkas (bukan `:memory:`) supaya dump dan
 * pemulihan benar-benar terjadi. Produksi memakai MySQL melalui mysqldump.
 */
class CadanganTest extends TestCase
{
    private string $folder;

    private string $db;

    private string $berkasPrivat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->folder = 'cadangan-uji-'.bin2hex(random_bytes(4));
        $this->db = storage_path('app/'.$this->folder.'.sqlite');

        if (is_file($this->db)) {
            @unlink($this->db);
        }

        $pdo = new PDO('sqlite:'.$this->db);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE rahasia (id INTEGER PRIMARY KEY AUTOINCREMENT, nilai TEXT)');
        $pdo->exec("INSERT INTO rahasia (nilai) VALUES ('NILAI-RAHASIA-123')");
        $pdo = null;

        $this->berkasPrivat = storage_path('app/private/berkas/uji-cadangan.txt');
        File::ensureDirectoryExists(dirname($this->berkasPrivat));
        File::put($this->berkasPrivat, 'BERKAS-RAHASIA');

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->db,
            'spmb.cadangan.kunci' => 'sandi-uji-cadangan',
            'spmb.cadangan.folder' => $this->folder,
            'spmb.cadangan.retensi_harian' => 14,
            'spmb.cadangan.retensi_bulanan' => 6,
        ]);

        DB::purge();
    }

    protected function tearDown(): void
    {
        // Tutup koneksi SQLite lebih dulu; di Windows berkas yang masih
        // terbuka tidak dapat dihapus sehingga sisa uji menumpuk.
        DB::purge();

        File::deleteDirectory(storage_path('app/'.$this->folder));
        File::delete($this->db);
        File::delete($this->berkasPrivat);

        parent::tearDown();
    }

    public function test_arsip_dibuat_dan_terenkripsi(): void
    {
        $path = $this->app->make(CadanganService::class)->buat();

        $this->assertFileExists($path);
        $this->assertSame('SPMBCAD1', file_get_contents($path, false, null, 0, 8));
        $this->assertGreaterThan(40, filesize($path));

        // Tanpa kunci yang benar, arsip tidak bisa dibuka.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Kunci cadangan salah');

        config(['spmb.cadangan.kunci' => 'kunci-yang-salah']);
        $this->app->make(CadanganService::class)->pulihkan($path);
    }

    public function test_pemulihan_mengembalikan_basis_data_dan_berkas_privat(): void
    {
        $service = $this->app->make(CadanganService::class);
        $path = $service->buat();

        // Rusak data setelah cadangan, lalu pulihkan.
        $pdo = new PDO('sqlite:'.$this->db);
        $pdo->exec("DELETE FROM rahasia");
        $pdo = null;

        File::delete($this->berkasPrivat);

        $service->pulihkan($path);

        $pdo = new PDO('sqlite:'.$this->db);
        $nilai = $pdo->query('SELECT nilai FROM rahasia')->fetchColumn();
        $pdo = null;

        $this->assertSame('NILAI-RAHASIA-123', $nilai);
        $this->assertFileExists($this->berkasPrivat);
        $this->assertSame('BERKAS-RAHASIA', File::get($this->berkasPrivat));
    }

    public function test_retensi_menyimpan_harian_dan_satu_per_bulan(): void
    {
        $folder = storage_path('app/'.$this->folder);
        File::ensureDirectoryExists($folder);

        $baru = $folder.'/baru.enc';
        $kemarin = $folder.'/kemarin.enc';
        $bulanIniA = $folder.'/bulan-ini-a.enc';
        $bulanIniB = $folder.'/bulan-ini-b.enc';
        $lamaSekali = $folder.'/lama-sekali.enc';

        File::put($baru, 'x');
        File::put($kemarin, 'x');
        File::put($bulanIniA, 'x');
        File::put($bulanIniB, 'x');
        File::put($lamaSekali, 'x');

        $bulanLalu = now()->startOfMonth()->subMonths(2)->addDays(5);

        touch($baru, now()->subDay()->getTimestamp());
        touch($kemarin, now()->subDays(3)->getTimestamp());
        touch($bulanIniA, $bulanLalu->copy()->getTimestamp());
        touch($bulanIniB, $bulanLalu->copy()->addDay()->getTimestamp());
        touch($lamaSekali, now()->subMonths(8)->getTimestamp());

        $dihapus = $this->app->make(CadanganService::class)->retensi();

        $this->assertFileExists($baru);
        $this->assertFileExists($kemarin);
        $this->assertFileExists($bulanIniA);
        $this->assertFileDoesNotExist($bulanIniB);
        $this->assertFileDoesNotExist($lamaSekali);
        $this->assertContains('bulan-ini-b.enc', $dihapus);
        $this->assertContains('lama-sekali.enc', $dihapus);
    }

    public function test_perintah_cadangan_berhasil(): void
    {
        $this->artisan('spmb:cadangan')
            ->expectsOutputToContain('Cadangan dibuat')
            ->assertExitCode(0);

        $this->assertCount(1, $this->app->make(CadanganService::class)->daftar());
    }

    public function test_pulihkan_menolak_basis_data_berisi_tanpa_force(): void
    {
        $folder = storage_path('app/'.$this->folder);
        File::ensureDirectoryExists($folder);
        $path = $folder.'/palsu.enc';
        File::put($path, 'bukan arsip');

        // Buat tabel yang diperiksa berisi satu baris.
        $pdo = new PDO('sqlite:'.$this->db);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, nama TEXT)');
        $pdo->exec("INSERT INTO users (nama) VALUES ('uji')");
        $pdo = null;

        DB::purge();

        $this->artisan('spmb:pulihkan', ['berkas' => $path])
            ->expectsOutputToContain('Basis data tidak kosong')
            ->assertExitCode(1);
    }

    public function test_pulihkan_menolak_tanpa_konfirmasi(): void
    {
        $service = $this->app->make(CadanganService::class);
        $path = $service->buat();

        // Basis data kosong menurut pemeriksaan: kosongkan tabel yang dicek.
        $pdo = new PDO('sqlite:'.$this->db);
        $pdo->exec('DROP TABLE IF EXISTS users');
        $pdo = null;
        DB::purge();

        $this->artisan('spmb:pulihkan', ['berkas' => $path])
            ->expectsConfirmation('Lanjutkan pemulihan?', 'no')
            ->expectsOutputToContain('Dibatalkan')
            ->assertExitCode(0);
    }
}
