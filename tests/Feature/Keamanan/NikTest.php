<?php

namespace Tests\Feature\Keamanan;

use App\Models\Anak;
use App\Support\Samarkan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\WalisantriTestCase;

/**
 * Butir 16: NIK dan nomor KK disimpan terenkripsi, disembunyikan dari
 * serialisasi, dan disamarkan saat ditampilkan.
 */
class NikTest extends WalisantriTestCase
{
    private const NIK = '3307015204190001';

    private const KK = '3307010101010002';

    private function bacaMentah(string $kolom): ?string
    {
        $baris = DB::table('anak')->where('id', $this->anak->id)->first();

        return $baris?->{$kolom};
    }

    public function test_nik_dan_kk_terenkripsi_di_database(): void
    {
        $this->assertNotSame(self::NIK, $this->bacaMentah('nik'));
        $this->assertNotSame(self::KK, $this->bacaMentah('no_kk'));
        $this->assertStringNotContainsString(self::NIK, (string) $this->bacaMentah('nik'));

        $segar = $this->anak->fresh();

        $this->assertSame(self::NIK, $segar->nik);
        $this->assertSame(self::KK, $segar->no_kk);
    }

    public function test_nik_dan_kk_tersembunyi_dari_serialisasi(): void
    {
        $array = $this->anak->fresh()->toArray();

        $this->assertArrayNotHasKey('nik', $array);
        $this->assertArrayNotHasKey('no_kk', $array);
        $this->assertStringNotContainsString(self::NIK, json_encode($array));
    }

    public function test_ringkasan_menyamarkan_nik(): void
    {
        $this->actingAs($this->wali)
            ->get('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/8')
            ->assertOk()
            ->assertDontSee(self::NIK, false)
            ->assertSee(Samarkan::nik(self::NIK), false);
    }

    public function test_samarkan_nik_tidak_membocorkan_semua_angka(): void
    {
        $hasil = Samarkan::nik(self::NIK);

        $this->assertSame('3307********0001', $hasil);
        $this->assertSame('—', Samarkan::nik(null));
        $this->assertSame('****', Samarkan::nik('1234'));
    }

    public function test_nik_tidak_ikut_tersimpan_dari_formulir_langkah_lain(): void
    {
        $this->actingAs($this->wali);

        $this->put('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/3', [
            'alamat' => 'Jl. Baru No. 2',
            'nik' => '9999999999999999',
        ])->assertRedirect();

        $this->assertSame(self::NIK, $this->anak->fresh()->nik);
    }

    public function test_anak_baru_wajib_menyimpan_nik_terenkripsi(): void
    {
        $this->actingAs($this->wali);

        $this->post('/akun/daftar/baru', [
            'sekolah_id' => $this->sekolah->id,
            'gelombang_id' => $this->gelombang->id,
            'anak_baru' => 1,
        ])->assertRedirect(route('walisantri.daftar.anak-baru'));

        $this->put('/akun/daftar/baru/anak', [
            'nama_lengkap' => 'Anak Baru Nik',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Wonosobo',
            'tanggal_lahir' => '2019-05-05',
            'nik' => '3307010101190003',
            'no_kk' => '3307010101010004',
        ])->assertRedirect();

        $anak = Anak::query()->where('nama_lengkap', 'Anak Baru Nik')->firstOrFail();

        $baris = DB::table('anak')->where('id', $anak->id)->first();

        $this->assertStringNotContainsString('3307010101190003', (string) $baris->nik);
        $this->assertSame('3307010101190003', $anak->nik);
    }
}
