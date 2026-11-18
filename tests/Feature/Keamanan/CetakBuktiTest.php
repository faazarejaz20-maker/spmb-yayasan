<?php

namespace Tests\Feature\Keamanan;

use App\Enums\StatusHasil;
use App\Models\Pengumuman;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\Feature\UnduhanTestCase;

/**
 * Butir 1, 7, dan 10 Tahap 5: cetak bukti pendaftaran tidak membocorkan hasil
 * sebelum pengumuman, NIK disamarkan, hanya pemilik/panitia sekolah sendiri,
 * draf tidak punya halaman cetak, header aman, dan log.
 */
class CetakBuktiTest extends UnduhanTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function umumkan(): void
    {
        Pengumuman::create([
            'sekolah_id' => $this->sekolah->id,
            'gelombang_id' => $this->gelombang->id,
            'diumumkan_oleh' => $this->admin->id,
            'diumumkan_pada' => now(),
            'teks_pesan' => 'Hasil sudah diumumkan.',
        ]);
    }

    public function test_sebelum_pengumuman_hasil_tidak_bocor(): void
    {
        $pendaftaran = $this->terkirim();
        $this->aturStatus($pendaftaran, StatusHasil::Diterima);

        $respons = $this->actingAs($this->wali)
            ->get(route('walisantri.pendaftaran.cetak.bukti', $pendaftaran->uuid));

        $respons->assertOk();
        $respons->assertDontSee('Diterima');
        $respons->assertDontSee('Ditolak');
        $respons->assertDontSee('Cadangan');
        $respons->assertSee('menunggu pengumuman');
    }

    public function test_status_ditolak_juga_tidak_bocor_sebelum_pengumuman(): void
    {
        $pendaftaran = $this->terkirim();
        $this->aturStatus($pendaftaran, StatusHasil::Ditolak);

        $this->actingAs($this->wali)
            ->get(route('walisantri.pendaftaran.cetak.bukti', $pendaftaran->uuid))
            ->assertOk()
            ->assertDontSee('Ditolak')
            ->assertDontSee('Belum diterima');
    }

    public function test_setelah_pengumuman_hasil_tampil(): void
    {
        $pendaftaran = $this->terkirim();
        $this->aturStatus($pendaftaran, StatusHasil::Diterima);
        $this->umumkan();

        $this->actingAs($this->wali)
            ->get(route('walisantri.pendaftaran.cetak.bukti', $pendaftaran->uuid))
            ->assertOk()
            ->assertSee('Diterima');
    }

    public function test_nik_disamarkan_empat_digit_terakhir(): void
    {
        $pendaftaran = $this->terkirim();

        $respons = $this->actingAs($this->wali)
            ->get(route('walisantri.pendaftaran.cetak.bukti', $pendaftaran->uuid));

        $respons->assertOk();
        $respons->assertDontSee((string) $this->anak->nik);
        $respons->assertSee('************0001');
    }

    public function test_panitia_sekolah_sendiri_boleh_mencetak(): void
    {
        $pendaftaran = $this->terkirim();

        foreach ([$this->panitia, $this->admin] as $pengguna) {
            $this->actingAs($pengguna)
                ->get(route('panitia.pendaftar.cetak.bukti', $pendaftaran->uuid))
                ->assertOk()
                ->assertSee((string) $pendaftaran->nomor_peserta);
        }
    }

    public function test_walisantri_lain_404(): void
    {
        $pendaftaran = $this->terkirim();
        $lain = User::factory()->walisantri()->create();

        $this->actingAs($lain)
            ->get(route('walisantri.pendaftaran.cetak.bukti', $pendaftaran->uuid))
            ->assertNotFound();
    }

    public function test_sekolah_lain_404(): void
    {
        $lain = $this->pendaftaranSekolahB();

        $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.cetak.bukti', $lain->uuid))
            ->assertNotFound();
    }

    public function test_draf_tidak_punya_halaman_cetak_bukti(): void
    {
        // Pendaftaran bawaan masih draf.
        $this->assertTrue($this->pendaftaran->masihDraf());

        $this->actingAs($this->wali)
            ->get(route('walisantri.pendaftaran.cetak.bukti', $this->pendaftaran->uuid))
            ->assertNotFound();

        $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.cetak.bukti', $this->pendaftaran->uuid))
            ->assertNotFound();
    }

    public function test_tamu_diarahkan_ke_masuk(): void
    {
        $pendaftaran = $this->terkirim();

        $this->get(route('walisantri.pendaftaran.cetak.bukti', $pendaftaran->uuid))
            ->assertRedirect(route('masuk'));
    }

    public function test_super_admin_dan_yayasan_tidak_boleh(): void
    {
        $pendaftaran = $this->terkirim();

        foreach ([User::factory()->superAdmin()->create(), User::factory()->yayasan()->create()] as $pengguna) {
            $this->actingAs($pengguna)
                ->get(route('panitia.pendaftar.cetak.bukti', $pendaftaran->uuid))
                ->assertForbidden();
        }
    }

    public function test_header_aman_dan_tanpa_sidebar(): void
    {
        $pendaftaran = $this->terkirim();

        $respons = $this->actingAs($this->wali)
            ->get(route('walisantri.pendaftaran.cetak.bukti', $pendaftaran->uuid));

        $respons->assertOk();
        $this->assertStringContainsString('no-store', (string) $respons->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $respons->headers->get('X-Content-Type-Options'));

        // Tata letak cetak: tidak memuat menu panel dan aturan cetak berada di
        // berkas CSS (bukan <style> inline) agar CSP `style-src 'self'` aman.
        $respons->assertDontSee('Keluar');
        $respons->assertDontSee('@page', false);
        $respons->assertDontSee('<style', false);
        $this->assertStringContainsString("style-src 'self'", (string) $respons->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('unsafe-inline', (string) $respons->headers->get('Content-Security-Policy'));
    }

    public function test_cetak_tercatat_di_log(): void
    {
        $pendaftaran = $this->terkirim();

        $this->actingAs($this->wali)
            ->get(route('walisantri.pendaftaran.cetak.bukti', $pendaftaran->uuid))
            ->assertOk();

        $log = \App\Models\LogAktivitas::query()->tanpaSekolah()
            ->where('aksi', 'cetak_bukti')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($this->wali->id, (int) $log->user_id);
        $this->assertSame($this->sekolah->id, (int) $log->sekolah_id);
    }

    public function test_nama_dengan_karakter_khusus_ter_escape(): void
    {
        $pendaftaran = $this->terkirim();
        $pendaftaran->anak->forceFill([
            'nama_lengkap' => '<script>alert("xss")</script>',
        ])->save();

        $respons = $this->actingAs($this->wali)
            ->get(route('walisantri.pendaftaran.cetak.bukti', $pendaftaran->uuid));

        $respons->assertOk();
        $respons->assertDontSee('<script>alert("xss")</script>', false);
        $respons->assertSee('&lt;script&gt;', false);
    }
}
