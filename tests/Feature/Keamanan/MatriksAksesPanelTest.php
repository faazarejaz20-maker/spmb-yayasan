<?php

namespace Tests\Feature\Keamanan;

use App\Models\User;
use Tests\Feature\PanitiaTestCase;

/**
 * Butir 1: matriks akses seluruh rute GET /admin, /super, dan /yayasan.
 *
 * Tamu 302 (ke halaman masuk); walisantri, panitia, dan peran lain 403;
 * hanya peran yang berhak 200.
 */
class MatriksAksesPanelTest extends PanitiaTestCase
{
    /**
     * @return array<int, string>
     */
    private function ruteAdmin(): array
    {
        return [
            '/admin',
            '/admin/gelombang',
            '/admin/pengaturan',
            '/admin/panitia',
            '/admin/berkas',
            '/admin/biaya',
            '/admin/rekening',
            '/admin/isian',
            '/admin/pengumuman',
            '/admin/rekap',
            '/admin/log',
            '/admin/konten',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function ruteSuper(): array
    {
        return [
            '/super',
            '/super/sekolah',
            '/super/sekolah/'.$this->sekolah->id.'/edit',
            '/super/admin',
            '/super/yayasan',
            '/super/konten',
            '/super/log',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function ruteYayasan(): array
    {
        return [
            '/yayasan',
            '/yayasan/rekap',
            '/yayasan/profil',
        ];
    }

    public function test_tamu_dialihkan_ke_halaman_masuk_di_semua_panel(): void
    {
        foreach ([...$this->ruteAdmin(), ...$this->ruteSuper(), ...$this->ruteYayasan()] as $rute) {
            $this->get($rute)->assertRedirect('/masuk');
        }
    }

    public function test_peran_tidak_berhak_mendapat_403_di_admin(): void
    {
        foreach ($this->ruteAdmin() as $rute) {
            foreach (['walisantri', 'panitia', 'super_admin', 'yayasan'] as $peran) {
                $pengguna = match ($peran) {
                    'walisantri' => User::factory()->walisantri()->create(),
                    'panitia' => User::factory()->panitia($this->sekolah)->create(),
                    'super_admin' => User::factory()->superAdmin()->create(),
                    'yayasan' => User::factory()->yayasan()->create(),
                };

                $this->actingAs($pengguna)
                    ->get($rute)
                    ->assertForbidden();

                $pengguna->delete();
            }
        }
    }

    public function test_admin_mendapat_200_di_admin_dan_403_di_panel_lain(): void
    {
        foreach ($this->ruteAdmin() as $rute) {
            $this->actingAs($this->admin)->get($rute)->assertOk();
        }

        foreach ([...$this->ruteSuper(), ...$this->ruteYayasan()] as $rute) {
            $this->actingAs($this->admin)->get($rute)->assertForbidden();
        }
    }

    public function test_super_admin_mendapat_200_di_super_dan_403_di_panel_lain(): void
    {
        $super = User::factory()->superAdmin()->create();

        foreach ($this->ruteSuper() as $rute) {
            $this->actingAs($super)->get($rute)->assertOk();
        }

        foreach ([...$this->ruteAdmin(), ...$this->ruteYayasan()] as $rute) {
            $this->actingAs($super)->get($rute)->assertForbidden();
        }
    }

    public function test_yayasan_mendapat_200_di_yayasan_dan_403_di_panel_lain(): void
    {
        $yayasan = User::factory()->yayasan()->create();

        foreach ($this->ruteYayasan() as $rute) {
            $this->actingAs($yayasan)->get($rute)->assertOk();
        }

        foreach ([...$this->ruteAdmin(), ...$this->ruteSuper()] as $rute) {
            $this->actingAs($yayasan)->get($rute)->assertForbidden();
        }
    }

    public function test_panitia_sekolah_a_dialihkan_atau_ditolak_di_data_admin_sekolah_b(): void
    {
        // Binding model dengan scope MilikSekolah selesai lebih dulu daripada
        // middleware peran, sehingga data sekolah lain menghasilkan 404.
        $this->actingAs($this->panitiaB)
            ->get('/admin/pengumuman/'.$this->gelombang->id)
            ->assertNotFound();

        $this->actingAs($this->panitia)
            ->get('/admin/pengumuman/'.$this->gelombangB->id)
            ->assertNotFound();
    }
}
