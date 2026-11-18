<?php

namespace Tests\Feature\Keamanan;

use App\Models\KontenSekolah;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\PanitiaTestCase;

/**
 * Butir 10: Kelola Konten. HTML dan <script> tidak dieksekusi; tautan
 * javascript: ditolak; unggahan berbahaya ditolak; gambar di-encode ulang di
 * disk publik khusus konten; berkas pendaftar tidak pernah masuk disk publik;
 * admin sekolah lain tidak bisa mengubah.
 */
class KontenKeamananTest extends PanitiaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_tag_html_dibuang_dan_tampil_ter_escape(): void
    {
        $this->actingAs($this->admin)
            ->put('/admin/konten/teks', [
                'bagian' => 'profil',
                'isi' => "Teks profil <script>alert(1)</script> dengan <b>cetak tebal</b>",
            ])
            ->assertSessionHasNoErrors();

        $baris = KontenSekolah::query()
            ->where('sekolah_id', $this->sekolah->id)
            ->where('bagian', 'profil')
            ->firstOrFail();

        // Tag dibuang sebelum disimpan; isi sisa tetap teks biasa.
        $this->assertStringNotContainsString('<script>', $baris->isi);
        $this->assertStringNotContainsString('<b>', $baris->isi);
        $this->assertStringContainsString('alert(1)', $baris->isi);

        $this->get('/admin/konten')
            ->assertOk()
            ->assertDontSee('<script>', false)
            ->assertSee(e($baris->isi), false);
    }

    public function test_tautan_selain_https_ditolak(): void
    {
        $super = User::factory()->superAdmin()->create();

        foreach (['javascript:alert(1)', 'data:text/html;base64,AAA', 'http://aman.test/profil'] as $tautan) {
            $this->actingAs($super)
                ->put('/super/konten/teks', [
                    'bagian' => 'tautan_sosial',
                    'isi' => $tautan,
                ])
                ->assertSessionHasErrors(['konten']);
        }

        $this->assertDatabaseMissing('konten_sekolah', ['sekolah_id' => null, 'bagian' => 'tautan_sosial']);

        $this->actingAs($super)
            ->put('/super/konten/teks', [
                'bagian' => 'tautan_sosial',
                'isi' => 'https://instagram.com/alishlahinsanmulia',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('konten_sekolah', ['sekolah_id' => null, 'bagian' => 'tautan_sosial']);
    }

    public function test_berkas_php_bernama_jpg_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/konten', [
                'bagian' => 'ekskul',
                'judul' => 'Skrip',
                'gambar' => UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo "pwn"; ?>'),
            ])
            ->assertSessionHasErrors(['konten']);

        $this->assertSame(0, KontenSekolah::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_svg_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/konten', [
                'bagian' => 'ekskul',
                'judul' => 'Gambar SVG',
                'gambar' => UploadedFile::fake()->createWithContent(
                    'gambar.svg',
                    '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
                ),
            ])
            ->assertSessionHasErrors(['konten']);

        $this->assertSame(0, KontenSekolah::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_gambar_lebih_dari_2_mb_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/konten', [
                'bagian' => 'ekskul',
                'judul' => 'Besar',
                'gambar' => UploadedFile::fake()->createWithContent(
                    'besar.jpg',
                    str_repeat('a', 3 * 1024 * 1024),
                ),
            ])
            ->assertSessionHasErrors();

        $this->assertSame(0, KontenSekolah::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_ekstensi_jpg_dengan_isi_pdf_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/konten', [
                'bagian' => 'ekskul',
                'judul' => 'Palsu',
                'gambar' => UploadedFile::fake()->createWithContent(
                    'palsu.jpg',
                    "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< >>\n%%EOF",
                ),
            ])
            ->assertSessionHasErrors(['konten']);

        $this->assertSame(0, KontenSekolah::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_gambar_sah_disimpan_di_disk_publik_konten_dengan_nama_acak(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/konten', [
                'bagian' => 'ekskul',
                'judul' => 'Robotik',
                'isi' => 'Klub robotik',
                'gambar' => UploadedFile::fake()->image('robotik.jpg', 400, 300),
            ])
            ->assertSessionHasNoErrors();

        $baris = KontenSekolah::query()
            ->where('sekolah_id', $this->sekolah->id)
            ->where('bagian', 'ekskul')
            ->firstOrFail();

        $path = (string) $baris->gambar_path;

        $this->assertStringStartsWith('konten/', $path);
        $this->assertStringEndsWith('.jpg', $path);
        $this->assertStringNotContainsString('robotik.jpg', $path);
        $this->assertTrue(Storage::disk('public')->exists($path));

        // Gambar tersimpan masih bisa dibuka sebagai gambar (hasil encode ulang).
        $isi = Storage::disk('public')->get($path);
        $this->assertTrue(@imagecreatefromstring($isi) !== false);
    }

    public function test_hapus_baris_konten_menghapus_gambar_dari_disk(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/konten', [
                'bagian' => 'galeri',
                'judul' => 'Foto Kegiatan',
                'gambar' => UploadedFile::fake()->image('kegiatan.png', 200, 200),
            ])
            ->assertSessionHasNoErrors();

        $baris = KontenSekolah::query()
            ->where('sekolah_id', $this->sekolah->id)
            ->where('bagian', 'galeri')
            ->firstOrFail();

        $path = (string) $baris->gambar_path;
        $this->assertTrue(Storage::disk('public')->exists($path));

        $this->delete('/admin/konten/'.$baris->id)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('konten_sekolah', ['id' => $baris->id]);
        $this->assertFalse(Storage::disk('public')->exists($path));
    }

    public function test_berkas_pendaftar_tidak_pernah_masuk_disk_publik(): void
    {
        // Berkas pendaftar hidup di disk privat local.
        Storage::disk('local')->put('berkas/uji/contoh.pdf', 'isi berkas');

        // Admin mengunggah gambar konten (disk publik).
        $this->actingAs($this->admin)
            ->post('/admin/konten', [
                'bagian' => 'prestasi',
                'judul' => 'Juara 1',
                'gambar' => UploadedFile::fake()->image('prestasi.jpg', 300, 300),
            ])
            ->assertSessionHasNoErrors();

        $publik = Storage::disk('public')->allFiles();

        $this->assertNotEmpty($publik);

        foreach ($publik as $path) {
            $this->assertStringStartsWith('konten/', $path, "Disk publik berisi {$path}.");
        }

        // Berkas pendaftar berada di disk privat, bukan publik.
        $privat = Storage::disk('local')->allFiles();

        $this->assertNotEmpty($privat);

        foreach ($privat as $path) {
            $this->assertStringStartsWith('berkas/', $path, "Disk privat berisi {$path}.");
        }
    }

    public function test_admin_sekolah_lain_tidak_bisa_mengubah_konten(): void
    {
        $konten = KontenSekolah::create([
            'sekolah_id' => $this->sekolahB->id,
            'bagian' => 'slogan',
            'isi' => 'Slogan SMP',
            'urutan' => 0,
        ]);

        $this->actingAs($this->admin);

        $this->put('/admin/konten/'.$konten->id, ['bagian' => 'slogan', 'isi' => 'Direbut'])
            ->assertNotFound();

        $this->delete('/admin/konten/'.$konten->id)->assertNotFound();

        // Baris sekolah B tidak pernah tersentuh admin A (query pakai DB
        // langsung supaya tidak tersaring global scope sekolah).
        $this->assertSame(
            'Slogan SMP',
            DB::table('konten_sekolah')
                ->where('sekolah_id', $this->sekolahB->id)
                ->where('bagian', 'slogan')
                ->value('isi'),
        );
    }

    public function test_admin_tidak_bisa_menyentuh_konten_yayasan(): void
    {
        $konten = KontenSekolah::create([
            'sekolah_id' => null,
            'bagian' => 'profil',
            'isi' => 'Profil yayasan',
            'urutan' => 0,
        ]);

        $this->actingAs($this->admin);

        $this->put('/admin/konten/'.$konten->id, ['bagian' => 'profil', 'isi' => 'Direbut'])->assertNotFound();
        $this->delete('/admin/konten/'.$konten->id)->assertNotFound();
        $this->put('/admin/konten/teks', ['bagian' => 'tautan_sosial', 'isi' => 'https://contoh.test'])->assertNotFound();

        $this->assertSame('Profil yayasan', $konten->fresh()->isi);
    }

    public function test_super_admin_hanya_bisa_mengelola_konten_yayasan(): void
    {
        $super = User::factory()->superAdmin()->create();

        $kontenSekolah = KontenSekolah::create([
            'sekolah_id' => Sekolah::factory()->create()->id,
            'bagian' => 'slogan',
            'isi' => 'Slogan sekolah',
            'urutan' => 0,
        ]);

        $this->actingAs($super);

        $this->put('/super/konten/'.$kontenSekolah->id, ['bagian' => 'slogan', 'isi' => 'Direbut'])->assertNotFound();
        $this->delete('/super/konten/'.$kontenSekolah->id)->assertNotFound();
        $this->put('/super/konten/teks', ['bagian' => 'slogan', 'isi' => 'Direbut'])->assertNotFound();

        $this->assertSame('Slogan sekolah', $kontenSekolah->fresh()->isi);

        $this->put('/super/konten/teks', ['bagian' => 'profil', 'isi' => 'Profil yayasan sah'])
            ->assertSessionHasNoErrors();
    }
}
