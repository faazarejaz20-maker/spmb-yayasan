<?php

namespace Tests\Feature\Keamanan;

use App\Enums\StatusBerkas;
use App\Models\Berkas;
use App\Models\JenisBerkas;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\WalisantriTestCase;

/**
 * Butir 9 dan 10: unggahan berbahaya ditolak dan unggah ulang mengganti berkas
 * lama dengan aman. Semua berkas disimpan di disk privat.
 */
class BerkasKeamananTest extends WalisantriTestCase
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
        ]);

        $this->actingAs($this->wali);
    }

    private function unggah(string $nama, string $isi): \Illuminate\Testing\TestResponse
    {
        return $this->post('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/berkas', [
            'jenis_berkas_id' => $this->jenis->id,
            'berkas' => UploadedFile::fake()->createWithContent($nama, $isi),
        ]);
    }

    private function jpeg(string $tambahan = ''): string
    {
        $gambar = imagecreatetruecolor(40, 30);
        imagefill($gambar, 0, 0, imagecolorallocate($gambar, 30, 120, 200));
        ob_start();
        imagejpeg($gambar);
        $isi = (string) ob_get_clean();
        imagedestroy($gambar);

        return $isi.$tambahan;
    }

    private function pdf(string $isi = 'Isi dokumen contoh.'): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n".$isi."\n%%EOF";
    }

    public function test_pdf_sah_diterima_dan_disimpan_di_disk_privat(): void
    {
        $this->unggah('akta.pdf', $this->pdf())
            ->assertRedirect(route('walisantri.pendaftaran.berkas.index', $this->pendaftaran->uuid));

        $berkas = Berkas::query()->where('pendaftaran_id', $this->pendaftaran->id)->firstOrFail();

        $this->assertStringEndsWith('.pdf', $berkas->path);
        $this->assertStringContainsString($this->pendaftaran->uuid, $berkas->path);
        Storage::disk('local')->assertExists($berkas->path);
        $this->assertFileDoesNotExist(public_path($berkas->path));
        $this->assertSame(StatusBerkas::Menunggu, $berkas->status);
    }

    public function test_php_yang_menyamar_sebagai_jpg_ditolak(): void
    {
        $this->unggah('foto.jpg', '<?php echo "halo"; ?>')
            ->assertSessionHasErrors('berkas');

        $this->assertSame(0, Berkas::query()->count());
    }

    public function test_ekstensi_ganda_ditolak(): void
    {
        $this->unggah('foto.php.jpg', $this->jpeg())
            ->assertSessionHasErrors('berkas');

        $this->assertSame(0, Berkas::query()->count());
    }

    public function test_pdf_palsu_ditolak(): void
    {
        $this->unggah('dokumen.pdf', 'Ini hanya teks biasa, bukan PDF.')
            ->assertSessionHasErrors('berkas');

        $this->assertSame(0, Berkas::query()->count());
    }

    public function test_png_yang_isinya_php_ditolak(): void
    {
        $this->unggah('gambar.png', '<?php system($_GET["c"]); ?>')
            ->assertSessionHasErrors('berkas');

        $this->assertSame(0, Berkas::query()->count());
    }

    public function test_berkas_terlalu_besar_ditolak(): void
    {
        $maks = (int) config('spmb.berkas.maks_kb');
        $besar = UploadedFile::fake()->create('besar.jpg', $maks + 100);

        $respons = $this->post('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/berkas', [
            'jenis_berkas_id' => $this->jenis->id,
            'berkas' => $besar,
        ]);

        $respons->assertSessionHasErrors('berkas');

        $pesan = (string) session('errors')->first('berkas');
        $this->assertStringContainsString('maksimal', $pesan);
        $this->assertSame(0, Berkas::query()->count());
    }

    public function test_berkas_kosong_ditolak(): void
    {
        $this->unggah('kosong.jpg', '')
            ->assertSessionHasErrors('berkas');
    }

    public function test_ekstensi_heic_ditolak_dengan_pesan_jelas(): void
    {
        $respons = $this->unggah('foto.heic', 'heic-palsu');

        $respons->assertSessionHasErrors('berkas');

        $pesan = (string) session('errors')->first('berkas');
        $this->assertStringContainsString('HEIC', $pesan);
    }

    public function test_jenis_berkas_milik_sekolah_lain_ditolak(): void
    {
        $sekolahLain = \App\Models\Sekolah::factory()->create(['kode' => 'SMP']);
        $jenisLain = JenisBerkas::factory()->create(['sekolah_id' => $sekolahLain->id]);

        $this->post('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/berkas', [
            'jenis_berkas_id' => $jenisLain->id,
            'berkas' => UploadedFile::fake()->createWithContent('akta.pdf', $this->pdf()),
        ])->assertNotFound();

        $this->assertSame(0, Berkas::query()->count());
    }

    public function test_unggah_ulang_mengganti_path_dan_mereset_status(): void
    {
        Storage::disk('local')->put('berkas/lama.pdf', '%PDF lama');

        $lama = Berkas::create([
            'pendaftaran_id' => $this->pendaftaran->id,
            'jenis_berkas_id' => $this->jenis->id,
            'path' => 'berkas/lama.pdf',
            'status' => StatusBerkas::PerluPerbaikan,
            'catatan' => 'Foto kurang jelas.',
        ]);

        $this->unggah('akta.pdf', $this->pdf())->assertRedirect();

        $baru = $lama->fresh();

        $this->assertSame($lama->id, $baru->id);
        $this->assertNotSame('berkas/lama.pdf', $baru->path);
        $this->assertSame(StatusBerkas::Menunggu, $baru->status);
        $this->assertNull($baru->catatan);
        Storage::disk('local')->assertMissing('berkas/lama.pdf');
        Storage::disk('local')->assertExists($baru->path);
        $this->assertSame(1, Berkas::query()->count());
    }

    public function test_tidak_bisa_mengunggah_setelah_dikirim(): void
    {
        $this->pendaftaran->forceFill(['dikirim_pada' => now()->subDay()])->save();

        $this->unggah('akta.pdf', $this->pdf())->assertForbidden();

        $this->assertSame(0, Berkas::query()->count());
    }

    public function test_berkas_tidak_bisa_unduh_tanpa_login(): void
    {
        $this->unggah('akta.pdf', $this->pdf())->assertRedirect();

        $berkas = Berkas::query()->firstOrFail();

        auth()->logout();

        $this->get('/akun/berkas/'.$berkas->uuid)->assertRedirect('/masuk');
    }
}
