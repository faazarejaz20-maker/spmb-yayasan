<?php

namespace Tests\Feature\Keamanan;

use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Tahap 6 butir 4: CSP enforce dan header keamanan lain.
 */
class HeaderKeamananTest extends TestCase
{
    use RefreshDatabase;

    public function test_csp_enforce_di_halaman_publik(): void
    {
        $respons = $this->get('/');

        $respons->assertOk();

        $csp = (string) $respons->headers->get('Content-Security-Policy');

        $this->assertNotSame('', $csp);
        $this->assertNull($respons->headers->get('Content-Security-Policy-Report-Only'));
        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("style-src 'self'", $csp);
        $this->assertStringContainsString("img-src 'self' data:", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);

        $this->assertSame('DENY', $respons->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $respons->headers->get('X-Content-Type-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $respons->headers->get('Referrer-Policy'));
        $this->assertStringContainsString('camera=(self)', (string) $respons->headers->get('Permissions-Policy'));
    }

    public function test_csp_enforce_di_panel_dan_halaman_cetak(): void
    {
        $sekolah = Sekolah::factory()->create();
        $panitia = User::factory()->panitia($sekolah)->create();

        $panel = $this->actingAs($panitia)->get('/panitia');
        $panel->assertOk();

        $csp = (string) $panel->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("style-src 'self'", $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
    }

    public function test_hsts_tidak_ada_di_luar_produksi(): void
    {
        $respons = $this->get('https://localhost/');

        $this->assertNull($respons->headers->get('Strict-Transport-Security'));
    }

    public function test_hsts_hanya_produksi_dan_https(): void
    {
        $this->app['env'] = 'production';

        $aman = $this->get('https://localhost/');
        $this->assertSame(
            'max-age=31536000; includeSubDomains',
            $aman->headers->get('Strict-Transport-Security')
        );

        // Produksi tetapi permintaan HTTP biasa: HSTS tidak dikirim.
        config(['app.url' => 'http://localhost']);
        $http = $this->get('http://localhost/');
        $this->assertNull($http->headers->get('Strict-Transport-Security'));
    }

    /**
     * CSP `style-src 'self'` dan `script-src 'self'` hanya terpenuhi bila
     * tampilan tidak memuat gaya/skrip inline. Tes ini menjaga agar tidak ada
     * yang menambahkannya kembali tanpa sengaja.
     */
    public function test_tampilan_tidak_memakai_gaya_atau_skrip_inline(): void
    {
        $pelanggaran = [];

        foreach (File::allFiles(resource_path('views')) as $berkas) {
            $isi = File::get($berkas->getPathname());

            if (preg_match('/<style\b|\sstyle\s*=|javascript:|\son[a-z]+\s*=\s*["\']/i', $isi) === 1) {
                $pelanggaran[] = $berkas->getRelativePathname();
            }
        }

        $this->assertSame([], $pelanggaran, 'Tampilan memuat gaya/skrip inline yang dilarang CSP.');
    }

    public function test_tidak_ada_rute_uji_atau_alat_debug(): void
    {
        $terlarang = ['test', 'phpinfo', 'telescope', '_debugbar', '_ignition/health-check'];

        foreach (Route::getRoutes() as $rute) {
            $uri = ltrim($rute->uri(), '/');

            foreach ($terlarang as $awalan) {
                $this->assertFalse(
                    $uri === $awalan || str_starts_with($uri, $awalan.'/'),
                    'Rute uji/debug tidak boleh ada di produksi: '.$uri
                );
            }
        }

        $this->assertFalse(Route::has('telescope'));
        $this->assertFalse(Route::has('debugbar.openhandler'));
    }
}
