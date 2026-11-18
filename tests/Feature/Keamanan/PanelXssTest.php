<?php

namespace Tests\Feature\Keamanan;

use App\Models\RincianBiaya;
use App\Models\User;
use Tests\Feature\PanitiaTestCase;

/**
 * Butir 14: XSS di panel Tahap 4. Nilai berisi <script> pada nama gelombang,
 * nama biaya, nama akun, dan konten ter-escape di semua halaman tahap ini.
 */
class PanelXssTest extends PanitiaTestCase
{
    private const JAHAT = '<script>alert("panel")</script>';

    private function pastikanTerEscape(string $rute, string $teks): void
    {
        $respons = $this->get($rute);
        $respons->assertOk();

        $isi = (string) $respons->getContent();

        $this->assertStringNotContainsString($teks, $isi, "Teks mentah tampil di {$rute}.");
        $this->assertStringContainsString(e($teks), $isi, "Teks ter-escape tidak ditemukan di {$rute}.");
    }

    public function test_nama_gelombang_di_escape(): void
    {
        $gelombang = \App\Models\Gelombang::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'nama' => self::JAHAT,
            'status' => 'draf',
        ]);

        $this->actingAs($this->admin);

        $this->pastikanTerEscape('/admin/gelombang', self::JAHAT);
        $this->pastikanTerEscape('/admin', self::JAHAT);

        // Nama tidak berubah oleh sistem (disimpan mentah, escape saat tampil).
        $this->assertSame(self::JAHAT, $gelombang->fresh()->nama);
    }

    public function test_nama_biaya_di_escape(): void
    {
        RincianBiaya::create([
            'sekolah_id' => $this->sekolah->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'kelompok' => 'IAP',
            'nama' => self::JAHAT,
            'nominal' => 100000,
            'tipe' => 'tetap',
            'urutan' => 1,
        ]);

        $this->actingAs($this->admin);

        $this->pastikanTerEscape('/admin/biaya', self::JAHAT);
    }

    public function test_nama_akun_panitia_di_escape(): void
    {
        $akun = User::factory()->panitia($this->sekolah)->create(['nama' => self::JAHAT]);

        \App\Models\LogAktivitas::create([
            'aksi' => 'uji_xss',
            'user_id' => $akun->id,
            'sekolah_id' => $this->sekolah->id,
        ]);

        $this->actingAs($this->admin);

        $this->pastikanTerEscape('/admin/panitia', self::JAHAT);
        $this->pastikanTerEscape('/admin/log', self::JAHAT);
    }

    public function test_isi_konten_di_escape(): void
    {
        $this->actingAs($this->admin)
            ->put('/admin/konten/teks', [
                'bagian' => 'profil',
                'isi' => self::JAHAT,
            ])
            ->assertSessionHasNoErrors();

        // strip_tags membuang tag sebelum simpan; sisa teks aman dan di-escape.
        $this->get('/admin/konten')
            ->assertOk()
            ->assertDontSee('<script>', false);

        $isi = (string) $this->get('/admin/konten')->getContent();

        $this->assertStringNotContainsString('<script>', $isi);
        $this->assertStringNotContainsString('onerror', $isi);
    }

    public function test_judul_baris_konten_di_escape(): void
    {
        // Judul disimpan apa adanya (strip_tags tidak menghapus teks dalam
        // tag yang ditulis tanpa tanda kurung) lalu di-escape saat tampil.
        $this->actingAs($this->admin)
            ->post('/admin/konten', [
                'bagian' => 'ekskul',
                'judul' => self::JAHAT,
                'isi' => 'Deskripsi ekskul',
            ])
            ->assertSessionHasNoErrors();

        $baris = \App\Models\KontenSekolah::query()
            ->where('sekolah_id', $this->sekolah->id)
            ->where('bagian', 'ekskul')
            ->firstOrFail();

        $this->assertStringNotContainsString('<script>', $baris->judul);

        $isi = (string) $this->get('/admin/konten')->getContent();

        $this->assertStringNotContainsString('<script>', $isi);
    }

    public function test_nama_sekolah_di_halaman_super_di_escape(): void
    {
        $sekolahXss = \App\Models\Sekolah::factory()->create(['nama' => self::JAHAT, 'kode' => 'XXS']);

        User::factory()->admin($sekolahXss)->create();

        $this->actingAs(User::factory()->superAdmin()->create());

        $this->pastikanTerEscape('/super/sekolah', self::JAHAT);
        $this->pastikanTerEscape('/super/admin', self::JAHAT);
        $this->pastikanTerEscape('/super/log', self::JAHAT);
    }
}
