<?php

namespace Tests\Feature\Keamanan;

use App\Enums\StatusHasil;
use Illuminate\Support\Facades\DB;
use Tests\Feature\WalisantriTestCase;

/**
 * Butir 3: kolom sensitif tidak berubah walau dikirim dari formulir.
 */
class MassAssignmentTest extends WalisantriTestCase
{
    public function test_kolom_sensitif_dari_formulir_langkah_diabaikan(): void
    {
        $this->actingAs($this->wali);

        $this->put('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/2', $this->dataLangkah2([
            'user_id' => 999,
            'sekolah_id' => 999,
            'status_hasil' => StatusHasil::Diterima->value,
            'nomor_peserta' => 'SD-2027-9999',
            'dikirim_pada' => now()->toDateTimeString(),
            'diputuskan_oleh' => 999,
            'catatan_panitia' => 'Disetujui',
        ]))->assertRedirect();

        $anak = $this->anak->fresh();
        $pendaftaran = $this->pendaftaran->fresh();

        $this->assertSame($this->wali->id, (int) $anak->user_id);
        $this->assertSame('Aisyah Nur Fadhilah', $anak->nama_lengkap);

        $this->assertSame($this->sekolah->id, (int) $pendaftaran->sekolah_id);
        $this->assertSame(StatusHasil::Baru, $pendaftaran->status_hasil);
        $this->assertNull($pendaftaran->nomor_peserta);
        $this->assertNull($pendaftaran->dikirim_pada);
        $this->assertNull($pendaftaran->diputuskan_oleh);
        $this->assertNull($pendaftaran->catatan_panitia);
    }

    public function test_user_id_pada_formulir_orang_tua_diabaikan(): void
    {
        $this->actingAs($this->wali);

        $this->put('/akun/orang-tua', [
            'ayah' => [
                'user_id' => 999,
                'nama' => 'Bapak Contoh',
                'no_hp' => '08111111111',
            ],
        ])->assertRedirect();

        $baris = DB::table('orang_tua')->first();

        $this->assertNotNull($baris);
        $this->assertSame($this->wali->id, (int) $baris->user_id);
    }

    public function test_nomor_dan_status_tidak_fillable(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\MassAssignmentException::class);

        new \App\Models\Pendaftaran([
            'anak_id' => $this->anak->id,
            'status_hasil' => StatusHasil::Diterima->value,
            'nomor_peserta' => 'SD-2027-9999',
        ]);
    }
}
