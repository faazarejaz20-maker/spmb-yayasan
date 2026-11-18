<?php

namespace Tests\Feature\Keamanan;

use App\Models\Anak;
use App\Models\Pendaftaran;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\PanitiaTestCase;

/**
 * Tahap 6 butir 9: jumlah query daftar pendaftar, rekap, dan log konstan
 * terhadap jumlah data (tidak ada N+1). Strict mode Laravel juga akan
 * melempar galat bila ada relasi yang dimuat malas.
 */
class QueryKonstanTest extends PanitiaTestCase
{
    /**
     * Hitung jumlah query satu permintaan. Permintaan pemanasan dikirim lebih
     * dulu supaya relasi akun (mis. sekolah pada pengguna) sudah termuat dan
     * tidak ikut terhitung sebagai selisih antara dua pengukuran.
     */
    private function jumlahQuery(callable $aksi): int
    {
        $aksi();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $aksi();

        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $jumlah;
    }

    private function tambahPendaftar(int $jumlah): void
    {
        for ($i = 0; $i < $jumlah; $i++) {
            $wali = User::factory()->walisantri()->create();
            $anak = Anak::factory()->create(['user_id' => $wali->id]);

            $pendaftaran = Pendaftaran::factory()->create([
                'anak_id' => $anak->id,
                'sekolah_id' => $this->sekolah->id,
                'gelombang_id' => $this->gelombang->id,
                'dibuat_oleh' => $wali->id,
            ]);

            $pendaftaran->forceFill([
                'status_hasil' => 'Baru',
                'dikirim_pada' => now()->subMinutes($i + 1),
                'nomor_peserta' => 'SD-2027-'.str_pad((string) (900 + $i), 4, '0', STR_PAD_LEFT),
            ])->save();
        }
    }

    public function test_query_daftar_pendaftar_konstan(): void
    {
        $this->terkirim();

        $awal = $this->jumlahQuery(function (): void {
            $this->actingAs($this->panitia)->get('/panitia/pendaftar')->assertOk();
        });

        $this->tambahPendaftar(20);

        $akhir = $this->jumlahQuery(function (): void {
            $this->actingAs($this->panitia)->get('/panitia/pendaftar')->assertOk();
        });

        $this->assertSame($awal, $akhir, 'Jumlah query daftar pendaftar bertambah saat data bertambah (N+1).');
    }

    public function test_query_rekap_konstan(): void
    {
        $this->terkirim();

        $awal = $this->jumlahQuery(function (): void {
            $this->actingAs($this->admin)->get('/admin/rekap')->assertOk();
        });

        $this->tambahPendaftar(20);

        $akhir = $this->jumlahQuery(function (): void {
            $this->actingAs($this->admin)->get('/admin/rekap')->assertOk();
        });

        $this->assertSame($awal, $akhir, 'Jumlah query rekap bertambah saat data bertambah (N+1).');
    }

    public function test_query_log_konstan(): void
    {
        $this->terkirim();

        $awal = $this->jumlahQuery(function (): void {
            $this->actingAs($this->admin)->get('/admin/log')->assertOk();
        });

        $this->tambahPendaftar(15);

        $akhir = $this->jumlahQuery(function (): void {
            $this->actingAs($this->admin)->get('/admin/log')->assertOk();
        });

        $this->assertSame($awal, $akhir, 'Jumlah query log bertambah saat data bertambah (N+1).');
    }
}
