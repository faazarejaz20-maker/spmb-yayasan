<?php

namespace Tests\Feature\Keamanan;

use App\Models\DaftarUlang;
use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\Feature\UnduhanTestCase;

/**
 * Butir 8 dan 10 Tahap 5: cetak formulir memuat data lengkap dengan NIK utuh
 * (arsip sekolah), tetapi tidak pernah memuat `catatan_panitia` maupun
 * `catatan_wawancara`; semua nilai ter-escape; Policy dan isolasi sekolah.
 */
class CetakFormulirTest extends UnduhanTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_formulir_memuat_nik_utuh_untuk_arsip(): void
    {
        $pendaftaran = $this->terkirim();

        $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.cetak.formulir', $pendaftaran->uuid))
            ->assertOk()
            ->assertSee((string) $this->anak->nik);
    }

    public function test_formulir_tidak_memuat_catatan_panitia(): void
    {
        $pendaftaran = $this->terkirim();
        $pendaftaran->forceFill(['catatan_panitia' => 'CATATAN-INTERNAL-RAHASIA'])->save();

        $respons = $this->actingAs($this->admin)
            ->get(route('panitia.pendaftar.cetak.formulir', $pendaftaran->uuid));

        $respons->assertOk();
        $respons->assertDontSee('CATATAN-INTERNAL-RAHASIA');
        $respons->assertDontSee('catatan_panitia');
    }

    public function test_formulir_tidak_memuat_catatan_wawancara(): void
    {
        $pendaftaran = $this->terkirim();

        DaftarUlang::create([
            'pendaftaran_id' => $pendaftaran->id,
            'hadir' => true,
            'kekurangan' => 'KEKURANGAN-DAFTAR-ULANG',
            'catatan_wawancara' => 'CATATAN-WAWANCARA-RAHASIA',
            'selesai' => false,
        ]);

        $respons = $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.cetak.formulir', $pendaftaran->uuid));

        $respons->assertOk();
        $respons->assertDontSee('CATATAN-WAWANCARA-RAHASIA');
        $respons->assertDontSee('catatan_wawancara');
    }

    public function test_formulir_memuat_data_orang_tua_dan_tanda_tangan(): void
    {
        $pendaftaran = $this->terkirim();
        $this->lengkapiOrangTua();

        $respons = $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.cetak.formulir', $pendaftaran->uuid));

        $respons->assertOk();
        $respons->assertSee('Bapak Contoh');
        $respons->assertSee('Tanda tangan wali murid');
        $respons->assertSee('Panitia SPMB');
    }

    public function test_xss_ter_escape(): void
    {
        $pendaftaran = $this->terkirim();
        $pendaftaran->anak->forceFill([
            'nama_lengkap' => '<script>alert("xss")</script>',
            'alamat' => '"><img src=x onerror=alert(1)>',
        ])->save();

        $respons = $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.cetak.formulir', $pendaftaran->uuid));

        $respons->assertOk();
        $respons->assertDontSee('<script>alert("xss")</script>', false);
        $respons->assertDontSee('<img src=x onerror=alert(1)>', false);
        $respons->assertSee('&lt;script&gt;', false);
    }

    public function test_walisantri_dan_peran_lain_tidak_boleh(): void
    {
        $pendaftaran = $this->terkirim();

        foreach ([
            $this->wali,
            User::factory()->superAdmin()->create(),
            User::factory()->yayasan()->create(),
        ] as $pengguna) {
            $this->actingAs($pengguna)
                ->get(route('panitia.pendaftar.cetak.formulir', $pendaftaran->uuid))
                ->assertForbidden();
        }
    }

    public function test_sekolah_lain_dan_draf_404(): void
    {
        $lain = $this->pendaftaranSekolahB();

        $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.cetak.formulir', $lain->uuid))
            ->assertNotFound();

        $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.cetak.formulir', $this->pendaftaran->uuid))
            ->assertNotFound();
    }

    public function test_header_aman(): void
    {
        $pendaftaran = $this->terkirim();

        $respons = $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.cetak.formulir', $pendaftaran->uuid));

        $respons->assertOk();
        $this->assertStringContainsString('no-store', (string) $respons->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $respons->headers->get('X-Content-Type-Options'));
    }

    public function test_cetak_formulir_tercatat_di_log(): void
    {
        $pendaftaran = $this->terkirim();

        $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.cetak.formulir', $pendaftaran->uuid))
            ->assertOk();

        $log = LogAktivitas::query()->tanpaSekolah()->where('aksi', 'cetak_formulir')->first();

        $this->assertNotNull($log);
        $this->assertSame($this->panitia->id, (int) $log->user_id);
        $this->assertSame($this->sekolah->id, (int) $log->sekolah_id);
        // Log tidak memuat isi data pribadi.
        $this->assertStringNotContainsString((string) $this->anak->nik, (string) $log->data);
    }
}
