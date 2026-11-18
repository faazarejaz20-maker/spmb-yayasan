<?php

namespace Tests\Feature;

use App\Enums\StatusHasil;
use Illuminate\Support\Facades\DB;
use Tests\Feature\PanitiaTestCase;

/**
 * Butir 4: validasi gelombang — tanggal, kuota, dan URL grup WhatsApp.
 * Kuota tidak bisa diturunkan di bawah jumlah Diterima; gelombang yang punya
 * pendaftaran tidak bisa dihapus; kuota dan tautan tidak tampil ke walisantri
 * sebelum syaratnya.
 */
class AdminGelombangTest extends PanitiaTestCase
{
    private function dataSah(array $tambahan = []): array
    {
        return array_merge([
            'nama' => 'Gelombang 2',
            'tanggal_buka' => now()->toDateString(),
            'tanggal_tutup' => now()->addDays(14)->toDateString(),
            'kuota' => 25,
            'status' => 'dibuka',
        ], $tambahan);
    }

    public function test_gelombang_dibuka_wajib_punya_tanggal(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/gelombang', [
                'nama' => 'Gelombang Tanpa Tanggal',
                'status' => 'dibuka',
            ])
            ->assertSessionHasErrors(['tanggal_buka', 'tanggal_tutup']);

        $this->assertDatabaseMissing('gelombang', ['nama' => 'Gelombang Tanpa Tanggal']);
    }

    public function test_tanggal_tutup_tidak_boleh_sebelum_tanggal_buka(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/gelombang', $this->dataSah([
                'tanggal_buka' => '2027-02-10',
                'tanggal_tutup' => '2027-02-01',
            ]))
            ->assertSessionHasErrors(['tanggal_tutup']);
    }

    public function test_kuota_negatif_dan_bukan_angka_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/gelombang', $this->dataSah(['kuota' => -3]))
            ->assertSessionHasErrors(['kuota']);

        $this->actingAs($this->admin)
            ->post('/admin/gelombang', $this->dataSah(['kuota' => 'banyak']))
            ->assertSessionHasErrors(['kuota']);
    }

    public function test_tautan_grup_wa_hanya_chat_whatsapp_https(): void
    {
        foreach ([
            'javascript:alert(1)',
            'https://contoh.test/grup',
            'http://chat.whatsapp.com/Undangan',
            'https://chat.whatsapp.com/ kode spasi',
        ] as $tautan) {
            $this->actingAs($this->admin)
                ->post('/admin/gelombang', $this->dataSah(['tautan_grup_wa' => $tautan]))
                ->assertSessionHasErrors(['tautan_grup_wa']);
        }

        $this->actingAs($this->admin)
            ->post('/admin/gelombang', $this->dataSah([
                'tautan_grup_wa' => 'https://chat.whatsapp.com/UndanganContoh123',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'https://chat.whatsapp.com/UndanganContoh123',
            DB::table('gelombang')->where('nama', 'Gelombang 2')->value('tautan_grup_wa'),
        );
    }

    public function test_kuota_tidak_bisa_diturunkan_di_bawah_jumlah_diterima(): void
    {
        $p = $this->terkirim();
        $this->aturStatus($p, StatusHasil::Diterima);

        $this->actingAs($this->admin)
            ->put('/admin/gelombang/'.$this->gelombang->id, $this->dataSah(['kuota' => 0]))
            ->assertSessionHasErrors(['gelombang']);

        $this->assertSame(20, (int) $this->gelombang->fresh()->kuota);
    }

    public function test_kuota_dapat_dinaikkan_melampaui_diterima(): void
    {
        $p = $this->terkirim();
        $this->aturStatus($p, StatusHasil::Diterima);

        $this->actingAs($this->admin)
            ->put('/admin/gelombang/'.$this->gelombang->id, $this->dataSah(['kuota' => 30]))
            ->assertSessionHasNoErrors();

        $this->assertSame(30, (int) $this->gelombang->fresh()->kuota);
    }

    public function test_gelombang_berpendaftaran_tidak_bisa_dihapus(): void
    {
        $this->terkirim();

        $this->actingAs($this->admin)
            ->delete('/admin/gelombang/'.$this->gelombang->id)
            ->assertSessionHasErrors(['gelombang']);

        $this->assertDatabaseHas('gelombang', ['id' => $this->gelombang->id]);
    }

    public function test_gelombang_sudah_diumumkan_tidak_bisa_dihapus(): void
    {
        \App\Models\Pengumuman::create([
            'sekolah_id' => $this->sekolah->id,
            'gelombang_id' => $this->gelombang->id,
            'diumumkan_pada' => now(),
            'teks_pesan' => 'teks',
        ]);

        $this->actingAs($this->admin)
            ->delete('/admin/gelombang/'.$this->gelombang->id)
            ->assertSessionHasErrors(['gelombang']);

        $this->assertDatabaseHas('gelombang', ['id' => $this->gelombang->id]);
    }

    public function test_gelombang_belum_dipakai_bisa_dihapus(): void
    {
        $gelombang = \App\Models\Gelombang::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'nama' => 'Gelombang Kosong',
            'status' => 'draf',
        ]);

        $this->actingAs($this->admin)
            ->delete('/admin/gelombang/'.$gelombang->id)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('gelombang', ['id' => $gelombang->id]);
    }

    public function test_kuota_dan_tautan_tidak_tampil_ke_walisantri_sebelum_syaratnya(): void
    {
        $this->gelombang->forceFill([
            'tautan_grup_wa' => 'https://chat.whatsapp.com/UndanganRahasia',
        ])->save();

        $this->pendaftaran->forceFill([
            'dikirim_pada' => now()->subDay(),
            'nomor_peserta' => 'SD-2027-0001',
        ])->save();

        $this->actingAs($this->wali);

        foreach ([
            '/akun',
            '/akun/pendaftaran/'.$this->pendaftaran->uuid,
        ] as $rute) {
            $respons = $this->get($rute)->assertOk();

            $isi = (string) $respons->getContent();

            $this->assertStringNotContainsString('UndanganRahasia', $isi);
            $this->assertStringNotContainsString('Kuota', $isi);
        }
    }

    public function test_perubahan_gelombang_dicatat_di_log(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/gelombang', $this->dataSah())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('log_aktivitas', [
            'aksi' => 'buat_gelombang',
            'sekolah_id' => $this->sekolah->id,
        ]);
    }
}
