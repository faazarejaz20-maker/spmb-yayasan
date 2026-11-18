<?php

namespace Tests\Feature;

use App\Enums\HubunganOrangTua;
use App\Models\Anak;
use App\Models\Berkas;
use App\Models\Gelombang;
use App\Models\JenisBerkas;
use App\Models\OrangTua;
use App\Models\Pendaftaran;
use App\Models\RincianBiaya;
use App\Models\Sekolah;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Dasar bersama tes Tahap 2: satu sekolah SD dengan gelombang dibuka, satu
 * walisantri, satu anak, dan satu pendaftaran draf.
 */
abstract class WalisantriTestCase extends TestCase
{
    use RefreshDatabase;

    protected TahunAjaran $tahun;

    protected Sekolah $sekolah;

    protected Gelombang $gelombang;

    protected User $wali;

    protected Anak $anak;

    protected Pendaftaran $pendaftaran;

    protected function setUp(): void
    {
        parent::setUp();

        // Semua unggahan dalam tes memakai disk palsu, supaya berkas uji tidak
        // menumpuk di storage/app/private pada mesin pengembang.
        Storage::fake('local');

        $this->tahun = TahunAjaran::factory()->create(['nama' => '2027/2028']);

        $this->sekolah = Sekolah::factory()->create([
            'kode' => 'SD',
            'nama' => 'SDIT Insan Mulia Wonosobo',
            'jenjang' => 'SD',
        ]);

        $this->gelombang = Gelombang::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'nama' => 'Gelombang 1',
            'status' => 'dibuka',
            'tanggal_buka' => now()->subDays(5)->toDateString(),
            'tanggal_tutup' => now()->addDays(30)->toDateString(),
            'kuota' => 20,
        ]);

        $this->wali = User::factory()->walisantri()->create(['nama' => 'Wali Utama']);

        $this->anak = Anak::factory()->create([
            'user_id' => $this->wali->id,
            'nama_lengkap' => 'Aisyah Nur Fadhilah',
            'nama_panggilan' => 'Aisyah',
            'jenis_kelamin' => 'P',
            'tempat_lahir' => 'Wonosobo',
            'tanggal_lahir' => '2019-04-12',
            'nik' => '3307015204190001',
            'no_kk' => '3307010101010002',
            'alamat' => 'Jl. Contoh No. 1',
            'desa' => 'Desa Contoh',
            'kecamatan' => 'Kecamatan Contoh',
            'kabupaten' => 'Wonosobo',
            'provinsi' => 'Jawa Tengah',
        ]);

        $this->pendaftaran = Pendaftaran::factory()->create([
            'anak_id' => $this->anak->id,
            'sekolah_id' => $this->sekolah->id,
            'gelombang_id' => $this->gelombang->id,
            'dibuat_oleh' => $this->wali->id,
        ]);
    }

    protected function lengkapiOrangTua(?User $wali = null): void
    {
        $wali ??= $this->wali;

        OrangTua::firstOrCreate(
            ['user_id' => $wali->id, 'hubungan' => HubunganOrangTua::Ayah],
            [
                'nama' => 'Bapak Contoh',
                'no_hp' => '628111111111',
                'status_hidup' => 'Masih hidup',
            ],
        );
    }

    protected function buatJenisBerkas(bool $wajib = true, ?Sekolah $sekolah = null): JenisBerkas
    {
        return JenisBerkas::factory()->create([
            'sekolah_id' => ($sekolah ?? $this->sekolah)->id,
            'wajib' => $wajib,
        ]);
    }

    protected function lampirkanBerkas(Pendaftaran $pendaftaran, ?JenisBerkas $jenis = null): Berkas
    {
        $jenis ??= JenisBerkas::query()
            ->where('sekolah_id', $pendaftaran->sekolah_id)
            ->where('wajib', true)
            ->where('aktif', true)
            ->orderBy('id')
            ->first()
            ?? JenisBerkas::factory()->create([
                'sekolah_id' => $pendaftaran->sekolah_id,
                'wajib' => true,
            ]);

        return Berkas::create([
            'pendaftaran_id' => $pendaftaran->id,
            'jenis_berkas_id' => $jenis->id,
            'path' => 'berkas/'.Str::uuid().'/contoh.pdf',
        ]);
    }

    protected function buatRincianBiaya(int $nominal = 50000, string $tipe = 'tetap'): RincianBiaya
    {
        return RincianBiaya::create([
            'sekolah_id' => $this->sekolah->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'kelompok' => 'Biaya',
            'nama' => 'Biaya '.$tipe.' '.$nominal,
            'nominal' => $nominal,
            'tipe' => $tipe,
            'urutan' => 0,
        ]);
    }

    /**
     * Lengkapi semua prasyarat kirim (orang tua + berkas wajib).
     */
    protected function siapkanKirim(Pendaftaran $pendaftaran): void
    {
        $this->lengkapiOrangTua();

        $this->lampirkanBerkas($pendaftaran);
    }

    /**
     * Data formulir langkah 2 yang sah.
     *
     * @param  array<string, mixed>  $tambahan
     * @return array<string, mixed>
     */
    protected function dataLangkah2(array $tambahan = []): array
    {
        return array_merge([
            'nama_lengkap' => $this->anak->nama_lengkap,
            'nama_panggilan' => $this->anak->nama_panggilan,
            'jenis_kelamin' => 'P',
            'tempat_lahir' => 'Wonosobo',
            'tanggal_lahir' => '2019-04-12',
            'nik' => '3307015204190001',
            'no_kk' => '3307010101010002',
            'agama' => 'Islam',
        ], $tambahan);
    }

    /**
     * Buat pendaftaran terkirim untuk pengguna dan anak lain.
     */
    protected function buatPendaftaranTerkirim(
        User $wali,
        ?Anak $anak = null,
        ?Sekolah $sekolah = null,
        ?Gelombang $gelombang = null,
        string $status = 'Baru',
    ): Pendaftaran {
        $sekolah ??= $this->sekolah;
        $gelombang ??= $this->gelombang;

        $anak ??= Anak::factory()->create([
            'user_id' => $wali->id,
            'tanggal_lahir' => '2019-04-12',
        ]);

        $pendaftaran = new Pendaftaran([
            'anak_id' => $anak->id,
            'sekolah_id' => $sekolah->id,
            'gelombang_id' => $gelombang->id,
            'dibuat_oleh' => $wali->id,
        ]);

        $pendaftaran->forceFill([
            'status_hasil' => $status,
            'dikirim_pada' => now()->subDay(),
        ])->save();

        $pendaftaran->forceFill([
            'nomor_peserta' => 'SD-2027-'.str_pad((string) $pendaftaran->id, 4, '0', STR_PAD_LEFT),
        ])->save();

        return $pendaftaran->refresh();
    }
}
