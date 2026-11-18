<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;

/**
 * Bagian asal sekolah (langkah 5) pada formulir walisantri.
 *
 * Regresi: nilai `lulusan_yayasan` dihitung oleh `PendaftaranService` dan
 * hanya disimpan pada kolom `pendaftaran.lulusan_yayasan`; kolom itu bukan
 * bagian tabel `anak`, sehingga tidak boleh ikut `fill()` anak. Sebelum
 * diperbaiki, langkah 5 selalu menghentikan halaman (500).
 */
class AsalSekolahTest extends WalisantriTestCase
{
    public function test_langkah_5_tersimpan_dan_lulusan_yayasan_dihitung(): void
    {
        $rute = '/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/5';

        // Asal sekolah sama persis dengan nama sekolah yayasan pada fixture.
        $this->actingAs($this->wali)
            ->put($rute, [
                'asal_sekolah' => 'SDIT Insan Mulia Wonosobo',
                'jenis_asal_sekolah' => 'TK',
            ])
            ->assertRedirect(route('walisantri.pendaftaran.berkas.index', $this->pendaftaran->uuid))
            ->assertSessionHasNoErrors();

        $this->assertSame('SDIT Insan Mulia Wonosobo', $this->anak->fresh()->asal_sekolah);
        $this->assertSame(
            1,
            (int) DB::table('pendaftaran')->where('id', $this->pendaftaran->id)->value('lulusan_yayasan'),
        );

        // Asal sekolah lain: tanda lulusan yayasan kembali ke false.
        $this->actingAs($this->wali)
            ->put($rute, [
                'asal_sekolah' => 'TK Negeri Kertek',
                'jenis_asal_sekolah' => 'TK',
            ])
            ->assertRedirect(route('walisantri.pendaftaran.berkas.index', $this->pendaftaran->uuid))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            0,
            (int) DB::table('pendaftaran')->where('id', $this->pendaftaran->id)->value('lulusan_yayasan'),
        );
    }

    public function test_lulusan_yayasan_tidak_bisa_dipalsukan_dari_formulir(): void
    {
        $this->actingAs($this->wali)
            ->put('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/5', [
                'asal_sekolah' => 'TK Negeri Kertek',
                'jenis_asal_sekolah' => 'TK',
                'lulusan_yayasan' => '1',
            ])
            ->assertRedirect(route('walisantri.pendaftaran.berkas.index', $this->pendaftaran->uuid))
            ->assertSessionHasNoErrors();

        // Nilai selalu dihitung ulang dari asal sekolah, bukan dari masukan.
        $this->assertSame(
            0,
            (int) DB::table('pendaftaran')->where('id', $this->pendaftaran->id)->value('lulusan_yayasan'),
        );
    }
}
