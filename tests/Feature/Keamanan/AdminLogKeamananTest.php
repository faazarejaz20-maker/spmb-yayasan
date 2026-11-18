<?php

namespace Tests\Feature\Keamanan;

use App\Models\LogAktivitas;
use App\Models\User;
use Tests\Feature\PanitiaTestCase;

/**
 * Butir 11: setiap tindakan menulis log_aktivitas; log tidak bisa di-update
 * atau dihapus lewat model; log tidak memuat kata sandi, token, atau nomor
 * rekening utuh.
 */
class AdminLogKeamananTest extends PanitiaTestCase
{
    public function test_setiap_tindakan_pengaturan_menulis_log(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/gelombang', [
            'nama' => 'Gelombang Log',
            'status' => 'draf',
        ])->assertSessionHasNoErrors();

        $this->put('/admin/pengaturan', ['mode_penerimaan' => 'seleksi'])
            ->assertSessionHasNoErrors();

        $this->post('/admin/berkas', [
            'nama' => 'Akta Kelahiran',
            'wajib' => '1',
            'jumlah_lembar' => 2,
            'aktif' => '1',
        ])->assertSessionHasNoErrors();

        $this->post('/admin/rekening', [
            'bank' => 'BRI',
            'nomor_rekening' => '1234567890',
            'atas_nama' => 'SDIT Insan Mulia',
            'aktif' => '1',
        ])->assertSessionHasNoErrors();

        foreach ([
            'buat_gelombang',
            'ubah_mode_penerimaan',
            'buat_jenis_berkas',
            'buat_rekening',
        ] as $aksi) {
            $this->assertDatabaseHas('log_aktivitas', [
                'aksi' => $aksi,
                'sekolah_id' => $this->sekolah->id,
            ]);
        }
    }

    public function test_log_dinonaktifkan_dan_umumkan_hasil_tercatat(): void
    {
        $panitia = User::factory()->panitia($this->sekolah)->create();

        $this->actingAs($this->admin)
            ->post('/admin/panitia/'.$panitia->id.'/nonaktifkan')
            ->assertSessionHas('sukses');

        $this->terkirim();

        $this->actingAs($this->admin)
            ->post('/admin/pengumuman/'.$this->gelombang->id, ['konfirmasi' => 1])
            ->assertSessionHas('sukses');

        $this->assertDatabaseHas('log_aktivitas', ['aksi' => 'nonaktifkan_akun_staf']);
        $this->assertDatabaseHas('log_aktivitas', ['aksi' => 'umumkan_hasil']);
    }

    public function test_log_tidak_bisa_diubah_lewat_model(): void
    {
        $log = LogAktivitas::create(['aksi' => 'uji_kekal']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Log aktivitas hanya boleh ditambah.');

        $log->aksi = 'diubah';
        $log->save();
    }

    public function test_log_tidak_bisa_dihapus_lewat_model(): void
    {
        $log = LogAktivitas::create(['aksi' => 'uji_kekal']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Log aktivitas tidak dapat dihapus.');

        $log->delete();
    }

    public function test_log_tidak_memuat_kata_sandi_token_dan_nomor_rekening_utuh(): void
    {
        $this->actingAs($this->admin);

        // Akun staf dengan kata sandi sementara.
        $respons = $this->post('/admin/panitia', [
            'nama' => 'Panitia Sandi',
            'no_hp' => '081200000011',
        ])->assertRedirect();

        $kataSandi = $respons->getSession()->get('kartu_akun.kata_sandi');

        // Atur ulang kata sandi panitia.
        $target = User::factory()->panitia($this->sekolah)->create();

        $this->post('/admin/panitia/'.$target->id.'/atur-ulang')->assertRedirect();

        // Rekening dengan nomor utuh.
        $this->post('/admin/rekening', [
            'bank' => 'BSI',
            'nomor_rekening' => '999988887777',
            'atas_nama' => 'SDIT Insan Mulia',
            'aktif' => '1',
        ])->assertSessionHasNoErrors();

        $semua = LogAktivitas::query()->get();
        $this->assertGreaterThanOrEqual(3, $semua->count());

        foreach ($semua as $log) {
            $data = (string) $log->data;

            $this->assertStringNotContainsString((string) $kataSandi, $data, 'Kata sandi bocor di log '.$log->aksi);
            $this->assertStringNotContainsString('password', strtolower($data), 'Kata kunci password di log '.$log->aksi);
            $this->assertStringNotContainsString('token', strtolower($data), 'Kata kunci token di log '.$log->aksi);
            $this->assertStringNotContainsString('999988887777', $data, 'Nomor rekening utuh di log '.$log->aksi);
        }

        $logRekening = LogAktivitas::query()->where('aksi', 'buat_rekening')->firstOrFail();
        $this->assertStringContainsString('7777', (string) $logRekening->data);
    }

    public function test_halaman_log_hanya_baca_dan_terurut_terbaru(): void
    {
        $logLama = LogAktivitas::create(['aksi' => 'uji_lama', 'sekolah_id' => $this->sekolah->id]);
        $logBaru = LogAktivitas::create(['aksi' => 'uji_baru', 'sekolah_id' => $this->sekolah->id]);

        $isi = (string) $this->actingAs($this->admin)
            ->get('/admin/log')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('uji_lama', $isi);
        $this->assertStringContainsString('uji_baru', $isi);

        $posisiLama = mb_strpos($isi, 'uji_lama');
        $posisiBaru = mb_strpos($isi, 'uji_baru');

        $this->assertNotFalse($posisiLama);
        $this->assertNotFalse($posisiBaru);
        // Halaman log terurut terbaru dulu: uji_baru muncul sebelum uji_lama.
        $this->assertLessThan($posisiLama, $posisiBaru);
    }
}
