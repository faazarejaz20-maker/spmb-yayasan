<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Foto sampul kartu sekolah di beranda publik: admin sekolah dan super admin
 * dapat mengunggah, mengganti, dan menghapusnya sendiri lewat panel.
 */
class FotoSampulSekolahTest extends PanitiaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function unggahSampul(string $nama = 'sampul-baru.jpg'): void
    {
        $this->actingAs($this->admin)
            ->put('/admin/pengaturan/foto-sampul', [
                'foto_sampul' => UploadedFile::fake()->image($nama, 1600, 1000),
            ])
            ->assertRedirect(route('admin.pengaturan'))
            ->assertSessionHasNoErrors();
    }

    public function test_admin_mengunggah_foto_sampul_dan_tampil_di_beranda(): void
    {
        // Panaskan cache beranda lebih dulu. Unggahan harus membuang cache itu,
        // bukan menunggu 10 menit sampai kedaluwarsa.
        $awal = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('/storage/sampul/', $awal);

        $this->unggahSampul();

        $sekolah = $this->sekolah->refresh();
        $path = (string) $sekolah->foto_sampul_path;

        $this->assertStringStartsWith('sampul/'.$this->sekolah->id.'/', $path);
        $this->assertStringEndsWith('.jpg', $path);
        $this->assertStringNotContainsString('sampul-baru.jpg', $path);
        $this->assertTrue(Storage::disk('public')->exists($path));

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('src="/storage/'.$path.'"', $html);
    }

    public function test_mengganti_foto_sampul_menghapus_berkas_lama(): void
    {
        $this->unggahSampul('pertama.jpg');
        $lama = (string) $this->sekolah->refresh()->foto_sampul_path;

        $this->unggahSampul('kedua.jpg');
        $baru = (string) $this->sekolah->refresh()->foto_sampul_path;

        $this->assertNotSame($lama, $baru);
        $this->assertFalse(Storage::disk('public')->exists($lama));
        $this->assertTrue(Storage::disk('public')->exists($baru));

        $html = $this->get('/')->getContent();
        $this->assertStringContainsString($baru, $html);
        $this->assertStringNotContainsString($lama, $html);
    }

    public function test_menghapus_foto_sampul_kembali_ke_foto_bawaan(): void
    {
        $this->unggahSampul();
        $path = (string) $this->sekolah->refresh()->foto_sampul_path;

        $this->actingAs($this->admin)
            ->delete('/admin/pengaturan/foto-sampul')
            ->assertRedirect(route('admin.pengaturan'))
            ->assertSessionHasNoErrors();

        $this->assertNull($this->sekolah->refresh()->foto_sampul_path);
        $this->assertFalse(Storage::disk('public')->exists($path));

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('/storage/sampul/', $html);
        $this->assertStringContainsString('Foto SDIT Insan Mulia Wonosobo', $html);
    }

    public function test_panitia_dan_peran_lain_tidak_boleh_mengubah_foto_sampul(): void
    {
        foreach ([$this->panitia, User::factory()->yayasan()->create()] as $pengguna) {
            $this->actingAs($pengguna)
                ->put('/admin/pengaturan/foto-sampul', [
                    'foto_sampul' => UploadedFile::fake()->image('salah.jpg', 800, 500),
                ])
                ->assertForbidden();

            $this->actingAs($pengguna)
                ->delete('/admin/pengaturan/foto-sampul')
                ->assertForbidden();
        }

        $this->assertNull($this->sekolah->refresh()->foto_sampul_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_super_admin_dapat_mengunggah_dan_menghapus_foto_sampul(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)
            ->put('/super/sekolah/'.$this->sekolahB->id.'/foto-sampul', [
                'foto_sampul' => UploadedFile::fake()->image('smp.jpg', 1600, 1000),
            ])
            ->assertRedirect(route('super.sekolah.edit', $this->sekolahB))
            ->assertSessionHasNoErrors();

        $path = (string) $this->sekolahB->refresh()->foto_sampul_path;

        $this->assertStringStartsWith('sampul/'.$this->sekolahB->id.'/', $path);
        $this->assertTrue(Storage::disk('public')->exists($path));

        $this->actingAs($super)
            ->delete('/super/sekolah/'.$this->sekolahB->id.'/foto-sampul')
            ->assertRedirect(route('super.sekolah.edit', $this->sekolahB));

        $this->assertNull($this->sekolahB->refresh()->foto_sampul_path);
        $this->assertFalse(Storage::disk('public')->exists($path));
    }

    public function test_unggahan_satu_sekolah_tidak_mengubah_sekolah_lain(): void
    {
        $this->unggahSampul();

        $this->assertNull($this->sekolahB->refresh()->foto_sampul_path);
        $this->assertCount(1, Storage::disk('public')->allFiles('sampul'));
        $this->assertStringStartsWith(
            'sampul/'.$this->sekolah->id.'/',
            Storage::disk('public')->allFiles('sampul')[0],
        );
    }

    public function test_berkas_terlalu_besar_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->put('/admin/pengaturan/foto-sampul', [
                'foto_sampul' => UploadedFile::fake()->image('besar.jpg')->size(11000),
            ])
            ->assertSessionHasErrors('foto_sampul');

        $this->assertNull($this->sekolah->refresh()->foto_sampul_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_berkas_besar_sampai_sepuluh_mb_diterima_dan_dikecilkan(): void
    {
        $this->actingAs($this->admin)
            ->put('/admin/pengaturan/foto-sampul', [
                'foto_sampul' => UploadedFile::fake()->image('besar.jpg', 3000, 2000)->size(6000),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.pengaturan'));

        $path = $this->sekolah->refresh()->foto_sampul_path;

        $this->assertNotNull($path);
        $this->assertTrue(Storage::disk('public')->exists($path));

        [$lebar, $tinggi] = getimagesizefromstring(Storage::disk('public')->get($path));

        // Hasil simpan tetap dikecilkan dan ditulis ulang, jauh di bawah
        // ukuran unggahan aslinya.
        $this->assertLessThanOrEqual(1600, max($lebar, $tinggi));
        $this->assertLessThan(6000 * 1024, Storage::disk('public')->size($path));
    }

    public function test_berkas_bukan_gambar_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->put('/admin/pengaturan/foto-sampul', [
                'foto_sampul' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('foto_sampul');

        // Berkas berisi data bukan gambar yang dinamai .jpg juga ditolak,
        // karena isi berkas diperiksa ulang di layanan.
        $this->actingAs($this->admin)
            ->put('/admin/pengaturan/foto-sampul', [
                'foto_sampul' => UploadedFile::fake()->createWithContent(
                    'palsu.jpg',
                    "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n",
                ),
            ])
            ->assertSessionHasErrors('foto_sampul');

        $this->assertNull($this->sekolah->refresh()->foto_sampul_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_halaman_pengaturan_menampilkan_kartu_foto_sampul(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/pengaturan')
            ->assertOk()
            ->assertSee('Foto sampul beranda')
            ->assertSee('Unggah foto baru');

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/super/sekolah/'.$this->sekolah->id.'/edit')
            ->assertOk()
            ->assertSee('Foto sampul beranda')
            ->assertSee('Unggah foto baru');
    }
}
