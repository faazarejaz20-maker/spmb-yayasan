<?php

namespace Tests\Feature;

use App\Enums\MetodePembayaran;
use App\Enums\StatusHasil;
use App\Enums\StatusPembayaran;
use App\Models\Pembayaran;
use App\Models\Pengumuman;
use App\Models\RekeningSekolah;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Butir 14: pembayaran daftar ulang hanya untuk pendaftar Diterima yang sudah
 * diumumkan, bukti transfer disimpan privat, dan status hanya ditentukan
 * panitia.
 */
class PembayaranTest extends WalisantriTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->pendaftaran->forceFill([
            'status_hasil' => StatusHasil::Diterima,
            'dikirim_pada' => now()->subDay(),
            'nomor_peserta' => 'SD-2027-0001',
        ])->save();
    }

    private function umumkan(): void
    {
        Pengumuman::create([
            'sekolah_id' => $this->sekolah->id,
            'gelombang_id' => $this->gelombang->id,
            'diumumkan_pada' => now(),
        ]);
    }

    private function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\nBukti transfer\n%%EOF";
    }

    private function bukti(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('bukti.pdf', $this->pdf());
    }

    public function test_halaman_pembayaran_butuh_diumumkan(): void
    {
        $this->actingAs($this->wali)
            ->get('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/pembayaran')
            ->assertNotFound();
    }

    public function test_halaman_pembayaran_menampilkan_rekening_setelah_diumumkan(): void
    {
        $this->umumkan();

        RekeningSekolah::create([
            'sekolah_id' => $this->sekolah->id,
            'bank' => 'BSI',
            'nomor_rekening' => '1234567890',
            'atas_nama' => 'Yayasan Al-Ishlah Insan Mulia',
            'aktif' => true,
        ]);

        $this->actingAs($this->wali)
            ->get('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/pembayaran')
            ->assertOk()
            ->assertSee('BSI')
            ->assertSee('1234567890');
    }

    public function test_unggah_bukti_berhasil_dan_status_menunggu(): void
    {
        $this->umumkan();

        $this->actingAs($this->wali)
            ->post('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/pembayaran', [
                'nominal' => 500000,
                'dibayar_pada' => now()->toDateString(),
                'bukti' => $this->bukti(),
                'status' => StatusPembayaran::Disetujui->value,
                'metode' => MetodePembayaran::Tunai->value,
            ])
            ->assertRedirect(route('walisantri.pendaftaran.pembayaran.index', $this->pendaftaran->uuid));

        $pembayaran = Pembayaran::query()->firstOrFail();

        $this->assertSame(StatusPembayaran::Menunggu, $pembayaran->status);
        $this->assertSame(MetodePembayaran::Transfer, $pembayaran->metode);
        $this->assertSame(500000, (int) $pembayaran->nominal);
        Storage::disk('local')->assertExists($pembayaran->bukti_path);
        $this->assertFileDoesNotExist(public_path($pembayaran->bukti_path));
    }

    public function test_bukti_bukan_pdf_ditolak(): void
    {
        $this->umumkan();

        $this->actingAs($this->wali)
            ->post('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/pembayaran', [
                'nominal' => 500000,
                'dibayar_pada' => now()->toDateString(),
                'bukti' => UploadedFile::fake()->createWithContent('bukti.pdf', 'bukan pdf'),
            ])
            ->assertSessionHasErrors('bukti');

        $this->assertSame(0, Pembayaran::query()->count());
    }

    public function test_nominal_dan_tanggal_wajib_diisi(): void
    {
        $this->umumkan();

        $this->actingAs($this->wali)
            ->post('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/pembayaran', [
                'bukti' => $this->bukti(),
            ])
            ->assertSessionHasErrors(['nominal', 'dibayar_pada']);

        $this->assertSame(0, Pembayaran::query()->count());
    }

    public function test_tanggal_transfer_tidak_boleh_masa_depan(): void
    {
        $this->umumkan();

        $this->actingAs($this->wali)
            ->post('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/pembayaran', [
                'nominal' => 500000,
                'dibayar_pada' => now()->addDay()->toDateString(),
                'bukti' => $this->bukti(),
            ])
            ->assertSessionHasErrors('dibayar_pada');
    }

    public function test_catatan_penolakan_tampil_ke_walisantri(): void
    {
        $this->umumkan();

        Pembayaran::create([
            'pendaftaran_id' => $this->pendaftaran->id,
            'metode' => MetodePembayaran::Transfer,
            'nominal' => 500000,
            'status' => StatusPembayaran::Ditolak,
            'catatan_penolakan' => 'Nominal belum sesuai.',
            'dibayar_pada' => now()->toDateString(),
        ]);

        $this->actingAs($this->wali)
            ->get('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/pembayaran')
            ->assertOk()
            ->assertSee('Nominal belum sesuai.')
            ->assertSee('Ditolak');
    }

    public function test_bukti_hanya_bisa_dilihat_pemilik(): void
    {
        $this->umumkan();

        Storage::disk('local')->put('pembayaran/contoh.pdf', $this->pdf());

        $pembayaran = Pembayaran::create([
            'pendaftaran_id' => $this->pendaftaran->id,
            'metode' => MetodePembayaran::Transfer,
            'nominal' => 500000,
            'bukti_path' => 'pembayaran/contoh.pdf',
            'status' => StatusPembayaran::Menunggu,
            'dibayar_pada' => now()->toDateString(),
        ]);

        $this->actingAs($this->wali)
            ->get('/akun/pembayaran/'.$pembayaran->uuid.'/bukti')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $lain = User::factory()->walisantri()->create();

        $this->actingAs($lain)
            ->get('/akun/pembayaran/'.$pembayaran->uuid.'/bukti')
            ->assertNotFound();
    }

    public function test_pembayaran_tidak_bisa_diunggah_untuk_status_ditolak(): void
    {
        $this->umumkan();
        $this->pendaftaran->forceFill(['status_hasil' => StatusHasil::Ditolak])->save();

        $this->actingAs($this->wali)
            ->post('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/pembayaran', [
                'nominal' => 500000,
                'dibayar_pada' => now()->toDateString(),
                'bukti' => $this->bukti(),
            ])
            ->assertNotFound();

        $this->assertSame(0, Pembayaran::query()->count());
    }
}
