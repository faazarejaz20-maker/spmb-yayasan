<?php

namespace Tests\Feature\Keamanan;

use App\Models\Anak;
use App\Models\Berkas;
use App\Models\Gelombang;
use App\Models\JenisBerkas;
use App\Models\Pendaftaran;
use App\Models\Sekolah;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Membuktikan scope `MilikSekolah` membatasi data pendaftar pada sekolah
 * milik akun panitia/admin, dan tidak aktif untuk tamu maupun konsol.
 */
class IsolasiSekolahTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolahA;

    private Sekolah $sekolahB;

    private Pendaftaran $pendaftaranA;

    private Pendaftaran $pendaftaranB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sekolahA = Sekolah::factory()->create(['kode' => 'SDA']);
        $this->sekolahB = Sekolah::factory()->create(['kode' => 'SDB']);

        $tahun = TahunAjaran::factory()->create();

        $gelombangA = Gelombang::factory()->create([
            'sekolah_id' => $this->sekolahA->id,
            'tahun_ajaran_id' => $tahun->id,
        ]);

        $gelombangB = Gelombang::factory()->create([
            'sekolah_id' => $this->sekolahB->id,
            'tahun_ajaran_id' => $tahun->id,
        ]);

        $anakA = Anak::factory()->create();
        $anakB = Anak::factory()->create();

        $this->pendaftaranA = Pendaftaran::factory()->create([
            'anak_id' => $anakA->id,
            'sekolah_id' => $this->sekolahA->id,
            'gelombang_id' => $gelombangA->id,
        ]);

        $this->pendaftaranB = Pendaftaran::factory()->create([
            'anak_id' => $anakB->id,
            'sekolah_id' => $this->sekolahB->id,
            'gelombang_id' => $gelombangB->id,
        ]);
    }

    public function test_panitia_hanya_melihat_pendaftaran_sekolahnya(): void
    {
        $panitiaA = User::factory()->panitia($this->sekolahA)->create();

        $this->actingAs($panitiaA);

        $id = Pendaftaran::query()->pluck('id')->all();

        $this->assertSame([$this->pendaftaranA->id], $id);
    }

    public function test_admin_hanya_melihat_pendaftaran_sekolahnya(): void
    {
        $adminB = User::factory()->admin($this->sekolahB)->create();

        $this->actingAs($adminB);

        $id = Pendaftaran::query()->pluck('id')->all();

        $this->assertSame([$this->pendaftaranB->id], $id);
    }

    public function test_berkas_dibatasi_lewat_relasi_pendaftaran(): void
    {
        $jenis = JenisBerkas::factory()->create(['sekolah_id' => $this->sekolahA->id]);

        $berkasA = Berkas::create([
            'pendaftaran_id' => $this->pendaftaranA->id,
            'jenis_berkas_id' => $jenis->id,
        ]);

        $jenisB = JenisBerkas::factory()->create(['sekolah_id' => $this->sekolahB->id]);

        $berkasB = Berkas::create([
            'pendaftaran_id' => $this->pendaftaranB->id,
            'jenis_berkas_id' => $jenisB->id,
        ]);

        $this->actingAs(User::factory()->panitia($this->sekolahA)->create());

        $this->assertEqualsCanonicalizing([$berkasA->id], Berkas::query()->pluck('id')->all());

        $this->actingAs(User::factory()->panitia($this->sekolahB)->create());

        $this->assertEqualsCanonicalizing([$berkasB->id], Berkas::query()->pluck('id')->all());
    }

    public function test_tanpa_pengguna_scope_tidak_aktif(): void
    {
        // Kondisi konsol/antrean: tidak ada pengguna masuk.
        $this->assertNull(auth()->user());

        $this->assertEqualsCanonicalizing(
            [$this->pendaftaranA->id, $this->pendaftaranB->id],
            Pendaftaran::query()->pluck('id')->all()
        );
    }

    public function test_walisantri_tidak_terkena_scope_sekolah(): void
    {
        $wali = User::factory()->walisantri()->create();

        $this->actingAs($wali);

        $this->assertEqualsCanonicalizing(
            [$this->pendaftaranA->id, $this->pendaftaranB->id],
            Pendaftaran::query()->pluck('id')->all()
        );
    }

    public function test_scope_tanpa_sekolah_membuka_semua_baris(): void
    {
        $panitiaA = User::factory()->panitia($this->sekolahA)->create();

        $this->actingAs($panitiaA);

        $this->assertEqualsCanonicalizing(
            [$this->pendaftaranA->id, $this->pendaftaranB->id],
            Pendaftaran::query()->tanpaSekolah()->pluck('id')->all()
        );
    }
}
