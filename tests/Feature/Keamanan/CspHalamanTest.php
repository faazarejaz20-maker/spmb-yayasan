<?php

namespace Tests\Feature\Keamanan;

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\Feature\UnduhanTestCase;

/**
 * Tahap 6 butir 2.3: CSP enforce harus berlaku di semua jenis halaman —
 * publik, autentikasi, akun, tiga panel, halaman cetak, dan halaman galat.
 *
 * Tes ini memeriksa header pada respons nyata tiap jenis halaman. Pemeriksaan
 * "tidak ada pelanggaran" secara statis ada di HeaderKeamananTest (pindai
 * seluruh berkas Blade untuk gaya/skrip inline).
 */
class CspHalamanTest extends UnduhanTestCase
{
    private function periksa(TestResponse $respons, string $label, int $status = 200): void
    {
        $respons->assertStatus($status);

        $csp = (string) $respons->headers->get('Content-Security-Policy');

        $this->assertNotSame('', $csp, "CSP tidak ada di {$label}.");
        $this->assertNull(
            $respons->headers->get('Content-Security-Policy-Report-Only'),
            "CSP report-only masih aktif di {$label}."
        );
        $this->assertStringNotContainsString('unsafe-inline', $csp, "unsafe-inline di {$label}.");
        $this->assertStringNotContainsString('unsafe-eval', $csp, "unsafe-eval di {$label}.");
        $this->assertStringContainsString("default-src 'self'", $csp, "default-src salah di {$label}.");
        $this->assertStringContainsString("frame-ancestors 'none'", $csp, "frame-ancestors salah di {$label}.");
        $this->assertStringContainsString("object-src 'none'", $csp, "object-src salah di {$label}.");
    }

    public function test_csp_di_halaman_publik_dan_autentikasi(): void
    {
        $this->periksa($this->get('/'), 'beranda');
        $this->periksa($this->get('/sekolah/sd'), 'halaman sekolah');
        $this->periksa($this->get('/kebijakan-privasi'), 'kebijakan privasi');
        $this->periksa($this->get('/masuk'), 'masuk');
        $this->periksa($this->get('/daftar-akun'), 'daftar akun');
        $this->periksa($this->get('/lupa-password'), 'lupa kata sandi');
    }

    public function test_csp_di_akun_dan_halaman_cetak_walisantri(): void
    {
        $pendaftaran = $this->terkirim();

        $this->periksa(
            $this->actingAs($this->wali)->get('/akun'),
            'beranda akun'
        );

        $this->periksa(
            $this->actingAs($this->wali)->get(route('walisantri.pendaftaran.cetak.bukti', $pendaftaran->uuid)),
            'cetak bukti pendaftaran'
        );
    }

    public function test_csp_di_panel_panitia_admin_super_dan_yayasan(): void
    {
        $pendaftaran = $this->terkirim();

        $this->periksa(
            $this->actingAs($this->panitia)->get('/panitia'),
            'beranda panitia'
        );

        $this->periksa(
            $this->actingAs($this->panitia)->get(route('panitia.pendaftar.detail', $pendaftaran->uuid)),
            'detail pendaftar'
        );

        $this->periksa(
            $this->actingAs($this->panitia)->get(route('panitia.pendaftar.cetak.formulir', $pendaftaran->uuid)),
            'cetak formulir'
        );

        $this->periksa(
            $this->actingAs($this->admin)->get('/admin'),
            'beranda admin'
        );

        $this->periksa(
            $this->actingAs(User::factory()->superAdmin()->create())->get('/super'),
            'beranda super admin'
        );

        $this->periksa(
            $this->actingAs(User::factory()->yayasan()->create())->get('/yayasan'),
            'beranda yayasan'
        );
    }

    public function test_csp_di_halaman_galat(): void
    {
        $this->periksa(
            $this->get('/halaman-yang-tidak-ada'),
            'galat 404',
            404
        );

        $this->periksa(
            $this->actingAs($this->wali)->get('/panitia'),
            'galat 403',
            403
        );
    }

    public function test_csp_di_galat_csrf_dan_batas_laju(): void
    {
        // 419 dan 429 juga dirender lewat penangan galat, jadi harus mendapat
        // header yang sama. Lingkungan `testing` melewati CSRF, jadi paksa
        // lingkungan lain untuk 419.
        $this->app['env'] = 'local';

        $this->periksa(
            $this->post('/masuk', [
                'identitas' => '6281234567890',
                'kata_sandi' => 'password123',
            ]),
            'galat 419',
            419
        );

        $this->app['env'] = 'testing';

        for ($i = 0; $i < 6; $i++) {
            $this->post('/masuk', [
                'identitas' => '6281234567890',
                'kata_sandi' => 'kata-sandi-salah',
            ]);
        }

        $this->periksa(
            $this->post('/masuk', [
                'identitas' => '6281234567890',
                'kata_sandi' => 'kata-sandi-salah',
            ]),
            'galat 429',
            429
        );
    }
}
