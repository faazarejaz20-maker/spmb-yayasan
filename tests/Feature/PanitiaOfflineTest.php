<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusHasil;
use App\Models\Anak;
use App\Models\JenisBerkas;
use App\Models\LogAktivitas;
use App\Models\Pendaftaran;
use App\Models\User;
use App\Support\NomorHp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/**
 * Butir 11: pendaftar offline. Panitia membuat akun walisantri dengan kata
 * sandi sementara acak, lalu mengisi seluruh formulir atas nama mereka.
 */
class PanitiaOfflineTest extends PanitiaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function pdf(string $isi = 'Isi dokumen contoh.'): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n".$isi."\n%%EOF";
    }

    private function dataAnak(string $nama = 'Budi Santoso', string $tanggal = '2019-05-01'): array
    {
        return [
            'nama_lengkap' => $nama,
            'nama_panggilan' => 'Budi',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Wonosobo',
            'tanggal_lahir' => $tanggal,
            'nik' => '3307010105190003',
            'no_kk' => '3307010101010004',
            'agama' => 'Islam',
        ];
    }

    /**
     * Buat akun walisantri lewat alur offline. Mengembalikan pengguna dan
     * kata sandi sementara dari sesi.
     *
     * @return array{0: User, 1: string}
     */
    private function buatAkunOffline(string $noHp = '081234567890'): array
    {
        $respons = $this->actingAs($this->panitia)
            ->post(route('panitia.offline.akun'), [
                'nama' => 'Ibu Walisantri',
                'no_hp' => $noHp,
            ]);

        $respons->assertRedirect(route('panitia.akun.kartu'))->assertSessionHasNoErrors();

        /** @var array<string, mixed> $kartu */
        $kartu = $respons->getSession()->get('kartu_akun');

        $this->assertIsArray($kartu);
        $this->assertNotEmpty($kartu['kata_sandi']);

        $user = User::query()
            ->where('no_hp', NomorHp::normalisasi($noHp))
            ->firstOrFail();

        return [$user, (string) $kartu['kata_sandi']];
    }

    private function pilihSekolah(): TestResponse
    {
        return $this->actingAs($this->panitia)
            ->post(route('panitia.offline.sekolah.simpan'), [
                'gelombang_id' => $this->gelombang->id,
            ]);
    }

    private function sampaiAnakBaru(string $nama = 'Budi Santoso', string $tanggal = '2019-05-01'): Pendaftaran
    {
        $this->pilihSekolah()->assertRedirect(route('panitia.offline.anak-baru'));

        $this->actingAs($this->panitia)
            ->put(route('panitia.offline.anak-baru.simpan'), $this->dataAnak($nama, $tanggal))
            ->assertSessionHasNoErrors();

        return Pendaftaran::query()->latest('id')->firstOrFail();
    }

    public function test_kata_sandi_sementara_acak_dan_hanya_tersimpan_sebagai_hash(): void
    {
        [$user, $kataSandi] = $this->buatAkunOffline();

        $this->assertSame(PeranPengguna::Walisantri, $user->peran);
        $this->assertNull($user->sekolah_id);
        $this->assertTrue($user->wajib_ganti_password);
        $this->assertSame($this->panitia->id, (int) $user->dibuat_oleh);
        $this->assertNotSame($kataSandi, $user->password);
        $this->assertTrue(Hash::check($kataSandi, $user->password));

        $this->assertDatabaseMissing('users', ['password' => $kataSandi]);
        $this->assertSame(0, LogAktivitas::query()->where('data', 'like', '%'.$kataSandi.'%')->count());

        $log = storage_path('logs/laravel.log');

        if (is_file($log)) {
            $this->assertStringNotContainsString($kataSandi, (string) file_get_contents($log));
        }
    }

    public function test_kata_sandi_berbeda_untuk_setiap_akun(): void
    {
        [, $pertama] = $this->buatAkunOffline('081111111111');
        [, $kedua] = $this->buatAkunOffline('082222222222');

        $this->assertNotSame($pertama, $kedua);
        $this->assertGreaterThanOrEqual(10, strlen($pertama));
    }

    public function test_kartu_akun_hanya_tampil_sekali(): void
    {
        [, $kataSandi] = $this->buatAkunOffline();

        $pertama = $this->actingAs($this->panitia)->get(route('panitia.akun.kartu'));

        $pertama->assertOk()
            ->assertSee('Ibu Walisantri')
            ->assertSee($kataSandi)
            ->assertSee('Salin kata sandi')
            ->assertSee('Cetak kartu');

        $this->actingAs($this->panitia)
            ->get(route('panitia.akun.kartu'))
            ->assertRedirect(route('panitia.beranda'));
    }

    public function test_pencarian_akun_di_luar_lingkup_memberi_pesan_yang_sama(): void
    {
        [$user] = $this->buatAkunOffline('081234567890');

        $diLuar = $this->actingAs($this->panitiaB)
            ->post(route('panitia.offline.cari'), ['no_hp' => $user->no_hp]);

        $tidakAda = $this->actingAs($this->panitiaB)
            ->post(route('panitia.offline.cari'), ['no_hp' => '089999999999']);

        $diLuar->assertSessionHasErrors('no_hp');
        $tidakAda->assertSessionHasErrors('no_hp');

        $this->assertSame(
            $tidakAda->getSession()->get('errors')->first('no_hp'),
            $diLuar->getSession()->get('errors')->first('no_hp'),
        );
    }

    public function test_alur_lengkap_pendaftaran_offline(): void
    {
        $jenis = JenisBerkas::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nama' => 'Kartu keluarga',
            'wajib' => true,
            'aktif' => true,
        ]);

        $this->buatAkunOffline();

        $this->actingAs($this->panitia)
            ->put(route('panitia.offline.orang-tua.simpan'), [
                'ayah' => [
                    'nama' => 'Bapak Santoso',
                    'no_hp' => '081200000001',
                    'status_hidup' => 'Masih hidup',
                ],
                'ibu' => [
                    'nama' => 'Ibu Santoso',
                    'no_hp' => '081200000002',
                    'status_hidup' => 'Masih hidup',
                ],
            ])
            ->assertRedirect(route('panitia.offline.sekolah'))
            ->assertSessionHasNoErrors();

        $pendaftaran = $this->sampaiAnakBaru();

        $this->actingAs($this->panitia)
            ->put(route('panitia.offline.langkah.simpan', [
                'pendaftaran' => $pendaftaran->uuid,
                'n' => 3,
            ]), ['alamat' => 'Jl. Keluarga No. 2', 'desa' => 'Desa Contoh'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->panitia)
            ->put(route('panitia.offline.langkah.simpan', [
                'pendaftaran' => $pendaftaran->uuid,
                'n' => 4,
            ]), ['tinggi_badan' => 115, 'berat_badan' => 21])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->panitia)
            ->put(route('panitia.offline.langkah.simpan', [
                'pendaftaran' => $pendaftaran->uuid,
                'n' => 5,
            ]), ['asal_sekolah' => 'TK Contoh', 'jenis_asal_sekolah' => 'TK'])
            // Redirect eksplisit agar 500 tidak bisa lolos diam-diam.
            ->assertRedirect(route('panitia.offline.berkas.index', $pendaftaran->uuid))
            ->assertSessionHasNoErrors();

        // Langkah 5 menghitung `lulusan_yayasan` pada pendaftaran.
        $this->assertSame(
            0,
            (int) DB::table('pendaftaran')->where('id', $pendaftaran->id)->value('lulusan_yayasan'),
        );

        $this->actingAs($this->panitia)
            ->post(route('panitia.offline.berkas.simpan', $pendaftaran->uuid), [
                'jenis_berkas_id' => $jenis->id,
                'berkas' => UploadedFile::fake()->createWithContent('kk.pdf', $this->pdf()),
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->panitia)
            ->put(route('panitia.offline.biaya.simpan', $pendaftaran->uuid), [])
            ->assertRedirect(route('panitia.offline.ringkasan', $pendaftaran->uuid))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->panitia)
            ->post(route('panitia.offline.kirim', $pendaftaran->uuid), [
                'pernyataan_data' => 1,
                'pernyataan_privasi' => 1,
            ])
            ->assertRedirect(route('panitia.pendaftar.detail', $pendaftaran->uuid))
            ->assertSessionHasNoErrors();

        $segar = $pendaftaran->refresh();

        $this->assertNotNull($segar->dikirim_pada);
        $this->assertSame(StatusHasil::Baru, $segar->status_hasil);
        $this->assertMatchesRegularExpression('/^SD-2027-\d{4}$/', (string) $segar->nomor_peserta);
        $this->assertSame($this->panitia->id, (int) $segar->dibuat_oleh);

        $this->assertNull(session('offline_user_id'));
        $this->assertNull(session('pendaftaran_baru'));
        $this->assertNull(session('kartu_akun'));

        $this->assertDatabaseHas('log_aktivitas', [
            'aksi' => 'konfirmasi_lisan_offline',
            'objek' => 'Pendaftaran',
            'objek_id' => $segar->id,
            'user_id' => $this->panitia->id,
            'sekolah_id' => $this->sekolah->id,
        ]);
    }

    public function test_anak_ganda_ditolak_saat_pendaftaran_offline_kedua(): void
    {
        $this->buatAkunOffline();
        $pendaftaran = $this->sampaiAnakBaru('Budi Santoso', '2019-05-01');

        $pendaftaran->forceFill([
            'dikirim_pada' => now(),
            'status_hasil' => StatusHasil::Baru,
        ])->save();

        // Akun kedua, anak dengan nama dan tanggal lahir yang sama.
        $this->actingAs($this->panitia)->post(route('panitia.offline.akun'), [
            'nama' => 'Ibu Lain',
            'no_hp' => '089876543210',
        ])->assertRedirect(route('panitia.akun.kartu'));

        $this->pilihSekolah()->assertRedirect(route('panitia.offline.anak-baru'));

        $this->actingAs($this->panitia)
            ->put(route('panitia.offline.anak-baru.simpan'), $this->dataAnak('  budi   santoso ', '2019-05-01'))
            ->assertSessionHasErrors('data_ganda');

        $this->assertSame(1, Anak::query()->where('nama_lengkap', 'Budi Santoso')->count());
    }
}
