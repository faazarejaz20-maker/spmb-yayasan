<?php

namespace Tests\Feature\Keamanan;

use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\UnduhanTestCase;

/**
 * Butir 11 dan 10 Tahap 5: unduhan rekap admin dan yayasan hanya memuat angka
 * agregat, tanpa nama atau nomor peserta; yayasan tidak punya ekspor per orang.
 */
class EksporRekapTest extends UnduhanTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Cache::flush();
    }

    public function test_admin_mengunduh_rekap_sekolah_sendiri(): void
    {
        $this->terkirim();

        $respons = $this->actingAs($this->admin)->get(route('admin.rekap.unduh'));

        $respons->assertOk()->assertDownload();
        $this->assertStringContainsString('no-store', (string) $respons->headers->get('Cache-Control'));

        $lembar = $this->bacaXlsx($this->isiUnduhan($respons));

        $this->assertArrayHasKey('Rekap', $lembar);
        $this->assertSame(['Sekolah', 'SDIT Insan Mulia Wonosobo'], $lembar['Rekap'][0]);
    }

    public function test_rekap_admin_tanpa_nama_atau_nomor_peserta(): void
    {
        $pendaftaran = $this->terkirim();

        $respons = $this->actingAs($this->admin)->get(route('admin.rekap.unduh'));
        $respons->assertOk();

        $isi = '';
        foreach ($this->bacaXlsx($this->isiUnduhan($respons)) as $barisLembar) {
            foreach ($barisLembar as $baris) {
                $isi .= implode(' ', array_map('strval', $baris))."\n";
            }
        }

        $this->assertStringNotContainsString((string) $this->anak->nama_lengkap, $isi);
        $this->assertStringNotContainsString((string) $pendaftaran->nomor_peserta, $isi);
        $this->assertStringNotContainsString((string) $this->anak->nik, $isi);
    }

    public function test_rekap_admin_tercatat_di_log(): void
    {
        $this->terkirim();

        $this->actingAs($this->admin)->get(route('admin.rekap.unduh'))->assertOk();

        $log = LogAktivitas::query()->tanpaSekolah()->where('aksi', 'ekspor_rekap')->first();

        $this->assertNotNull($log);
        $this->assertSame($this->admin->id, (int) $log->user_id);
        $this->assertSame($this->sekolah->id, (int) $log->sekolah_id);
        $this->assertSame('sekolah', json_decode((string) $log->data, true)['jenis']);
    }

    public function test_yayasan_mengunduh_rekap_agregat(): void
    {
        $this->terkirim();
        $this->pendaftaranSekolahB();

        $yayasan = User::factory()->yayasan()->create();

        $respons = $this->actingAs($yayasan)->get(route('yayasan.rekap.unduh'));
        $respons->assertOk()->assertDownload();

        $lembar = $this->bacaXlsx($this->isiUnduhan($respons));

        $this->assertArrayHasKey('Rekap', $lembar);

        $isi = '';
        foreach ($lembar['Rekap'] as $baris) {
            $isi .= implode(' ', array_map('strval', $baris))."\n";
        }

        // Kedua sekolah muncul sebagai agregat.
        $this->assertStringContainsString('SDIT Insan Mulia Wonosobo', $isi);
        $this->assertStringContainsString('SMPIT Insan Mulia Wonosobo', $isi);

        // Tidak ada data per orang.
        $this->assertStringNotContainsString((string) $this->anak->nama_lengkap, $isi);
        $this->assertStringNotContainsString((string) $this->anak->nik, $isi);
        $this->assertStringNotContainsString((string) $this->pendaftaran->nomor_peserta, $isi);
    }

    public function test_yayasan_tidak_punya_ekspor_per_orang(): void
    {
        $this->terkirim();
        $yayasan = User::factory()->yayasan()->create();

        $this->actingAs($yayasan)->get(route('panitia.ekspor.index'))->assertForbidden();
        $this->actingAs($yayasan)->post(route('panitia.ekspor.unduh'), ['format' => 'csv'])->assertForbidden();
        $this->actingAs($yayasan)->get(route('admin.rekap.unduh'))->assertForbidden();
    }

    public function test_super_admin_tidak_punya_rekap_pendaftar(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->get(route('admin.rekap.unduh'))->assertForbidden();
        $this->actingAs($super)->get(route('yayasan.rekap.unduh'))->assertForbidden();
    }

    public function test_panitia_tidak_boleh_rekap_admin(): void
    {
        $this->actingAs($this->panitia)->get(route('admin.rekap.unduh'))->assertForbidden();
    }

    public function test_tamu_diarahkan_ke_masuk(): void
    {
        $this->get(route('admin.rekap.unduh'))->assertRedirect(route('masuk'));
        $this->get(route('yayasan.rekap.unduh'))->assertRedirect(route('masuk'));
    }
}
