<?php

namespace Tests\Feature;

use App\Enums\StatusBerkas;
use App\Models\Berkas;
use App\Models\JenisBerkas;
use App\Models\Pendaftaran;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/**
 * Butir 8: pemeriksaan berkas oleh panitia dan unggah atas nama walisantri
 * memakai aturan validasi yang sama dengan tahap 2.
 */
class PanitiaBerkasTest extends PanitiaTestCase
{
    private JenisBerkas $jenis;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->jenis = JenisBerkas::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nama' => 'Akta kelahiran',
            'wajib' => true,
            'aktif' => true,
        ]);
    }

    private function pdf(string $isi = 'Isi dokumen contoh.'): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n".$isi."\n%%EOF";
    }

    private function unggah(Pendaftaran $pendaftaran, string $nama, string $isi): TestResponse
    {
        return $this->actingAs($this->panitia)->post(
            route('panitia.pendaftar.berkas.simpan', $pendaftaran->uuid),
            [
                'jenis_berkas_id' => $this->jenis->id,
                'berkas' => UploadedFile::fake()->createWithContent($nama, $isi),
            ],
        );
    }

    public function test_berkas_pendaftar_lebih_dari_dua_mb_tetap_ditolak(): void
    {
        $pendaftaran = $this->terkirim();

        // Foto tampilan sekolah boleh sampai 10 MB, tetapi berkas pendaftar
        // tetap 2 MB karena berisi data pribadi.
        $this->unggah(
            $pendaftaran,
            'besar.pdf',
            $this->pdf(str_repeat('a', 3 * 1024 * 1024)),
        )->assertSessionHasErrors('berkas');

        $this->assertSame(0, Berkas::query()->count());
    }

    public function test_periksa_sesuai_menyimpan_pemeriksa_dan_waktu(): void
    {
        $pendaftaran = $this->terkirim();
        $berkas = $this->lampirkanBerkas($pendaftaran, $this->jenis);

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.berkas.periksa', [
                'pendaftaran' => $pendaftaran->uuid,
                'berkas' => $berkas->uuid,
            ]), ['status' => 'sesuai'])
            ->assertRedirect(route('panitia.pendaftar.detail', [
                'pendaftaran' => $pendaftaran->uuid,
                'tab' => 'berkas',
            ]))
            ->assertSessionHasNoErrors();

        $segar = $berkas->refresh();

        $this->assertSame(StatusBerkas::Sesuai, $segar->status);
        $this->assertSame($this->panitia->id, (int) $segar->diperiksa_oleh);
        $this->assertNotNull($segar->diperiksa_pada);
        $this->assertNull($segar->catatan);

        $this->assertDatabaseHas('log_aktivitas', [
            'aksi' => 'periksa_berkas',
            'objek' => 'Berkas',
            'objek_id' => $berkas->id,
            'user_id' => $this->panitia->id,
            'sekolah_id' => $this->sekolah->id,
        ]);
    }

    public function test_perlu_perbaikan_wajib_catatan(): void
    {
        $pendaftaran = $this->terkirim();
        $berkas = $this->lampirkanBerkas($pendaftaran, $this->jenis);

        $url = route('panitia.pendaftar.berkas.periksa', [
            'pendaftaran' => $pendaftaran->uuid,
            'berkas' => $berkas->uuid,
        ]);

        $this->actingAs($this->panitia)
            ->post($url, ['status' => 'perlu_perbaikan'])
            ->assertSessionHasErrors('berkas');

        $this->assertSame(StatusBerkas::Menunggu, $berkas->refresh()->status);

        $this->actingAs($this->panitia)
            ->post($url, ['status' => 'perlu_perbaikan', 'catatan' => 'Foto buram, unggah ulang.'])
            ->assertSessionHasNoErrors();

        $segar = $berkas->refresh();
        $this->assertSame(StatusBerkas::PerluPerbaikan, $segar->status);
        $this->assertSame('Foto buram, unggah ulang.', $segar->catatan);
    }

    public function test_status_menunggu_ditolak_validasi(): void
    {
        $pendaftaran = $this->terkirim();
        $berkas = $this->lampirkanBerkas($pendaftaran, $this->jenis);

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.berkas.periksa', [
                'pendaftaran' => $pendaftaran->uuid,
                'berkas' => $berkas->uuid,
            ]), ['status' => 'menunggu'])
            ->assertSessionHasErrors('status');

        $this->assertSame(StatusBerkas::Menunggu, $berkas->refresh()->status);
    }

    public function test_berkas_dari_pendaftaran_lain_tidak_bisa_diperiksa(): void
    {
        $sendiri = $this->terkirim();
        $lain = $this->buatPendaftaranTerkirim(User::factory()->walisantri()->create());

        $berkasLain = $this->lampirkanBerkas($lain, $this->jenis);

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.berkas.periksa', [
                'pendaftaran' => $sendiri->uuid,
                'berkas' => $berkasLain->uuid,
            ]), ['status' => 'sesuai'])
            ->assertNotFound();

        $this->assertSame(StatusBerkas::Menunggu, $berkasLain->refresh()->status);
    }

    public function test_unggah_atas_nama_walisantri_menolak_berkas_berbahaya(): void
    {
        $pendaftaran = $this->terkirim();

        $this->unggah($pendaftaran, 'foto.jpg', '<?php echo "halo"; ?>')
            ->assertSessionHasErrors('berkas');

        $this->unggah($pendaftaran, 'foto.php.jpg', $this->pdf())
            ->assertSessionHasErrors('berkas');

        $this->assertSame(0, Berkas::query()->where('pendaftaran_id', $pendaftaran->id)->count());
    }

    public function test_unggah_atas_nama_walisantri_berhasil(): void
    {
        $pendaftaran = $this->terkirim();

        $this->unggah($pendaftaran, 'akta.pdf', $this->pdf())
            ->assertRedirect(route('panitia.pendaftar.detail', [
                'pendaftaran' => $pendaftaran->uuid,
                'tab' => 'berkas',
            ]))
            ->assertSessionHasNoErrors();

        $berkas = Berkas::query()->where('pendaftaran_id', $pendaftaran->id)->firstOrFail();

        $this->assertSame(StatusBerkas::Menunggu, $berkas->status);
        Storage::disk('local')->assertExists($berkas->path);
        $this->assertStringContainsString($pendaftaran->uuid, $berkas->path);
    }

    public function test_berkas_draf_tidak_bisa_diunduh_lewat_rute_panitia(): void
    {
        $berkas = $this->lampirkanBerkas($this->pendaftaran, $this->jenis);

        $this->actingAs($this->panitia)
            ->get(route('panitia.berkas.tampil', $berkas->uuid))
            ->assertNotFound();
    }

    public function test_berkas_sekolah_sendiri_bisa_diunduh(): void
    {
        $pendaftaran = $this->terkirim();
        $berkas = $this->lampirkanBerkas($pendaftaran, $this->jenis);

        Storage::disk('local')->put($berkas->path, $this->pdf());

        $this->actingAs($this->panitia)
            ->get(route('panitia.berkas.tampil', $berkas->uuid))
            ->assertOk();
    }
}
