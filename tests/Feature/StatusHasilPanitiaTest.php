<?php

namespace Tests\Feature;

use App\Enums\StatusBerkas;
use App\Enums\StatusHasil;
use App\Enums\StatusMurid;
use App\Models\JenisBerkas;
use App\Models\Pengumuman;
use App\Models\Pendaftaran;
use App\Models\User;
use App\Services\StatusHasilService;

/**
 * Butir 5, 6, dan 7: matriks perpindahan status, kuota, dan catatan
 * Perlu perbaikan yang tampil ke walisantri.
 */
class StatusHasilPanitiaTest extends PanitiaTestCase
{
    private function daftarDengan(StatusHasil $status): Pendaftaran
    {
        $wali = User::factory()->walisantri()->create();

        return $this->buatPendaftaranTerkirim($wali, null, $this->sekolah, $this->gelombang, $status->value);
    }

    /**
     * @param  array<string, mixed>  $tambahan
     */
    private function putuskan(
        User $staf,
        Pendaftaran $pendaftaran,
        StatusHasil $tujuan,
        ?string $catatan = null,
        array $tambahan = [],
    ): \Illuminate\Testing\TestResponse {
        return $this->actingAs($staf)->post(
            route('panitia.pendaftar.keputusan', $pendaftaran->uuid),
            array_merge(['status' => $tujuan->value, 'catatan' => $catatan], $tambahan),
        );
    }

    private function umumkan(): void
    {
        Pengumuman::create([
            'sekolah_id' => $this->sekolah->id,
            'gelombang_id' => $this->gelombang->id,
            'diumumkan_pada' => now(),
        ]);
    }

    public function test_perpindahan_yang_diizinkan_berhasil(): void
    {
        $kasus = [
            [StatusHasil::Baru, StatusHasil::Diverifikasi, null],
            [StatusHasil::Baru, StatusHasil::PerluPerbaikan, 'Lengkapi alamat'],
            [StatusHasil::Diverifikasi, StatusHasil::Diterima, null],
            [StatusHasil::Diverifikasi, StatusHasil::Ditolak, null],
            [StatusHasil::Diverifikasi, StatusHasil::Cadangan, null],
            [StatusHasil::Diverifikasi, StatusHasil::PerluPerbaikan, 'Perbaiki NIK'],
            [StatusHasil::Cadangan, StatusHasil::Diterima, null],
            [StatusHasil::Cadangan, StatusHasil::Ditolak, null],
            [StatusHasil::Ditolak, StatusHasil::Diterima, null],
            [StatusHasil::Ditolak, StatusHasil::Cadangan, null],
            [StatusHasil::Diterima, StatusHasil::Ditolak, null],
        ];

        foreach ($kasus as [$dari, $tujuan, $catatan]) {
            $pendaftaran = $this->daftarDengan($dari);

            $this->putuskan($this->panitia, $pendaftaran, $tujuan, $catatan)
                ->assertRedirect(route('panitia.pendaftar.detail', $pendaftaran->uuid))
                ->assertSessionHasNoErrors();

            $this->assertSame($tujuan, $pendaftaran->refresh()->status_hasil);
        }
    }

    public function test_perpindahan_yang_tidak_diizinkan_ditolak(): void
    {
        $kasus = [
            [StatusHasil::Baru, StatusHasil::Diterima],
            [StatusHasil::Baru, StatusHasil::Ditolak],
            [StatusHasil::Baru, StatusHasil::Cadangan],
            [StatusHasil::Diverifikasi, StatusHasil::Baru],
            [StatusHasil::Ditolak, StatusHasil::Diverifikasi],
            [StatusHasil::Diterima, StatusHasil::Diverifikasi],
            [StatusHasil::Cadangan, StatusHasil::PerluPerbaikan],
            [StatusHasil::PerluPerbaikan, StatusHasil::Diverifikasi],
        ];

        foreach ($kasus as [$dari, $tujuan]) {
            $pendaftaran = $this->daftarDengan($dari);

            $this->putuskan($this->panitia, $pendaftaran, $tujuan, 'Catatan uji')
                ->assertSessionHasErrors('keputusan');

            $this->assertSame($dari, $pendaftaran->refresh()->status_hasil);
        }
    }

