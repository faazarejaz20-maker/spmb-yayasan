<?php

namespace Tests\Feature;

use App\Models\Gelombang;
use App\Models\JenisBerkas;
use App\Models\KontenSekolah;
use App\Models\RekeningSekolah;
use App\Models\RincianBiaya;
use App\Models\Sekolah;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Support\GambarPublik;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tahap 6 butir 1–3: halaman publik, status pendaftaran, kebocoran data,
 * escaping konten, robots, sitemap, dan placeholder gambar.
 */
class HalamanPublikTest extends TestCase
{
    use RefreshDatabase;

    private TahunAjaran $tahun;

    private Sekolah $sd;

    private Gelombang $gelombang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tahun = TahunAjaran::factory()->create(['nama' => '2027/2028']);

        foreach (['TKK', 'TKI', 'SD', 'SMP'] as $kode) {
            $sekolah = Sekolah::factory()->create([
                'kode' => $kode,
                'nama' => 'Sekolah '.$kode,
                'jenjang' => in_array($kode, ['TKK', 'TKI'], true) ? 'TK' : $kode,
                'kontak' => '6285234575505',
            ]);

            if ($kode === 'SD') {
                $this->sd = $sekolah;
            }
        }

        $this->gelombang = Gelombang::factory()->create([
            'sekolah_id' => $this->sd->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'nama' => 'Gelombang 1',
            'status' => 'dibuka',
            'tanggal_buka' => now()->subDay(),
            'tanggal_tutup' => now()->addMonth(),
            'kuota' => 987654,
            'tautan_grup_wa' => 'https://chat.whatsapp.com/grup-rahasia-xyz',
        ]);
    }

    public function test_halaman_publik_terbuka_untuk_tamu(): void
    {
        $this->get('/')->assertOk()->assertSee('Penerimaan Murid Baru');
        $this->get('/sekolah/tkk')->assertOk();
        $this->get('/sekolah/tki')->assertOk()->assertSee('KB (Kelompok Bermain)')->assertSee('TPA (Taman Penitipan Anak)');
        $this->get('/sekolah/sd')->assertOk()->assertSee('Sekolah SD');
        $this->get('/sekolah/smp')->assertOk();
        $this->get('/kebijakan-privasi')->assertOk()->assertSee('Draf, menunggu tinjauan yayasan');
    }

    public function test_kode_sekolah_tak_dikenal_404(): void
    {
        $this->get('/sekolah/tidak-ada')->assertNotFound();
    }

    public function test_status_pendaftaran_dibuka_tanpa_kuota_dan_grup_wa(): void
    {
        $respons = $this->get('/sekolah/sd');

        $respons->assertOk();
        $respons->assertSee('Pendaftaran dibuka');

        // Nilai khas tidak boleh tampil di HTML.
        $respons->assertDontSee('987654');
        $respons->assertDontSee('grup-rahasia-xyz');
        $respons->assertDontSee('chat.whatsapp.com');
        $respons->assertDontSee(strtolower('kuota'), false);
    }

    public function test_biaya_rekening_dan_jumlah_pendaftar_tidak_tampil(): void
    {
        RincianBiaya::create([
            'sekolah_id' => $this->sd->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'nama' => 'IAP',
            'nominal' => 8765432,
        ]);

        RekeningSekolah::create([
            'sekolah_id' => $this->sd->id,
            'bank' => 'Bank Uji',
            'nomor_rekening' => '112233445566',
            'atas_nama' => 'Yayasan',
        ]);

        foreach (['/', '/sekolah/sd'] as $jalur) {
            $respons = $this->get($jalur);
            $respons->assertOk();
            $respons->assertDontSee('8765432');
            $respons->assertDontSee('112233445566');
            $respons->assertDontSee('Rp', false);
            $respons->assertDontSee('IAP');
        }
    }

    /**
     * K-17: halaman publik tidak boleh menyentuh tabel/kolom biaya, rekening,
     * pendaftar, kuota, atau tautan grup WhatsApp sama sekali — bukan sekadar
     * tidak menampilkannya.
     */
    public function test_query_halaman_publik_tidak_menyentuh_data_terlarang(): void
    {
        Cache::flush();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get('/')->assertOk();
        $this->get('/sekolah/sd')->assertOk();

        $sql = strtolower(implode(' ', array_column(DB::getQueryLog(), 'query')));

        DB::disableQueryLog();

        $this->assertStringNotContainsString('rincian_biaya', $sql);
        $this->assertStringNotContainsString('rekening_sekolah', $sql);
        $this->assertDoesNotMatchRegularExpression('/\b(from|join)\s+["`]?pendaftaran\b/', $sql);
        $this->assertStringNotContainsString('kuota', $sql);
        $this->assertStringNotContainsString('tautan_grup_wa', $sql);
    }

    public function test_konten_admin_ter_escape(): void
    {
        KontenSekolah::create([
            'sekolah_id' => $this->sd->id,
            'bagian' => 'profil',
            'isi' => "<script>alert('xss')</script> Profil <b>tebal</b>",
        ]);

        $respons = $this->get('/sekolah/sd');

        $respons->assertOk();
        $respons->assertDontSee("<script>alert('xss')</script>", false);
        $respons->assertSee(e("<script>alert('xss')</script> Profil <b>tebal</b>"), false);
    }

    public function test_sekolah_tanpa_berkas_gambar_memakai_placeholder(): void
    {
        $sekolah = Sekolah::factory()->create([
            'kode' => 'ABC',
            'nama' => 'Sekolah Tanpa Foto',
            'jenjang' => 'SD',
        ]);

        Gelombang::factory()->create([
            'sekolah_id' => $sekolah->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'status' => 'dibuka',
        ]);

        $respons = $this->get('/sekolah/abc');

        $respons->assertOk();
        $respons->assertSee('Logo menyusul');
        $respons->assertSee('Foto menyusul');
    }

    /**
     * Foto sorotan beranda harus memakai berkas asli sekolah, dengan URL yang
     * aman (tanpa spasi/kurung) dan keterangan berbahasa Indonesia.
     */
    /**
     * Foto prestasi yang diunggah lewat Kelola Konten harus tampil di halaman
     * sekolah dengan URL relatif. URL absolut dari `APP_URL` bisa menunjuk
     * host yang salah (misalnya `localhost` padahal aplikasi dibuka dari
     * `127.0.0.1:8000`), sehingga gambarnya gagal dimuat.
     */
    public function test_foto_prestasi_konten_memakai_url_relatif(): void
    {
        Storage::fake('public');

        $path = 'konten/'.$this->sd->id.'/prestasi-uji.jpg';
        Storage::disk('public')->put(
            $path,
            UploadedFile::fake()->image('prestasi-uji.jpg', 480, 320)->getContent(),
        );

        KontenSekolah::create([
            'sekolah_id' => $this->sd->id,
            'bagian' => 'prestasi',
            'judul' => 'Juara membuat web',
            'isi' => 'Juara lomba membuat web',
            'gambar_path' => $path,
            'urutan' => 1,
        ]);

        $html = $this->get('/sekolah/sd')->assertOk()->getContent();

        $this->assertStringContainsString('src="/storage/'.$path.'"', $html);
        $this->assertStringNotContainsString('src="'.config('app.url').'/storage/', $html);
        $this->assertStringContainsString('Juara membuat web', $html);
    }

    public function test_sorotan_beranda_memakai_foto_asli_sekolah(): void
    {
        $sorotan = app(GambarPublik::class)->sorotan();

        $this->assertNotEmpty($sorotan);

        foreach ($sorotan as $foto) {
            $this->assertStringStartsWith('/', $foto['url']);
            $this->assertStringNotContainsString(' ', $foto['url']);
            $this->assertStringNotContainsString('(', $foto['url']);
            $this->assertNotSame('', $foto['keterangan']);
            $this->assertNotSame('', $foto['alt']);
        }

        $this->get('/')
            ->assertOk()
            ->assertSee('Prestasi dan Kegiatan Murid')
            ->assertSee($sorotan[0]['keterangan']);
    }

    public function test_nama_berkas_optimasi_stabil_dan_aman_untuk_url(): void
    {
        $relatif = 'prestasi/sd/prestasisd (1).jpeg';
        $nama = GambarPublik::namaOptimasi($relatif);

        $this->assertSame(
            'prestasi/sd/prestasisd-1-'.substr(sha1($relatif), 0, 8).'.webp',
            $nama
        );
        $this->assertStringNotContainsString(' ', $nama);
        $this->assertStringNotContainsString('(', $nama);
    }

    public function test_berkas_wajib_tampil_tanpa_biaya(): void
    {
        JenisBerkas::factory()->create([
            'sekolah_id' => $this->sd->id,
            'nama' => 'Kartu Keluarga',
            'wajib' => true,
            'jumlah_lembar' => 2,
        ]);

        $this->get('/sekolah/sd')
            ->assertOk()
            ->assertSee('Kartu Keluarga')
            ->assertSee('Wajib')
            ->assertSee('2 lembar');
    }

    public function test_robots_memblokir_jalur_panel_dan_memuat_sitemap(): void
    {
        $respons = $this->get('/robots.txt');

        $respons->assertOk();
        $respons->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $respons->assertSee('Disallow: /akun', false);
        $respons->assertSee('Disallow: /panitia', false);
        $respons->assertSee('Disallow: /admin', false);
        $respons->assertSee('Disallow: /super', false);
        $respons->assertSee('Disallow: /yayasan', false);
        $respons->assertSee('Sitemap: '.route('sitemap'), false);
    }

    public function test_sitemap_memuat_halaman_publik_dan_sekolah(): void
    {
        $respons = $this->get('/sitemap.xml');

        $respons->assertOk();
        $respons->assertSee('<urlset', false);
        $respons->assertSee(route('beranda'), false);
        $respons->assertSee(route('kebijakan-privasi'), false);
        $respons->assertSee(route('sekolah', 'tkk'), false);
        $respons->assertSee(route('sekolah', 'sd'), false);
    }

    public function test_halaman_masuk_noindex(): void
    {
        $this->get('/masuk')->assertOk()->assertSee('name="robots" content="noindex, nofollow"', false);
        $this->get('/daftar-akun')->assertOk()->assertSee('name="robots" content="noindex, nofollow"', false);
        $this->get('/lupa-password')->assertOk()->assertSee('name="robots" content="noindex, nofollow"', false);
    }

    public function test_panel_dan_akun_noindex(): void
    {
        $panitia = User::factory()->panitia($this->sd)->create();

        $this->actingAs($panitia)
            ->get('/panitia')
            ->assertOk()
            ->assertSee('name="robots" content="noindex, nofollow"', false);
    }

    public function test_halaman_galat_tidak_membocorkan_jejak(): void
    {
        $this->get('/halaman-tidak-ada')->assertNotFound()->assertSee('Halaman Tidak Ditemukan');

        foreach ([403, 404, 419, 429, 500] as $kode) {
            $html = view('errors.'.$kode)->render();

            $this->assertStringNotContainsString('Exception', $html);
            $this->assertStringNotContainsString('vendor'.DIRECTORY_SEPARATOR, $html);
            $this->assertStringNotContainsString('stack trace', strtolower($html));
            $this->assertStringContainsString('name="robots" content="noindex, nofollow"', $html);
        }
    }
}
