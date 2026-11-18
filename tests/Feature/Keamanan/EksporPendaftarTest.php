<?php

namespace Tests\Feature\Keamanan;

use App\Enums\StatusBerkas;
use App\Enums\StatusHasil;
use App\Models\Anak;
use App\Models\Berkas;
use App\Models\Gelombang;
use App\Models\LogAktivitas;
use App\Models\Pendaftaran;
use App\Models\RincianBiaya;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\UnduhanTestCase;

/**
 * Butir 1, 2, 3, 4, 5, 10 Tahap 5: matriks akses ekspor, isolasi sekolah,
 * daftar putih kolom, kolom sensitif, formula injection, batas baris, dan log.
 */
class EksporPendaftarTest extends UnduhanTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Cache::flush();
    }

    /**
     * @param  array<string, mixed>  $tambahan
     * @return array<string, mixed>
     */
    private function muatan(array $tambahan = []): array
    {
        return array_merge([
            'format' => 'csv',
            'kolom' => ['nomor_peserta' => 1, 'nama' => 1],
            'saring' => [],
        ], $tambahan);
    }

    // ---------------------------------------------------------------- akses

    public function test_tamu_diarahkan_ke_masuk(): void
    {
        $this->get(route('panitia.ekspor.index'))->assertRedirect(route('masuk'));
        $this->post(route('panitia.ekspor.unduh'), $this->muatan())->assertRedirect(route('masuk'));
    }

    public function test_walisantri_super_admin_dan_yayasan_tidak_punya_akses_ekspor(): void
    {
        $wali = User::factory()->walisantri()->create();
        $super = User::factory()->superAdmin()->create();
        $yayasan = User::factory()->yayasan()->create();

        foreach ([$wali, $super, $yayasan] as $pengguna) {
            $this->actingAs($pengguna)->get(route('panitia.ekspor.index'))->assertForbidden();
            $this->actingAs($pengguna)->post(route('panitia.ekspor.unduh'), $this->muatan())->assertForbidden();
        }
    }

    public function test_panitia_dan_admin_sekolah_sendiri_boleh_membuka_halaman_dan_mengunduh(): void
    {
        $this->terkirim();

        foreach ([$this->panitia, $this->admin] as $pengguna) {
            $this->actingAs($pengguna)->get(route('panitia.ekspor.index'))->assertOk();

            $respons = $this->actingAs($pengguna)->post(route('panitia.ekspor.unduh'), $this->muatan());
            $respons->assertOk()->assertDownload();
        }
    }

    // ------------------------------------------------------------- isolasi

    public function test_ekspor_sekolah_a_tidak_memuat_baris_sekolah_b(): void
    {
        $this->terkirim();
        $lain = $this->pendaftaranSekolahB();

        $respons = $this->actingAs($this->panitia)->post(route('panitia.ekspor.unduh'), $this->muatan());
        $respons->assertOk();

        $isi = $this->isiUnduhan($respons);

        $this->assertStringContainsString((string) $this->pendaftaran->nomor_peserta, $isi);
        $this->assertStringNotContainsString((string) $lain->nomor_peserta, $isi);
    }

    public function test_panitia_sekolah_b_tidak_mendapat_baris_sekolah_a(): void
    {
        $this->terkirim();
        $this->pendaftaranSekolahB();

        $respons = $this->actingAs($this->panitiaB)->post(route('panitia.ekspor.unduh'), $this->muatan());
        $respons->assertOk();

        $isi = $this->isiUnduhan($respons);

        $this->assertStringNotContainsString((string) $this->pendaftaran->nomor_peserta, $isi);
    }

    public function test_gelombang_sekolah_lain_ditolak(): void
    {
        $this->terkirim();

        $this->actingAs($this->panitia)
            ->post(route('panitia.ekspor.unduh'), $this->muatan([
                'saring' => ['gelombang' => $this->gelombangB->id],
            ]))
            ->assertSessionHasErrors(['saring.gelombang']);
    }

    // --------------------------------------------------------- hanya terkirim

    public function test_draf_tidak_ikut_diekspor(): void
    {
        // Pendaftaran bawaan masih draf.
        $this->assertTrue($this->pendaftaran->masihDraf());

        $this->actingAs($this->panitia)
            ->post(route('panitia.ekspor.unduh'), $this->muatan())
            ->assertSessionHasErrors(['ekspor']);
    }

    public function test_penyaring_status_dan_nama_bekerja(): void
    {
        $terkirim = $this->terkirim();
        $lain = $this->buatPendaftaranTerkirim($this->wali);
        $this->aturStatus($lain, StatusHasil::Diterima);

        $respons = $this->actingAs($this->panitia)->post(route('panitia.ekspor.unduh'), $this->muatan([
            'saring' => ['status' => StatusHasil::Diterima->value],
        ]));
        $respons->assertOk();

        $isi = $this->isiUnduhan($respons);

        $this->assertStringContainsString((string) $lain->nomor_peserta, $isi);
        $this->assertStringNotContainsString((string) $terkirim->nomor_peserta, $isi);
    }

    // ------------------------------------------------------ daftar putih kolom

    public function test_kolom_di_luar_daftar_putih_diabaikan(): void
    {
        $pendaftaran = $this->terkirim();
        $pendaftaran->forceFill(['catatan_panitia' => 'CATATAN-RAHASIA-PANITIA'])->save();

        $respons = $this->actingAs($this->admin)->post(route('panitia.ekspor.unduh'), $this->muatan([
            'kolom' => [
                'nomor_peserta' => 1,
                'nama' => 1,
                'catatan_panitia' => 1,
                'catatan_wawancara' => 1,
                'kekurangan' => 1,
                'password' => 1,
            ],
        ]));
        $respons->assertOk();

        $isi = $this->isiUnduhan($respons);

        $this->assertStringNotContainsString('CATATAN-RAHASIA-PANITIA', $isi);
        $this->assertStringNotContainsString('catatan_panitia', $isi);
        $this->assertStringNotContainsString('catatan_wawancara', $isi);
        $this->assertStringNotContainsString('kekurangan', $isi);
    }

    public function test_catatan_wawancara_dan_kekurangan_daftar_ulang_tidak_pernah_diekspor(): void
    {
        $pendaftaran = $this->terkirim();

        \App\Models\DaftarUlang::create([
            'pendaftaran_id' => $pendaftaran->id,
            'hadir' => true,
            'kekurangan' => 'KEKURANGAN-RAHASIA',
            'catatan_wawancara' => 'WAWANCARA-RAHASIA',
            'selesai' => false,
        ]);

        $respons = $this->actingAs($this->admin)->post(route('panitia.ekspor.unduh'), $this->muatan([
            'kolom' => ['nomor_peserta' => 1, 'hadir' => 1, 'selesai' => 1],
        ]));
        $respons->assertOk();

        $isi = $this->isiUnduhan($respons);

        $this->assertStringNotContainsString('KEKURANGAN-RAHASIA', $isi);
        $this->assertStringNotContainsString('WAWANCARA-RAHASIA', $isi);
    }

    // ------------------------------------------------------------ sensitif

    public function test_kolom_sensitif_ditolak_untuk_panitia_meski_dikonfirmasi(): void
    {
        $this->terkirim();

        $respons = $this->actingAs($this->panitia)->post(route('panitia.ekspor.unduh'), $this->muatan([
            'kolom' => ['nomor_peserta' => 1, 'nik' => 1, 'no_kk' => 1],
            'konfirmasi_sensitif' => 1,
        ]));
        $respons->assertOk();

        $isi = $this->isiUnduhan($respons);

        $this->assertStringNotContainsString((string) $this->anak->nik, $isi);
        $this->assertStringNotContainsString((string) $this->anak->no_kk, $isi);
    }

    public function test_kolom_sensitif_admin_tanpa_konfirmasi_tidak_ikut(): void
    {
        $this->terkirim();

        $respons = $this->actingAs($this->admin)->post(route('panitia.ekspor.unduh'), $this->muatan([
            'kolom' => ['nomor_peserta' => 1, 'nik' => 1],
        ]));
        $respons->assertOk();

        $this->assertStringNotContainsString((string) $this->anak->nik, $this->isiUnduhan($respons));
    }

    public function test_kolom_sensitif_admin_dengan_konfirmasi_ikut_sebagai_teks(): void
    {
        $this->terkirim();

        $respons = $this->actingAs($this->admin)->post(route('panitia.ekspor.unduh'), $this->muatan([
            'kolom' => ['nomor_peserta' => 1, 'nik' => 1, 'no_kk' => 1],
            'konfirmasi_sensitif' => 1,
        ]));
        $respons->assertOk();

        $baris = $this->bacaCsv($this->isiUnduhan($respons));

        $this->assertSame(['Nomor peserta', 'NIK', 'Nomor KK'], $baris[0]);
        $this->assertSame((string) $this->anak->nik, (string) $baris[1][1]);
        $this->assertSame((string) $this->anak->no_kk, (string) $baris[1][2]);
    }

    // ----------------------------------------------------- formula injection

    public function test_formula_injection_dinetralkan_di_csv(): void
    {
        $pendaftaran = $this->terkirim();
        $pendaftaran->anak->forceFill([
            'nama_lengkap' => '=HYPERLINK("http://evil","klik")',
        ])->save();

        $respons = $this->actingAs($this->panitia)->post(route('panitia.ekspor.unduh'), $this->muatan());
        $respons->assertOk();

        $baris = $this->bacaCsv($this->isiUnduhan($respons));

        $this->assertSame("'=HYPERLINK(\"http://evil\",\"klik\")", (string) $baris[1][1]);
    }

    public function test_formula_injection_dinetralkan_di_xlsx(): void
    {
        $pendaftaran = $this->terkirim();
        $pendaftaran->anak->forceFill([
            'nama_lengkap' => "+cmd|' /C calc'!A0",
        ])->save();

        $respons = $this->actingAs($this->panitia)->post(route('panitia.ekspor.unduh'), $this->muatan([
            'format' => 'xlsx',
        ]));
        $respons->assertOk();

        $lembar = $this->bacaXlsx($this->isiUnduhan($respons));

        $this->assertArrayHasKey('Data', $lembar);
        $this->assertSame("'+cmd|' /C calc'!A0", (string) $lembar['Data'][1][1]);
    }

    public function test_semua_awalan_berbahaya_dinetralkan(): void
    {
        foreach (['=', '+', '-', '@', "\t", "\r"] as $awalan) {
            $nilai = $awalan.'serangan';

            $this->assertSame("'".$nilai, \App\Support\EksporSanitizer::sel($nilai));
        }

        $this->assertSame('aman', \App\Support\EksporSanitizer::sel('aman'));
        $this->assertSame(12, \App\Support\EksporSanitizer::sel(12));
    }

    // ------------------------------------------------------------ batas

    public function test_lebih_dari_batas_baris_ditolak_dengan_pesan(): void
    {
        config(['ekspor.batas_baris' => 2]);

        $this->terkirim();
        $this->buatPendaftaranTerkirim($this->wali);
        $this->buatPendaftaranTerkirim($this->wali);

        $this->actingAs($this->panitia)
            ->post(route('panitia.ekspor.unduh'), $this->muatan())
            ->assertSessionHasErrors(['ekspor']);

        $this->assertSame([], $this->fileTmp());
    }

    public function test_laju_ekspor_dibatasi_lima_per_menit(): void
    {
        $this->terkirim();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->panitia)
                ->post(route('panitia.ekspor.unduh'), $this->muatan())
                ->assertOk();
        }

        $this->actingAs($this->panitia)
            ->post(route('panitia.ekspor.unduh'), $this->muatan())
            ->assertStatus(429);
    }

    // --------------------------------------------------------------- log

    public function test_ekspor_tercatat_di_log_tanpa_isi_data(): void
    {
        $this->terkirim();

        $this->actingAs($this->panitia)
            ->post(route('panitia.ekspor.unduh'), $this->muatan([
                'kolom' => ['nomor_peserta' => 1, 'nama' => 1],
                'saring' => ['status' => StatusHasil::Baru->value],
            ]))
            ->assertOk();

        $log = LogAktivitas::query()->tanpaSekolah()->where('aksi', 'ekspor_pendaftar')->first();

        $this->assertNotNull($log);
        $this->assertSame($this->panitia->id, (int) $log->user_id);
        $this->assertSame($this->sekolah->id, (int) $log->sekolah_id);

        $data = json_decode((string) $log->data, true);

        $this->assertSame(1, $data['jumlah_baris']);
        $this->assertSame(['Nomor peserta', 'Nama lengkap'], $data['kolom']);
        $this->assertSame('csv', $data['format']);
        $this->assertArrayHasKey('saring', $data);

        // Isi data tidak boleh masuk log.
        $this->assertStringNotContainsString((string) $this->anak->nama_lengkap, (string) $log->data);
        $this->assertStringNotContainsString((string) $this->anak->nik, (string) $log->data);
    }

    public function test_header_unduhan_aman(): void
    {
        $this->terkirim();

        $respons = $this->actingAs($this->panitia)->post(route('panitia.ekspor.unduh'), $this->muatan());
        $respons->assertOk();

        $this->assertSame('nosniff', $respons->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('no-store', (string) $respons->headers->get('Cache-Control'));
        $this->assertStringStartsWith('attachment', (string) $respons->headers->get('Content-Disposition'));
    }

    public function test_file_sementara_dihapus_setelah_diunduh(): void
    {
        $this->terkirim();

        $respons = $this->actingAs($this->panitia)->post(route('panitia.ekspor.unduh'), $this->muatan());
        $respons->assertOk();

        $this->assertNotSame([], $this->fileTmp());

        // Kirim isi ke "pengguna"; file sementara dihapus setelahnya.
        $this->isiUnduhan($respons);

        $this->assertSame([], $this->fileTmp());
    }

    public function test_ringkasan_xlsx_tanpa_data_pribadi(): void
    {
        $this->terkirim();

        $respons = $this->actingAs($this->panitia)->post(route('panitia.ekspor.unduh'), $this->muatan([
            'format' => 'xlsx',
        ]));
        $respons->assertOk();

        $lembar = $this->bacaXlsx($this->isiUnduhan($respons));

        $this->assertArrayHasKey('Ringkasan', $lembar);

        $isiRingkasan = '';

        foreach ($lembar['Ringkasan'] as $baris) {
            $isiRingkasan .= implode(' ', array_map('strval', $baris));
        }

        $this->assertStringContainsString('Ringkasan ekspor', $isiRingkasan);
        $this->assertStringContainsString('Jumlah baris', $isiRingkasan);
        $this->assertStringNotContainsString((string) $this->anak->nik, $isiRingkasan);
        $this->assertStringNotContainsString((string) $this->anak->nama_lengkap, $isiRingkasan);
    }
}
