<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Butir 1 dan 2: matriks akses rute `/akun` dan isolasi data walisantri.
 */
class WalisantriAksesTest extends WalisantriTestCase
{
    private string $uuid;

    private string $berkasUuid;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uuid = $this->pendaftaran->uuid;
        $this->berkasUuid = $this->lampirkanBerkas($this->pendaftaran)->uuid;
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function ruteGetter(): array
    {
        return [
            'beranda' => ['GET', '/akun'],
            'orang tua' => ['GET', '/akun/orang-tua'],
            'daftar baru' => ['GET', '/akun/daftar/baru'],
            'detail' => ['GET', '/akun/pendaftaran/{uuid}'],
            'langkah' => ['GET', '/akun/pendaftaran/{uuid}/langkah/2'],
            'berkas' => ['GET', '/akun/pendaftaran/{uuid}/berkas'],
            'berkas tampil' => ['GET', '/akun/berkas/{berkas}'],
            'hasil' => ['GET', '/akun/pendaftaran/{uuid}/hasil'],
            'pembayaran' => ['GET', '/akun/pendaftaran/{uuid}/pembayaran'],
            'tahap 2' => ['GET', '/akun/pendaftaran/{uuid}/tahap-2'],
        ];
    }

    public function test_tamu_diarahkan_ke_masuk(): void
    {
        foreach (self::ruteGetter() as $nama => [$metode, $jalur]) {
            $respons = $this->panggil($metode, $jalur);

            $this->assertSame(302, $respons->getStatusCode(), "Tamu ke {$nama} seharusnya 302.");
            $this->assertStringContainsString('/masuk', (string) $respons->headers->get('Location'), "Tamu ke {$nama}.");
        }
    }

    public function test_peran_selain_walisantri_mendapat_403(): void
    {
        $panitia = User::factory()->panitia($this->sekolah)->create();

        foreach (self::ruteGetter() as $nama => [$metode, $jalur]) {
            $respons = $this->actingAs($panitia)->panggil($metode, $jalur);

            $this->assertSame(403, $respons->getStatusCode(), "Panitia ke {$nama} seharusnya 403.");
        }
    }

    public function test_walisantri_boleh_membuka_rutenya_sendiri(): void
    {
        $this->actingAs($this->wali);

        $this->get('/akun')->assertOk();
        $this->get('/akun/orang-tua')->assertOk();
        $this->get('/akun/daftar/baru')->assertOk();
        $this->get('/akun/pendaftaran/'.$this->uuid)->assertOk();
        $this->get('/akun/pendaftaran/'.$this->uuid.'/langkah/2')->assertOk();
        $this->get('/akun/pendaftaran/'.$this->uuid.'/berkas')->assertOk();
        $this->get('/akun/pendaftaran/'.$this->uuid.'/hasil')->assertOk();
    }

    public function test_walisantri_lain_tidak_bisa_melihat_atau_mengubah_data(): void
    {
        $lain = User::factory()->walisantri()->create();
        $this->actingAs($lain);

        $this->get('/akun/pendaftaran/'.$this->uuid)->assertNotFound();
        $this->get('/akun/pendaftaran/'.$this->uuid.'/hasil')->assertNotFound();
        $this->get('/akun/pendaftaran/'.$this->uuid.'/berkas')->assertNotFound();
        $this->get('/akun/berkas/'.$this->berkasUuid)->assertNotFound();
        $this->get('/akun/pendaftaran/'.$this->uuid.'/pembayaran')->assertNotFound();
        $this->get('/akun/pendaftaran/'.$this->uuid.'/tahap-2')->assertNotFound();

        $this->put('/akun/pendaftaran/'.$this->uuid.'/langkah/2', $this->dataLangkah2([
            'nama_lengkap' => 'Diubah Orang Lain',
        ]))->assertNotFound();

        $this->assertSame('Aisyah Nur Fadhilah', $this->anak->fresh()->nama_lengkap);
    }

    public function test_walisantri_tidak_bisa_membuat_pendaftaran_untuk_anak_orang_lain(): void
    {
        $lain = User::factory()->walisantri()->create();
        $this->actingAs($lain);

        $this->post('/akun/daftar/baru', [
            'sekolah_id' => $this->sekolah->id,
            'gelombang_id' => $this->gelombang->id,
            'anak_id' => $this->anak->id,
        ])->assertSessionHasErrors('anak_id');
    }

    /**
     * Radio `anak_id` tidak boleh `required`. Saat walisantri memilih "Anak
     * baru", skrip mengosongkan grup radio `anak_id`; bila grup itu wajib,
     * peramban menolak mengirim formulir tanpa pesan sehingga tombol "Lanjut"
     * seolah tidak berfungsi.
     */
    public function test_pilihan_anak_baru_tidak_diblokir_validasi_peramban(): void
    {
        $this->actingAs($this->wali);

        $html = $this->get('/akun/daftar/baru')->assertOk()->getContent();

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $pilihanAnak = $xpath->query('//input[@name="anak_id"]');

        $this->assertGreaterThan(0, $pilihanAnak->length, 'Pilihan anak yang sudah ada harus tampil.');

        foreach ($pilihanAnak as $input) {
            $this->assertFalse(
                $input->hasAttribute('required'),
                'Radio anak_id tidak boleh required karena memblokir pilihan "Anak baru".'
            );
        }

        $this->assertGreaterThan(
            0,
            $xpath->query('//input[@name="anak_baru"]')->length,
            'Pilihan "Anak baru" harus tersedia.'
        );
    }

    public function test_memilih_anak_baru_membuka_formulir_anak_baru(): void
    {
        $this->actingAs($this->wali);

        $this->post('/akun/daftar/baru', [
            'sekolah_id' => $this->sekolah->id,
            'gelombang_id' => $this->gelombang->id,
            'anak_baru' => 1,
        ])->assertRedirect(route('walisantri.daftar.anak-baru'));

        $this->get(route('walisantri.daftar.anak-baru'))
            ->assertOk()
            ->assertSee('Data anak');
    }

    private function panggil(string $metode, string $jalur): TestResponse
    {
        $jalur = str_replace(
            ['{uuid}', '{berkas}'],
            [$this->uuid, $this->berkasUuid],
            $jalur
        );

        return $this->call($metode, $jalur);
    }
}
