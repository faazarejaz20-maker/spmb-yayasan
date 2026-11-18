<?php

namespace Tests\Feature;

use App\Enums\MetodePembayaran;
use App\Enums\StatusHasil;
use App\Enums\StatusPembayaran;
use App\Models\Anak;
use App\Models\Gelombang;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\Sekolah;
use App\Models\TahunAjaran;
use App\Models\User;

/**
 * Dasar bersama tes Tahap 3: dua sekolah dengan gelombang dibuka dan akun
 * panitia/admin untuk masing-masing sekolah.
 */
abstract class PanitiaTestCase extends WalisantriTestCase
{
    protected Sekolah $sekolahB;

    protected Gelombang $gelombangB;

    protected User $panitia;

    protected User $admin;

    protected User $panitiaB;

    protected function setUp(): void
    {
        parent::setUp();

        $tahunB = TahunAjaran::factory()->create(['nama' => '2027/2028']);

        $this->sekolahB = Sekolah::factory()->create([
            'kode' => 'SMP',
            'nama' => 'SMPIT Insan Mulia Wonosobo',
            'jenjang' => 'SMP',
        ]);

        $this->gelombangB = Gelombang::factory()->create([
            'sekolah_id' => $this->sekolahB->id,
            'tahun_ajaran_id' => $tahunB->id,
            'nama' => 'Gelombang 1',
            'status' => 'dibuka',
            'tanggal_buka' => now()->subDays(5)->toDateString(),
            'tanggal_tutup' => now()->addDays(30)->toDateString(),
            'kuota' => 20,
        ]);

        $this->panitia = User::factory()->panitia($this->sekolah)->create();
        $this->admin = User::factory()->admin($this->sekolah)->create();
        $this->panitiaB = User::factory()->panitia($this->sekolahB)->create();
    }

    /**
     * Kirim pendaftaran bawaan supaya muncul di panel panitia.
     */
    protected function terkirim(?Pendaftaran $pendaftaran = null): Pendaftaran
    {
        $pendaftaran ??= $this->pendaftaran;

        if ($pendaftaran->masihDraf()) {
            $pendaftaran->forceFill([
                'dikirim_pada' => now()->subHour(),
            ])->save();

            $pendaftaran->forceFill([
                'nomor_peserta' => $pendaftaran->nomor_peserta
                    ?? 'SD-2027-'.str_pad((string) $pendaftaran->id, 4, '0', STR_PAD_LEFT),
            ])->save();
        }

        return $pendaftaran->refresh();
    }

    protected function pendaftaranSekolahB(?User $wali = null, string $status = 'Baru'): Pendaftaran
    {
        $wali ??= User::factory()->walisantri()->create();

        $anak = Anak::factory()->create(['user_id' => $wali->id]);

        $pendaftaran = Pendaftaran::factory()->create([
            'anak_id' => $anak->id,
            'sekolah_id' => $this->sekolahB->id,
            'gelombang_id' => $this->gelombangB->id,
            'dibuat_oleh' => $wali->id,
        ]);

        $pendaftaran->forceFill([
            'status_hasil' => $status,
            'dikirim_pada' => now()->subHour(),
        ])->save();

        $pendaftaran->forceFill([
            'nomor_peserta' => 'SMP-2027-'.str_pad((string) $pendaftaran->id, 4, '0', STR_PAD_LEFT),
        ])->save();

        return $pendaftaran->refresh();
    }

    protected function aturStatus(Pendaftaran $pendaftaran, StatusHasil $status): Pendaftaran
    {
        $pendaftaran->forceFill(['status_hasil' => $status])->save();

        return $pendaftaran->refresh();
    }

    protected function pembayaran(
        Pendaftaran $pendaftaran,
        StatusPembayaran $status = StatusPembayaran::Menunggu,
        int $nominal = 150000,
        MetodePembayaran $metode = MetodePembayaran::Transfer,
    ): Pembayaran {
        return Pembayaran::create([
            'pendaftaran_id' => $pendaftaran->id,
            'metode' => $metode,
            'nominal' => $nominal,
            'bukti_path' => $metode === MetodePembayaran::Transfer ? 'pembayaran/contoh/bukti.pdf' : null,
            'status' => $status,
            'dibayar_pada' => now()->subDay(),
        ]);
    }
}
