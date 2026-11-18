<?php

namespace Tests\Feature\Keamanan;

use App\Models\Berkas;
use App\Models\JenisBerkas;
use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\UnduhanTestCase;
use ZipArchive;

/**
 * Butir 6 dan 10 Tahap 5: isi ZIP, nama entri terkontrol, berkas hilang,
 * batas ukuran, folder sementara bersih setelah unduhan/gagal, log, dan
 * matriks akses ZIP.
 */
class ZipBerkasTest extends UnduhanTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Cache::flush();
    }

    /**
     * Daftar nama entri di dalam isi ZIP.
     *
     * @return array<int, string>
     */
    private function entriZip(string $isi): array
    {
        $path = tempnam(sys_get_temp_dir(), 'uji-zip-');
        file_put_contents($path, $isi);

        $zip = new ZipArchive;
        $nama = [];

        try {
            $this->assertTrue($zip->open($path) === true, 'Isi respons bukan ZIP yang sah.');

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nama[] = (string) $zip->getNameIndex($i);
            }
        } finally {
            @$zip->close();
            @unlink($path);
        }

        return $nama;
    }

    public function test_tamu_diarahkan_ke_masuk_dan_peran_lain_ditolak(): void
    {
        $this->terkirim();
        $this->berkasNyata($this->pendaftaran);

        $this->get(route('panitia.pendaftar.zip', $this->pendaftaran->uuid))
            ->assertRedirect(route('masuk'));

        $wali = User::factory()->walisantri()->create();
        $super = User::factory()->superAdmin()->create();
        $yayasan = User::factory()->yayasan()->create();

        foreach ([$wali, $super, $yayasan] as $pengguna) {
            $this->actingAs($pengguna)
                ->get(route('panitia.pendaftar.zip', $this->pendaftaran->uuid))
                ->assertForbidden();
        }
    }

    public function test_panitia_dan_admin_sekolah_sendiri_bisa_mengunduh_zip(): void
    {
        $this->terkirim();
        $this->berkasNyata($this->pendaftaran);

        foreach ([$this->panitia, $this->admin] as $pengguna) {
            $respons = $this->actingAs($pengguna)
                ->get(route('panitia.pendaftar.zip', $this->pendaftaran->uuid));

            $respons->assertOk()->assertDownload();
            $this->assertSame('application/zip', $respons->headers->get('Content-Type'));
            $this->assertSame('nosniff', $respons->headers->get('X-Content-Type-Options'));
            $this->assertStringContainsString('no-store', (string) $respons->headers->get('Cache-Control'));
        }
    }

    public function test_isi_zip_hanya_berkas_pendaftar_itu(): void
    {
        $pendaftaran = $this->terkirim();
        $jenis = JenisBerkas::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nama' => 'Kartu Keluarga',
        ]);

        $this->berkasNyata($pendaftaran, 'pdf', $jenis);
        $this->berkasNyata($pendaftaran, 'jpg', $jenis);

        // Pendaftar lain di sekolah yang sama, tidak boleh ikut.
        $lain = $this->buatPendaftaranTerkirim($this->wali);
        $this->berkasNyata($lain, 'pdf');

        $respons = $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.zip', $pendaftaran->uuid));
        $respons->assertOk();

        $entri = $this->entriZip($this->isiUnduhan($respons));

        $this->assertCount(2, $entri);
        $this->assertSame([
            $pendaftaran->nomor_peserta.'_kartu-keluarga_1.pdf',
            $pendaftaran->nomor_peserta.'_kartu-keluarga_2.jpg',
        ], $entri);
    }

    public function test_nama_entri_dari_data_terkontrol_bukan_nama_unggahan(): void
    {
        $pendaftaran = $this->terkirim();

        $jenis = JenisBerkas::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nama' => 'Akta Lahir',
        ]);

        // Nama berkas di disk sengaja mengandung `..` (mis. sisa unggahan
        // lama yang tidak tervalidasi). Nama entri ZIP tetap dibangun dari
        // data terkontrol, bukan dari nama berkas.
        $path = 'berkas/'.$pendaftaran->uuid.'/..x.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 contoh');

        Berkas::create([
            'pendaftaran_id' => $pendaftaran->id,
            'jenis_berkas_id' => $jenis->id,
            'path' => $path,
        ]);

        $respons = $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.zip', $pendaftaran->uuid));
        $respons->assertOk();

        $entri = $this->entriZip($this->isiUnduhan($respons));

        $this->assertSame([$pendaftaran->nomor_peserta.'_akta-lahir_1.pdf'], $entri);

        foreach ($entri as $nama) {
            $this->assertStringNotContainsString('..', $nama);
            $this->assertStringNotContainsString('/', $nama);
            $this->assertStringNotContainsString('\\', $nama);
        }
    }

    public function test_ekstensi_di_luar_daftar_putih_dilewati(): void
    {
        $pendaftaran = $this->terkirim();
        $jenis = JenisBerkas::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nama' => 'Berkas Aneh',
        ]);

        $this->berkasNyata($pendaftaran, 'php', $jenis);

        $respons = $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.zip', $pendaftaran->uuid));

        // Semua berkas tidak dapat dipakai → pesan sopan, bukan 500.
        $respons->assertRedirect()->assertSessionHasErrors(['zip']);
        $this->assertSame([], $this->fileTmp());
    }

    public function test_berkas_hilang_dari_disk_dilewati_tanpa_500(): void
    {
        $pendaftaran = $this->terkirim();
        $jenis = JenisBerkas::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nama' => 'Kartu Keluarga',
        ]);

        $ada = $this->berkasNyata($pendaftaran, 'pdf', $jenis);

        // Baris berkas tanpa isi di disk.
        Berkas::create([
            'pendaftaran_id' => $pendaftaran->id,
            'jenis_berkas_id' => $jenis->id,
            'path' => 'berkas/hilang/tidak-ada.pdf',
        ]);

        $respons = $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.zip', $pendaftaran->uuid));
        $respons->assertOk();

        $entri = $this->entriZip($this->isiUnduhan($respons));

        $this->assertCount(1, $entri);
        $this->assertSame($pendaftaran->nomor_peserta.'_kartu-keluarga_1.pdf', $entri[0]);

        $log = LogAktivitas::query()->tanpaSekolah()
            ->where('aksi', 'unduh_zip_berkas_hilang')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame([$this->berkasHilangId($pendaftaran, $jenis)], json_decode((string) $log->data, true)['berkas_id']);
    }

    private function berkasHilangId($pendaftaran, JenisBerkas $jenis): int
    {
        return (int) Berkas::query()
            ->where('pendaftaran_id', $pendaftaran->id)
            ->where('jenis_berkas_id', $jenis->id)
            ->where('path', 'berkas/hilang/tidak-ada.pdf')
            ->value('id');
    }

    public function test_semua_berkas_hilang_memberi_pesan_bukan_500(): void
    {
        $pendaftaran = $this->terkirim();
        $jenis = JenisBerkas::factory()->create(['sekolah_id' => $this->sekolah->id]);

        Berkas::create([
            'pendaftaran_id' => $pendaftaran->id,
            'jenis_berkas_id' => $jenis->id,
            'path' => 'berkas/hilang/semua.pdf',
        ]);

        $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.zip', $pendaftaran->uuid))
            ->assertRedirect()
            ->assertSessionHasErrors(['zip']);

        $this->assertSame([], $this->fileTmp());
    }

    public function test_pendaftar_tanpa_berkas_memberi_pesan(): void
    {
        $this->terkirim();

        $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.zip', $this->pendaftaran->uuid))
            ->assertRedirect()
            ->assertSessionHasErrors(['zip']);
    }

    public function test_total_lebih_dari_batas_ukuran_ditolak(): void
    {
        $pendaftaran = $this->terkirim();
        $this->berkasNyata($pendaftaran, 'pdf');

        config(['ekspor.zip_maks_mb' => 0]);

        $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.zip', $pendaftaran->uuid))
            ->assertRedirect()
            ->assertSessionHasErrors(['zip']);

        $this->assertSame([], $this->fileTmp());
    }

    public function test_sekolah_lain_404(): void
    {
        $lain = $this->pendaftaranSekolahB();
        $this->berkasNyata($lain, 'pdf');

        $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.zip', $lain->uuid))
            ->assertNotFound();

        $this->actingAs($this->panitiaB)
            ->get(route('panitia.pendaftar.zip', $this->terkirim()->uuid))
            ->assertNotFound();
    }

    public function test_draf_404(): void
    {
        // Pendaftaran bawaan masih draf.
        $this->berkasNyata($this->pendaftaran, 'pdf');

        $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.zip', $this->pendaftaran->uuid))
            ->assertNotFound();
    }

    public function test_folder_sementara_bersih_setelah_unduhan(): void
    {
        $pendaftaran = $this->terkirim();
        $this->berkasNyata($pendaftaran, 'pdf');

        $respons = $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.zip', $pendaftaran->uuid));
        $respons->assertOk();

        $this->assertNotSame([], $this->fileTmp());

        $this->isiUnduhan($respons);

        $this->assertSame([], $this->fileTmp());
    }

    public function test_unduhan_tercatat_di_log(): void
    {
        $pendaftaran = $this->terkirim();
        $this->berkasNyata($pendaftaran, 'pdf');

        $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.zip', $pendaftaran->uuid))
            ->assertOk();

        $log = LogAktivitas::query()->tanpaSekolah()->where('aksi', 'unduh_zip')->first();

        $this->assertNotNull($log);
        $this->assertSame($this->panitia->id, (int) $log->user_id);
        $this->assertSame($this->sekolah->id, (int) $log->sekolah_id);
        $this->assertSame(1, json_decode((string) $log->data, true)['jumlah_berkas']);
    }

    public function test_laju_zip_dibatasi_lima_per_menit(): void
    {
        $pendaftaran = $this->terkirim();
        $this->berkasNyata($pendaftaran, 'pdf');

        for ($i = 0; $i < 5; $i++) {
            $respons = $this->actingAs($this->panitia)
                ->get(route('panitia.pendaftar.zip', $pendaftaran->uuid));
            $respons->assertOk();
            $this->isiUnduhan($respons);
        }

        $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.zip', $pendaftaran->uuid))
            ->assertStatus(429);
    }
}
