<?php

namespace Tests\Feature\Keamanan;

use Illuminate\Support\Facades\Route;
use Tests\Feature\PanitiaTestCase;

/**
 * Butir 3: super admin dan yayasan tidak melihat data pendaftar per orang.
 *
 * Semua rute GET /super dan /yayasan dijelajahi dengan data uji yang memuat
 * nama anak, NIK, nomor peserta, dan nama walisantri; tidak satu pun boleh
 * muncul. Rute ke data pendaftar juga tidak boleh ada di daftar rute.
 */
class SuperYayasanTidakLihatPendaftarTest extends PanitiaTestCase
{
    private const RAHASIA = [
        'Aisyah Nur Fadhilah',
        'Budi Bedah SMP',
        '3307015204190001',
        '3307010101010002',
        'Wali Utama',
        'Wali Bedah B',
    ];

    private \App\Models\User $super;

    private \App\Models\User $yayasan;

    private \App\Models\Pendaftaran $pendaftaranB;

    protected function setUp(): void
    {
        parent::setUp();

        // Dua pendaftaran terkirim di dua sekolah, lengkap dengan nama dan
        // NIK yang dikenali, supaya kebocoran apa pun terlihat.
        $this->terkirim();

        $this->pendaftaranB = $this->pendaftaranSekolahB(status: 'Diterima');
        $this->pendaftaranB->anak->forceFill([
            'nama_lengkap' => 'Budi Bedah SMP',
            'nik' => '3307015204190099',
            'no_kk' => '3307010101010099',
        ])->save();
        $this->pendaftaranB->anak->user->forceFill(['nama' => 'Wali Bedah B'])->save();
        $this->pendaftaranB->refresh();

        $this->super = \App\Models\User::factory()->superAdmin()->create();
        $this->yayasan = \App\Models\User::factory()->yayasan()->create();
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

    private function pastikanTidakBocor(array $rute, \App\Models\User $pengguna): void
    {
        $nomorPeserta = [
            $this->pendaftaran->fresh()->nomor_peserta,
            $this->pendaftaranB->nomor_peserta,
        ];

        foreach ($rute as $rute_) {
            $respons = $this->actingAs($pengguna)->get($rute_);
            $respons->assertOk();

            $isi = (string) $respons->getContent();

            foreach (self::RAHASIA as $teks) {
                $this->assertStringNotContainsString(
                    $teks,
                    $isi,
                    "Teks \"{$teks}\" bocor di {$rute_} untuk peran {$pengguna->peran->value}.",
                );
            }

            $this->assertStringNotContainsString(
                '3307015204190099',
                $isi,
                "NIK bocor di {$rute_}.",
            );

            foreach ($nomorPeserta as $nomor) {
                if (! $nomor) {
                    continue;
                }

                $this->assertStringNotContainsString(
                    (string) $nomor,
                    $isi,
                    "Nomor peserta {$nomor} bocor di {$rute_}.",
                );
            }

            // Tidak ada tautan ke data per orang di panel mana pun.
            $this->assertStringNotContainsString('href="/panitia', $isi);
            $this->assertStringNotContainsString('href="/akun', $isi);
        }
    }

    public function test_super_admin_tidak_melihat_data_pendaftar_di_semua_rutenya(): void
    {
        $this->pastikanTidakBocor($this->ruteSuper(), $this->super);
    }

    public function test_yayasan_tidak_melihat_data_pendaftar_di_semua_rutenya(): void
    {
        $this->pastikanTidakBocor($this->ruteYayasan(), $this->yayasan);
    }

    public function test_tidak_ada_rute_super_atau_yayasan_ke_data_pendaftar(): void
    {
        $dicek = 0;

        foreach (Route::getRoutes() as $rute) {
            $uri = $rute->uri();

            if (! str_starts_with($uri, 'super') && ! str_starts_with($uri, 'yayasan')) {
                continue;
            }

            $dicek++;

            foreach ([
                'pendaftar',
                'berkas',
                'pembayaran',
                'keputusan',
                'daftar-ulang',
                'mengundurkan',
                'offline',
                'pindah-anak',
                'orang-tua',
            ] as $terlarang) {
                $this->assertStringNotContainsString(
                    $terlarang,
                    $uri,
                    "Rute {$uri} menunjuk ke data pendaftar.",
                );
            }
        }

        $this->assertGreaterThan(5, $dicek);
    }
}
