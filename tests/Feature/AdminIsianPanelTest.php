<?php

namespace Tests\Feature;

use App\Models\JawabanTambahan;
use App\Models\PengaturanIsian;
use Illuminate\Support\Facades\DB;
use Tests\Feature\PanitiaTestCase;

/**
 * Butir 9: isian formulir. Isian inti tidak bisa disembunyikan atau dibuat
 * tidak wajib; menyembunyikan isian tidak menghapus jawaban yang sudah ada.
 */
class AdminIsianPanelTest extends PanitiaTestCase
{
    /**
     * @param  array<string, array{tampil: int, wajib: int}>  $isian
     * @return array<string, mixed>
     */
    private function badan(array $isian): array
    {
        return ['isian' => $isian];
    }

    public function test_isian_inti_selalu_tampil_dan_wajib(): void
    {
        $this->actingAs($this->admin)
            ->put('/admin/isian', $this->badan([
                'nama_lengkap' => ['tampil' => 0, 'wajib' => 0],
                'jenis_kelamin' => ['tampil' => 0, 'wajib' => 0],
                'tempat_lahir' => ['tampil' => 0, 'wajib' => 0],
                'tanggal_lahir' => ['tampil' => 0, 'wajib' => 0],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.isian.index'));

        foreach (['nama_lengkap', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir'] as $kunci) {
            $baris = PengaturanIsian::query()
                ->where('sekolah_id', $this->sekolah->id)
                ->where('kunci_isian', $kunci)
                ->first();

            $this->assertNotNull($baris, "Isian inti {$kunci} harus punya pengaturan.");
            $this->assertTrue((bool) $baris->tampil, "Isian inti {$kunci} tidak boleh disembunyikan.");
            $this->assertTrue((bool) $baris->wajib, "Isian inti {$kunci} tidak boleh jadi tidak wajib.");
        }
    }

    public function test_isian_bukan_inti_bisa_disembunyikan(): void
    {
        $this->actingAs($this->admin)
            ->put('/admin/isian', $this->badan([
                'hobi' => ['tampil' => 0, 'wajib' => 0],
                'nisn' => ['tampil' => 1, 'wajib' => 0],
            ]))
            ->assertSessionHasNoErrors();

        $hobi = PengaturanIsian::query()
            ->where('sekolah_id', $this->sekolah->id)
            ->where('kunci_isian', 'hobi')
            ->firstOrFail();

        $this->assertFalse((bool) $hobi->tampil);
        $this->assertFalse((bool) $hobi->wajib);

        $nisn = PengaturanIsian::query()
            ->where('sekolah_id', $this->sekolah->id)
            ->where('kunci_isian', 'nisn')
            ->firstOrFail();

        $this->assertTrue((bool) $nisn->tampil);
        $this->assertFalse((bool) $nisn->wajib);
    }

    public function test_menyembunyikan_isian_tidak_menghapus_jawaban_lama(): void
    {
        $p = $this->terkirim();

        JawabanTambahan::create([
            'pendaftaran_id' => $p->id,
            'bagian' => 'tahap2',
            'kunci' => 'hobi',
            'nilai' => 'Membaca buku',
        ]);

        $this->actingAs($this->admin)
            ->put('/admin/isian', $this->badan([
                'hobi' => ['tampil' => 0, 'wajib' => 0],
            ]))
            ->assertSessionHasNoErrors();

        $jawaban = JawabanTambahan::query()
            ->where('pendaftaran_id', $p->id)
            ->where('kunci', 'hobi')
            ->first();

        $this->assertNotNull($jawaban);
        $this->assertSame('Membaca buku', $jawaban->nilai);

        // Jawaban pada kolom anak juga tetap ada meski isian disembunyikan.
        $this->anak->forceFill(['tinggi_badan' => 120])->save();

        $this->actingAs($this->admin)
            ->put('/admin/isian', $this->badan([
                'tinggi_badan' => ['tampil' => 0, 'wajib' => 0],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(120, (int) DB::table('anak')->where('id', $this->anak->id)->value('tinggi_badan'));
    }

    public function test_kunci_di_luar_katalog_diabaikan(): void
    {
        $this->actingAs($this->admin)
            ->put('/admin/isian', $this->badan([
                'kunci_palsu' => ['tampil' => 1, 'wajib' => 1],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('pengaturan_isian', [
            'sekolah_id' => $this->sekolah->id,
            'kunci_isian' => 'kunci_palsu',
        ]);
    }

    public function test_perubahan_isian_dicatat_di_log(): void
    {
        $this->actingAs($this->admin)
            ->put('/admin/isian', $this->badan([
                'hobi' => ['tampil' => 0, 'wajib' => 0],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('log_aktivitas', [
            'aksi' => 'ubah_pengaturan_isian',
            'sekolah_id' => $this->sekolah->id,
        ]);
    }
}
