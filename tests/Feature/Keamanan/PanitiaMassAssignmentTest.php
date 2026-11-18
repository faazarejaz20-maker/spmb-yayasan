<?php

namespace Tests\Feature\Keamanan;

use App\Enums\StatusHasil;
use App\Enums\StatusMurid;
use App\Enums\StatusPembayaran;
use App\Models\DaftarUlang;
use App\Models\JenisBerkas;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\PanitiaTestCase;

/**
 * Butir 15: kolom sensitif yang dikirim lewat formulir panitia tidak pernah
 * mengubah nilai sebenarnya. Semua penulisan kolom itu lewat `forceFill()` di
 * service setelah pemeriksaan peran dan sekolah.
 */
class PanitiaMassAssignmentTest extends PanitiaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_formulir_akun_offline_mengabaikan_peran_sekolah_dan_pembuat(): void
    {
        $this->actingAs($this->panitia)
            ->post(route('panitia.offline.akun'), [
                'nama' => 'Wali Baru',
                'no_hp' => '081234500001',
                'peran' => 'admin',
                'sekolah_id' => $this->sekolahB->id,
                'dibuat_oleh' => 99999,
                'aktif' => false,
                'wajib_ganti_password' => false,
            ])
            ->assertSessionHasNoErrors();

        $user = User::query()->where('no_hp', '6281234500001')->firstOrFail();

        $this->assertSame(\App\Enums\PeranPengguna::Walisantri, $user->peran);
        $this->assertNull($user->sekolah_id);
        $this->assertSame($this->panitia->id, (int) $user->dibuat_oleh);
        $this->assertTrue($user->aktif);
        $this->assertTrue($user->wajib_ganti_password);
    }

    public function test_formulir_anak_baru_offline_mengabaikan_kolom_pendaftaran_sensitif(): void
    {
        $this->actingAs($this->panitia)->post(route('panitia.offline.akun'), [
            'nama' => 'Wali Offline',
            'no_hp' => '081234500002',
        ]);

        $wali = User::query()->where('no_hp', '6281234500002')->firstOrFail();
        $sebelum = Pendaftaran::query()->count();

        $this->actingAs($this->panitia)
            ->post(route('panitia.offline.sekolah.simpan'), [
                'gelombang_id' => $this->gelombang->id,
            ])
            ->assertRedirect(route('panitia.offline.anak-baru'));

        $this->actingAs($this->panitia)
            ->put(route('panitia.offline.anak-baru.simpan'), [
                'nama_lengkap' => 'Anak Offline',
                'jenis_kelamin' => 'L',
                'tempat_lahir' => 'Wonosobo',
                'tanggal_lahir' => '2019-06-01',
                'user_id' => 99999,
                'sekolah_id' => $this->sekolahB->id,
                'status_hasil' => StatusHasil::Diterima->value,
                'status_murid' => StatusMurid::Resmi->value,
                'nomor_peserta' => 'SMP-2027-9999',
                'dibuat_oleh' => 99999,
                'dikirim_pada' => now()->toDateTimeString(),
            ])
            ->assertRedirect();

        $this->assertSame($sebelum + 1, Pendaftaran::query()->count());

        $pendaftaran = Pendaftaran::query()->latest('id')->firstOrFail();

        $this->assertSame($this->sekolah->id, (int) $pendaftaran->sekolah_id);
        $this->assertSame($wali->id, (int) $pendaftaran->anak->user_id);
        $this->assertSame($this->panitia->id, (int) $pendaftaran->dibuat_oleh);
        $this->assertSame(StatusHasil::Baru, $pendaftaran->status_hasil);
        $this->assertNull($pendaftaran->status_murid);
        $this->assertNull($pendaftaran->nomor_peserta);
        $this->assertNull($pendaftaran->dikirim_pada);
    }

    public function test_keputusan_mengabaikan_kolom_sensitif_dari_formulir(): void
    {
        $pendaftaran = $this->terkirim();
        $nomorAwal = $pendaftaran->nomor_peserta;
        $pemilikAwal = $pendaftaran->anak->user_id;
        $pembuatAwal = $pendaftaran->dibuat_oleh;

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.keputusan', $pendaftaran->uuid), [
                'status' => StatusHasil::Diverifikasi->value,
                'sekolah_id' => $this->sekolahB->id,
                'user_id' => 99999,
                'nomor_peserta' => 'SMP-2027-9999',
                'dibuat_oleh' => 99999,
                'status_murid' => StatusMurid::MengundurkanDiri->value,
            ])
            ->assertSessionHasNoErrors();

        $segar = $pendaftaran->refresh();

        $this->assertSame(StatusHasil::Diverifikasi, $segar->status_hasil);
        $this->assertSame($this->sekolah->id, (int) $segar->sekolah_id);
        $this->assertSame($nomorAwal, $segar->nomor_peserta);
        $this->assertSame((int) $pembuatAwal, (int) $segar->dibuat_oleh);
        $this->assertSame($this->panitia->id, (int) $segar->diputuskan_oleh);
        $this->assertNull($segar->status_murid);
        $this->assertSame($pemilikAwal, (int) $segar->anak->user_id);
    }

    public function test_pembayaran_tunai_mengabaikan_status_dan_pemroses_dari_formulir(): void
    {
        $pendaftaran = $this->terkirim();

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.pembayaran-tunai', $pendaftaran->uuid), [
                'nominal' => 175000,
                'dibayar_pada' => now()->subDay()->toDateString(),
                'status' => StatusPembayaran::Ditolak->value,
                'diproses_oleh' => 99999,
                'pendaftaran_id' => 99999,
                'sekolah_id' => $this->sekolahB->id,
            ])
            ->assertSessionHasNoErrors();

        $bayar = Pembayaran::query()->where('pendaftaran_id', $pendaftaran->id)->firstOrFail();

        $this->assertSame(StatusPembayaran::Disetujui, $bayar->status);
        $this->assertSame($this->panitia->id, (int) $bayar->diproses_oleh);
        $this->assertSame($pendaftaran->id, (int) $bayar->pendaftaran_id);
    }

    public function test_pemeriksaan_berkas_mengabaikan_pemeriksa_dari_formulir(): void
    {
        $pendaftaran = $this->terkirim();
        $jenis = JenisBerkas::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'wajib' => true,
        ]);
        $berkas = $this->lampirkanBerkas($pendaftaran, $jenis);

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.berkas.periksa', [
                'pendaftaran' => $pendaftaran->uuid,
                'berkas' => $berkas->uuid,
            ]), [
                'status' => 'sesuai',
                'diperiksa_oleh' => 99999,
                'status_berkas' => 'perlu_perbaikan',
                'pendaftaran_id' => 99999,
            ])
            ->assertSessionHasNoErrors();

        $segar = $berkas->refresh();

        $this->assertSame($this->panitia->id, (int) $segar->diperiksa_oleh);
        $this->assertSame($pendaftaran->id, (int) $segar->pendaftaran_id);
    }

    public function test_daftar_ulang_mengabaikan_pencatat_dan_status_dari_formulir(): void
    {
        $pendaftaran = $this->terkirim();
        $pendaftaran->forceFill(['status_hasil' => StatusHasil::Diterima])->save();
        $this->pembayaran($pendaftaran, StatusPembayaran::Disetujui);

        $this->actingAs($this->panitia)
            ->put(route('panitia.pendaftar.daftar-ulang', $pendaftaran->uuid), [
                'hadir' => 1,
                'selesai' => 1,
                'dicatat_oleh' => 99999,
                'status_murid' => StatusMurid::MengundurkanDiri->value,
                'pendaftaran_id' => 99999,
            ])
            ->assertSessionHasNoErrors();

        $baris = DaftarUlang::query()->where('pendaftaran_id', $pendaftaran->id)->firstOrFail();

        $this->assertSame($this->panitia->id, (int) $baris->dicatat_oleh);
        $this->assertSame($pendaftaran->id, (int) $baris->pendaftaran_id);
        $this->assertSame(StatusMurid::Resmi, $pendaftaran->refresh()->status_murid);
    }
}
