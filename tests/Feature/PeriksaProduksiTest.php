<?php

namespace Tests\Feature;

use App\Support\KonfigurasiProduksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

/**
 * Tahap 6 butir 5–6: penjaga boot produksi dan perintah pemeriksaan.
 */
class PeriksaProduksiTest extends TestCase
{
    use RefreshDatabase;

    public function test_penjaga_boot_menolak_debug_aktif(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_DEBUG harus false');

        KonfigurasiProduksi::pastikan(true, true, 'base64:kunci');
    }

    public function test_penjaga_boot_menolak_kunci_kosong(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_KEY kosong');

        KonfigurasiProduksi::pastikan(true, false, '');
    }

    public function test_penjaga_boot_mengizinkan_non_produksi(): void
    {
        KonfigurasiProduksi::pastikan(false, true, '');

        $this->assertTrue(true);
    }

    public function test_perintah_gagal_untuk_konfigurasi_rusak(): void
    {
        $this->app['env'] = 'production';

        config([
            'app.debug' => true,
            'app.key' => '',
            'app.url' => 'http://contoh.test',
            'session.secure' => false,
            'session.http_only' => false,
            'session.same_site' => null,
            'queue.default' => 'sync',
            'cache.default' => 'array',
            'session.driver' => 'array',
        ]);

        $this->artisan('spmb:periksa-produksi')
            ->expectsOutputToContain('GAGAL')
            ->assertExitCode(1);
    }

    public function test_perintah_lulus_untuk_konfigurasi_benar(): void
    {
        $vendor = storage_path('app/tmp/vendor-uji-'.bin2hex(random_bytes(4)));

        File::ensureDirectoryExists($vendor.'/composer');
        File::put($vendor.'/composer/installed.json', json_encode(['packages' => []]));

        try {
            $this->app['env'] = 'production';

            config([
                'app.debug' => false,
                'app.key' => 'base64:'.base64_encode(random_bytes(32)),
                'app.url' => 'https://spmb.contoh.test',
                'session.secure' => true,
                'session.http_only' => true,
                'session.same_site' => 'lax',
                'session.driver' => 'database',
                'queue.default' => 'database',
                'cache.default' => 'database',
                'spmb.produksi.vendor_path' => $vendor,
            ]);

            $this->artisan('spmb:periksa-produksi')
                ->expectsOutputToContain('Semua pemeriksaan LULUS')
                ->assertExitCode(0);
        } finally {
            File::deleteDirectory($vendor);
        }
    }
}
