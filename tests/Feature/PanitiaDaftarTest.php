<?php

namespace Tests\Feature;

use App\Enums\StatusHasil;
use App\Enums\StatusPembayaran;

/**
 * Butir 4: pencarian, penyaring, daftar putih urutan, NIK tidak tampil, dan
 * wildcard pencarian diperlakukan literal.
 */
class PanitiaDaftarTest extends PanitiaTestCase
{
    private function anakDenganNama(string $nama, ?string $status = null): \App\Models\Pendaftaran
    {
        $wali = \App\Models\User::factory()->walisantri()->create();
        $anak = \App\Models\Anak::factory()->create([
            'user_id' => $wali->id,
            'nama_lengkap' => $nama,
        ]);

        $pendaftaran = \App\Models\Pendaftaran::factory()->create([
            'anak_id' => $anak->id,
            'sekolah_id' => $this->sekolah->id,
            'gelombang_id' => $this->gelombang->id,
        ]);

        $pendaftaran->forceFill([
            'status_hasil' => $status ?? StatusHasil::Baru,
            'dikirim_pada' => now()->subHours(2),
        ])->save();

        $pendaftaran->forceFill([
            'nomor_peserta' => 'SD-2027-'.str_pad((string) $pendaftaran->id, 4, '0', STR_PAD_LEFT),
        ])->save();

        return $pendaftaran->refresh();
    }

    public function test_pencarian_nama_dan_nomor_peserta(): void
    {
        $cari = $this->anakDenganNama('Zahra Pencarian Unik');

        $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.index', ['cari' => 'Pencarian Unik']))
            ->assertOk()
            ->assertSee('Zahra Pencarian Unik')
            ->assertDontSee($this->anak->nama_lengkap);

        $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.index', ['cari' => $cari->nomor_peserta]))
            ->assertOk()
            ->assertSee('Zahra Pencarian Unik');
    }

    public function test_wildcard_pencarian_diperlakukan_literal(): void
    {
        $this->anakDenganNama('Anak Persen 100% Spesial');
        $this->anakDenganNama('Anak Biasa Tanpa Simbol');

        $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.index', ['cari' => '%']))
            ->assertOk()
            ->assertSee('Anak Persen 100% Spesial')
            ->assertDontSee('Anak Biasa Tanpa Simbol');

        $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.index', ['cari' => '_']))
            ->assertOk()
            ->assertDontSee('Anak Persen 100% Spesial');
    }

    public function test_penyaring_status_dan_pembayaran(): void
    {
        $baru = $this->anakDenganNama('Anak Status Baru', StatusHasil::Baru->value);
        $diterima = $this->anakDenganNama('Anak Status Diterima', StatusHasil::Diterima->value);
        $this->anakDenganNama('Anak Tanpa Pembayaran', StatusHasil::Baru->value);

        $this->pembayaran($baru, StatusPembayaran::Menunggu);
        $this->pembayaran($diterima, StatusPembayaran::Disetujui);

        $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.index', ['status' => StatusHasil::Diterima->value]))
            ->assertOk()
            ->assertSee('Anak Status Diterima')
            ->assertDontSee('Anak Status Baru');

        $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.index', ['pembayaran' => 'disetujui']))
            ->assertOk()
            ->assertSee('Anak Status Diterima')
            ->assertDontSee('Anak Status Baru');

        $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.index', ['pembayaran' => 'belum']))
            ->assertOk()
            ->assertSee('Anak Tanpa Pembayaran')
            ->assertDontSee('Anak Status Diterima');
    }

    public function test_urutan_hanya_menerima_kolom_daftar_putih(): void
    {
        $this->terkirim();

        foreach (['nama', 'nomor_peserta', 'dikirim_pada', 'status_hasil', 'password', 'sekolah_id', '1;drop table users'] as $kolom) {
            $this->actingAs($this->panitia)
                ->get(route('panitia.pendaftar.index', ['urut' => $kolom, 'arah' => 'asc']))
                ->assertOk();
        }
    }

    public function test_nik_tidak_pernah_tampil_di_daftar(): void
    {
        $this->terkirim();

        $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.index'))
            ->assertOk()
            ->assertDontSee($this->anak->nik);
    }
}