    public function test_hasil_akhir_hanya_dari_diverifikasi_atau_cadangan(): void
    {
        $baru = $this->daftarDengan(StatusHasil::Baru);

        $pilihan = collect(app(StatusHasilService::class)->pilihan($baru, $this->panitia))
            ->pluck('status')
            ->all();

        $this->assertSame([StatusHasil::Diverifikasi, StatusHasil::PerluPerbaikan], $pilihan);
    }

    public function test_diverifikasi_ditolak_bila_ada_berkas_perlu_perbaikan(): void
    {
        $pendaftaran = $this->terkirim();
        $berkas = $this->lampirkanBerkas($pendaftaran);

        $berkas->forceFill([
            'status' => StatusBerkas::PerluPerbaikan,
            'catatan' => 'Unggah ulang',
        ])->save();

        $this->putuskan($this->panitia, $pendaftaran, StatusHasil::Diverifikasi)
            ->assertSessionHasErrors('keputusan');

        $this->assertSame(StatusHasil::Baru, $pendaftaran->refresh()->status_hasil);
    }

    public function test_verifikasi_meminta_konfirmasi_bila_berkas_wajib_belum_siap(): void
    {
        JenisBerkas::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'wajib' => true,
            'aktif' => true,
        ]);

        $pendaftaran = $this->terkirim();

        $this->putuskan($this->panitia, $pendaftaran, StatusHasil::Diverifikasi)
            ->assertSessionHasErrors('keputusan');

        $this->putuskan($this->panitia, $pendaftaran, StatusHasil::Diverifikasi, null, [
            'konfirmasi_berkas' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame(StatusHasil::Diverifikasi, $pendaftaran->refresh()->status_hasil);
    }

    public function test_setelah_diumumkan_panitia_ditolak_dan_admin_wajib_catatan(): void
    {
        $pendaftaran = $this->daftarDengan(StatusHasil::Diterima);
        $this->umumkan();

        $this->putuskan($this->panitia, $pendaftaran, StatusHasil::Ditolak, 'Alasan apa pun')
            ->assertSessionHasErrors('keputusan');

        $this->putuskan($this->admin, $pendaftaran, StatusHasil::Ditolak)
            ->assertSessionHasErrors('keputusan');

        $this->assertSame(StatusHasil::Diterima, $pendaftaran->refresh()->status_hasil);

        $this->putuskan($this->admin, $pendaftaran, StatusHasil::Ditolak, 'Kesalahan input nilai')
            ->assertSessionHasNoErrors();

        $this->assertSame(StatusHasil::Ditolak, $pendaftaran->refresh()->status_hasil);
    }

    public function test_perlu_perbaikan_wajib_catatan(): void
    {
        $pendaftaran = $this->daftarDengan(StatusHasil::Diverifikasi);

        $this->putuskan($this->panitia, $pendaftaran, StatusHasil::PerluPerbaikan)
            ->assertSessionHasErrors('keputusan');

        $this->putuskan($this->panitia, $pendaftaran, StatusHasil::PerluPerbaikan, '   ')
            ->assertSessionHasErrors('keputusan');

        $this->assertSame(StatusHasil::Diverifikasi, $pendaftaran->refresh()->status_hasil);
    }

    public function test_catatan_perbaikan_tampil_ke_walisantri_dan_catatan_lain_tidak(): void
    {
        $pendaftaran = $this->terkirim();

        $this->putuskan($this->panitia, $pendaftaran, StatusHasil::Diverifikasi, 'CATATAN-VERIFIKASI-INTERNAL')
            ->assertSessionHasNoErrors();

        $this->actingAs($this->panitia)
            ->put(route('panitia.pendaftar.catatan', $pendaftaran->uuid), [
                'catatan_panitia' => 'RAHASIA-INTERNAL-PANITIA',
            ])
            ->assertSessionHasNoErrors();

        $this->putuskan($this->panitia, $pendaftaran, StatusHasil::PerluPerbaikan, 'Mohon perbaiki NIK anak.')
            ->assertSessionHasNoErrors();

        $this->actingAs($this->wali)
            ->get(route('walisantri.pendaftaran.detail', $pendaftaran->uuid))
            ->assertOk()
            ->assertSee('Perlu perbaikan')
            ->assertSee('Mohon perbaiki NIK anak.')
            ->assertDontSee('RAHASIA-INTERNAL-PANITIA')
            ->assertDontSee('CATATAN-VERIFIKASI-INTERNAL');
    }

    public function test_kirim_perbaikan_mengembalikan_status_ke_baru(): void
    {
        $pendaftaran = $this->terkirim();

        $this->putuskan($this->panitia, $pendaftaran, StatusHasil::PerluPerbaikan, 'Perbaiki data')
            ->assertSessionHasNoErrors();

        $this->siapkanKirim($pendaftaran);

        $this->actingAs($this->wali)
            ->post(route('walisantri.pendaftaran.kirim-perbaikan', $pendaftaran->uuid))
            ->assertSessionHasNoErrors();

        $this->assertSame(StatusHasil::Baru, $pendaftaran->refresh()->status_hasil);
    }

    public function test_kuota_penuh_menolak_diterima(): void
    {
        $this->gelombang->forceFill(['kuota' => 1])->save();

        $this->daftarDengan(StatusHasil::Diterima);
        $calon = $this->daftarDengan(StatusHasil::Diverifikasi);

        $pilihan = collect(app(StatusHasilService::class)->pilihan($calon, $this->panitia))
            ->firstWhere(fn (array $baris) => $baris['status'] === StatusHasil::Diterima);

        $this->assertFalse($pilihan['aktif']);
        $this->assertNotNull($pilihan['alasan']);

        $this->putuskan($this->panitia, $calon, StatusHasil::Diterima)
            ->assertSessionHasErrors('keputusan');

        $this->assertSame(StatusHasil::Diverifikasi, $calon->refresh()->status_hasil);
    }

    public function test_kuota_kosong_berarti_tanpa_batas(): void
    {
        $this->gelombang->forceFill(['kuota' => null])->save();

        for ($i = 0; $i < 3; $i++) {
            $this->daftarDengan(StatusHasil::Diterima);
        }

        $calon = $this->daftarDengan(StatusHasil::Diverifikasi);

        $this->putuskan($this->panitia, $calon, StatusHasil::Diterima)
            ->assertSessionHasNoErrors();

        $this->assertSame(StatusHasil::Diterima, $calon->refresh()->status_hasil);
    }

    public function test_dua_permintaan_berurutan_pada_kuota_satu_hanya_satu_berhasil(): void
    {
        $this->gelombang->forceFill(['kuota' => 1])->save();

        $pertama = $this->daftarDengan(StatusHasil::Diverifikasi);
        $kedua = $this->daftarDengan(StatusHasil::Diverifikasi);

        $this->putuskan($this->panitia, $pertama, StatusHasil::Diterima)
            ->assertSessionHasNoErrors();

        $this->putuskan($this->panitia, $kedua, StatusHasil::Diterima)
            ->assertSessionHasErrors('keputusan');

        $this->assertSame(StatusHasil::Diterima, $pertama->refresh()->status_hasil);
        $this->assertSame(StatusHasil::Diverifikasi, $kedua->refresh()->status_hasil);
    }

    public function test_mengundurkan_diri_membebaskan_kuota(): void
    {
        $this->gelombang->forceFill(['kuota' => 1])->save();

        $diterima = $this->daftarDengan(StatusHasil::Diterima);
        $calon = $this->daftarDengan(StatusHasil::Diverifikasi);

        $this->putuskan($this->panitia, $calon, StatusHasil::Diterima)
            ->assertSessionHasErrors('keputusan');

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.mengundurkan-diri', $diterima->uuid))
            ->assertSessionHasNoErrors();

        $this->assertSame(StatusMurid::MengundurkanDiri, $diterima->refresh()->status_murid);

        $this->putuskan($this->panitia, $calon, StatusHasil::Diterima)
            ->assertSessionHasNoErrors();
    }

    public function test_setiap_perubahan_status_menulis_riwayat_dan_log(): void
    {
        $pendaftaran = $this->terkirim();

        $this->putuskan($this->panitia, $pendaftaran, StatusHasil::Diverifikasi)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('riwayat_status', [
            'pendaftaran_id' => $pendaftaran->id,
            'jenis' => 'hasil',
            'dari' => 'Baru',
            'ke' => 'Diverifikasi',
            'oleh' => $this->panitia->id,
        ]);

        $this->assertDatabaseHas('log_aktivitas', [
            'aksi' => 'ubah_status_hasil',
            'objek' => 'Pendaftaran',
            'objek_id' => $pendaftaran->id,
            'user_id' => $this->panitia->id,
            'sekolah_id' => $this->sekolah->id,
        ]);
    }
}
