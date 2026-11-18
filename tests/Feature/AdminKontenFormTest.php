<?php

namespace Tests\Feature;

use App\Models\KontenSekolah;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/**
 * Menguji agar galat unggah gambar tidak menghapus atau menyalin isian
 * formulir konten ke formulir lain di halaman yang sama.
 */
class AdminKontenFormTest extends PanitiaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    private function baris(string $isi, int $urutan): KontenSekolah
    {
        return KontenSekolah::create([
            'sekolah_id' => $this->sekolah->id,
            'bagian' => 'ekskul',
            'judul' => 'Baris '.$urutan,
            'isi' => $isi,
            'urutan' => $urutan,
        ]);
    }

    private function gambarTerlaluBesar(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'besar.jpg',
            str_repeat('a', 11 * 1024 * 1024),
        );
    }

    /**
     * Bawa cookie sesi dari respons sebelumnya, seperti browser sungguhan.
     * Tanpa ini, permintaan GET memakai sesi baru dan galat validasi hilang.
     */
    private function bukaDenganSesi(TestResponse $sebelumnya, string $jalur): string
    {
        $cookie = collect($sebelumnya->headers->getCookies())
            ->first(fn ($item) => $item->getName() === config('session.cookie'));

        if ($cookie) {
            $this->withUnencryptedCookie(config('session.cookie'), $cookie->getValue());
        }

        return $this->get($jalur)->assertOk()->getContent();
    }

    public function test_kegagalan_unggah_hanya_mengisi_form_baris_yang_gagal(): void
    {
        $gagal = $this->baris('Isi lama satu', 1);
        $this->baris('Isi lama dua', 2);

        $respons = $this->actingAs($this->admin)
            ->put('/admin/konten/'.$gagal->id, [
                'bagian' => 'ekskul',
                'judul' => 'Judul baru satu',
                'urutan' => 1,
                'isi' => 'Isi baru satu',
                '_form' => 'baris-'.$gagal->id,
                'gambar' => $this->gambarTerlaluBesar(),
            ])
            ->assertSessionHasErrors('gambar');

        $html = $this->bukaDenganSesi($respons, '/admin/konten');

        // Isian yang gagal kembali hanya di formulir barisnya sendiri.
        $this->assertSame(1, substr_count($html, 'Isi baru satu'));
        $this->assertSame(1, substr_count($html, 'Judul baru satu'));

        // Baris lain tetap memakai isi dari database.
        $this->assertSame(1, substr_count($html, 'Isi lama dua'));

        // Pesan galat ukuran terlihat di halaman.
        $this->assertStringContainsString('Ukuran gambar maksimal 10 MB.', $html);
    }

    public function test_kegagalan_tambah_baris_mengisi_form_tambah_saja(): void
    {
        $this->baris('Isi lama satu', 1);

        $respons = $this->actingAs($this->admin)
            ->post('/admin/konten', [
                'bagian' => 'ekskul',
                'judul' => 'Baris baru',
                'urutan' => 9,
                'isi' => 'Draf tambahan',
                '_form' => 'tambah-ekskul',
                'gambar' => $this->gambarTerlaluBesar(),
            ])
            ->assertSessionHasErrors('gambar');

        $html = $this->bukaDenganSesi($respons, '/admin/konten');

        $this->assertSame(1, substr_count($html, 'Draf tambahan'));
        $this->assertSame(1, substr_count($html, 'Baris baru'));
        $this->assertSame(1, substr_count($html, 'Isi lama satu'));

        // Tidak ada baris baru yang tersimpan.
        $this->assertSame(1, KontenSekolah::query()->count());
    }

    public function test_gambar_konten_sampai_sepuluh_mb_diterima_dan_dikecilkan(): void
    {
        $baris = $this->baris('Isi lama', 1);

        $this->actingAs($this->admin)
            ->put('/admin/konten/'.$baris->id, [
                'bagian' => 'ekskul',
                'judul' => 'Judul baru',
                'urutan' => 1,
                'isi' => 'Isi baru',
                'gambar' => UploadedFile::fake()->image('besar.jpg', 3000, 2000)->size(6000),
            ])
            ->assertSessionHasNoErrors();

        $path = $baris->refresh()->gambar_path;

        $this->assertNotNull($path);
        $this->assertTrue(Storage::disk('public')->exists($path));

        [$lebar, $tinggi] = getimagesizefromstring(Storage::disk('public')->get($path));

        // Foto tampilan sekolah boleh besar saat diunggah, tetapi yang
        // disimpan tetap versi kecil.
        $this->assertLessThanOrEqual(1600, max($lebar, $tinggi));
        $this->assertLessThan(6000 * 1024, Storage::disk('public')->size($path));

        // Label dan pemeriksaan ukuran di editor mengikuti config, bukan
        // angka tetap 2 MB.
        $this->actingAs($this->admin)
            ->get('/admin/konten')
            ->assertOk()
            ->assertSee('maks. 10 MB')
            ->assertSee('data-maks-berkas="10485760"', false);
    }

    public function test_pratinjau_gambar_di_editor_memakai_url_relatif(): void
    {
        $path = 'konten/'.$this->sekolah->id.'/prestasi-editor.jpg';

        Storage::disk('public')->put(
            $path,
            UploadedFile::fake()->image('prestasi-editor.jpg', 240, 180)->getContent(),
        );

        KontenSekolah::create([
            'sekolah_id' => $this->sekolah->id,
            'bagian' => 'prestasi',
            'judul' => 'Juara membuat web',
            'isi' => 'Juara lomba membuat web',
            'gambar_path' => $path,
            'urutan' => 1,
        ]);

        $html = $this->actingAs($this->admin)->get('/admin/konten')->assertOk()->getContent();

        // URL relatif, bukan absolut dari APP_URL yang bisa menunjuk host lain.
        $this->assertStringContainsString('src="/storage/'.$path.'"', $html);
        $this->assertStringNotContainsString('src="'.config('app.url').'/storage/', $html);
    }
}
