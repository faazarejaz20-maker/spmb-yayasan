<?php

namespace Tests\Feature;

use App\Models\OrangTua;

/**
 * Data wali bersifat pilihan: anak yang tinggal bersama ayah atau ibu tidak
 * punya data wali.
 *
 * Regresi: dropdown "Status" pada formulir selalu mengirim nilai (dibuka
 * dengan bawaan "Masih hidup"), sehingga wali yang dikosongkan dianggap
 * diisi — halaman menuntut nama wali dan baris wali hantu ikut tersimpan.
 */
class OrangTuaWaliTest extends WalisantriTestCase
{
    public function test_wali_dikosongkan_tidak_menuntut_nama(): void
    {
        $this->actingAs($this->wali)
            ->put('/akun/orang-tua', [
                'ayah' => ['nama' => 'Bapak Contoh', 'no_hp' => '081111111111'],
                'ibu' => ['nama' => 'Ibu Contoh', 'no_hp' => '081222222222'],
                'wali' => ['status_hidup' => 'Masih hidup'],
            ])
            ->assertRedirect(route('walisantri.orang-tua'))
            ->assertSessionHasNoErrors();

        // Ayah dan ibu tersimpan, wali tidak dibuat barisnya.
        $this->assertTrue(OrangTua::query()
            ->where('user_id', $this->wali->id)
            ->where('hubungan', 'ayah')
            ->where('nama', 'Bapak Contoh')
            ->exists());
        $this->assertFalse(OrangTua::query()
            ->where('user_id', $this->wali->id)
            ->where('hubungan', 'wali')
            ->exists());
    }

    public function test_wali_lama_yang_dikosongkan_hilang_isinya(): void
    {
        OrangTua::create([
            'user_id' => $this->wali->id,
            'hubungan' => 'wali',
            'nama' => 'Wali Lama',
            'no_hp' => '081333333333',
        ]);

        $this->actingAs($this->wali)
            ->put('/akun/orang-tua', [
                'wali' => ['status_hidup' => 'Sudah wafat'],
            ])
            ->assertRedirect(route('walisantri.orang-tua'))
            ->assertSessionHasNoErrors();

        $baris = OrangTua::query()
            ->where('user_id', $this->wali->id)
            ->where('hubungan', 'wali')
            ->first();

        // Baris tanpa data tidak sah (`nama` tidak boleh kosong), jadi
        // dikosongkan berarti barisnya dihapus.
        $this->assertNull($baris);
    }

    public function test_wali_yang_diisi_tetap_tersimpan(): void
    {
        $this->actingAs($this->wali)
            ->put('/akun/orang-tua', [
                'wali' => [
                    'nama' => 'Wali Baru',
                    'no_hp' => '081444444444',
                    'status_hidup' => 'Sudah wafat',
                ],
            ])
            ->assertRedirect(route('walisantri.orang-tua'))
            ->assertSessionHasNoErrors();

        $baris = OrangTua::query()
            ->where('user_id', $this->wali->id)
            ->where('hubungan', 'wali')
            ->first();

        $this->assertNotNull($baris);
        $this->assertSame('Wali Baru', $baris->nama);
        $this->assertSame('Sudah wafat', $baris->status_hidup);
    }
}
