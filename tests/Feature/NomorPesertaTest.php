<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Models\Gelombang;
use App\Models\Pendaftaran;
use App\Models\Sekolah;
use App\Services\PendaftaranService;

/**
 * Butir 6: format, urutan, dan keunikan nomor peserta.
 */
class NomorPesertaTest extends WalisantriTestCase
{
    private function pendaftaranLain(string $nama = 'Anak Kedua'): Pendaftaran
    {
        $anak = Anak::factory()->create([
            'user_id' => $this->wali->id,
            'nama_lengkap' => $nama,
            'tanggal_lahir' => '2018-02-02',
        ]);

        $pendaftaran = Pendaftaran::factory()->create([
            'anak_id' => $anak->id,
            'sekolah_id' => $this->sekolah->id,
            'gelombang_id' => $this->gelombang->id,
            'dibuat_oleh' => $this->wali->id,
        ]);

        $this->lampirkanBerkas($pendaftaran);

        return $pendaftaran;
    }

    public function test_format_dan_urutan_sd(): void
    {
        $service = app(PendaftaranService::class);

        $this->lengkapiOrangTua();
        $this->lampirkanBerkas($this->pendaftaran);
        $pertama = $service->kirim($this->wali, $this->pendaftaran, true, true);

        $keduaPendaftaran = $this->pendaftaranLain();
        $kedua = $service->kirim($this->wali, $keduaPendaftaran, true, true);

        $this->assertSame('SD-2027-0001', $pertama->nomor_peserta);
        $this->assertSame('SD-2027-0002', $kedua->nomor_peserta);
        $this->assertNotSame($pertama->nomor_peserta, $kedua->nomor_peserta);
    }

    public function test_awalan_mengikuti_program_tki(): void
    {
        $service = app(PendaftaranService::class);

        $tki = Sekolah::factory()->create(['kode' => 'TKI', 'nama' => 'KB-TKIT Insan Mulia']);
        $gelombang = Gelombang::factory()->create([
            'sekolah_id' => $tki->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'status' => 'dibuka',
        ]);

        $this->lengkapiOrangTua();

        foreach (['KB', 'TK', 'TPA'] as $program) {
            $anak = Anak::factory()->create([
                'user_id' => $this->wali->id,
                'nama_lengkap' => 'Anak TKI '.$program,
                'tanggal_lahir' => '2022-03-03',
            ]);

            $pendaftaran = Pendaftaran::factory()->create([
                'anak_id' => $anak->id,
                'sekolah_id' => $tki->id,
                'gelombang_id' => $gelombang->id,
                'program' => $program,
                'dibuat_oleh' => $this->wali->id,
            ]);

            $this->lampirkanBerkas($pendaftaran);

            $hasil = $service->kirim($this->wali, $pendaftaran, true, true);

            $this->assertSame($program.'-2027-0001', $hasil->nomor_peserta);
        }
    }

    public function test_nomor_peserta_unik_di_database(): void
    {
        $lain = Pendaftaran::factory()->create([
            'anak_id' => Anak::factory()->create(['user_id' => $this->wali->id, 'nama_lengkap' => 'Anak Unik', 'tanggal_lahir' => '2017-07-07'])->id,
            'sekolah_id' => $this->sekolah->id,
            'gelombang_id' => $this->gelombang->id,
        ]);

        $this->pendaftaran->forceFill(['nomor_peserta' => 'SD-2027-0001'])->save();

        $this->expectException(\Illuminate\Database\QueryException::class);

        $lain->forceFill(['nomor_peserta' => 'SD-2027-0001'])->save();
    }

}
