<?php

namespace App\Http\Controllers\Panitia;

use App\Enums\HubunganOrangTua;
use App\Enums\PeranPengguna;
use App\Enums\StatusBerkas;
use App\Exceptions\AturanBisnisException;
use App\Exceptions\BerkasTidakValidException;
use App\Exceptions\DataGandaException;
use App\Exceptions\SyaratKirimException;
use App\Http\Controllers\Controller;
use App\Http\Requests\BiayaPendaftaranRequest;
use App\Http\Requests\KirimPendaftaranRequest;
use App\Http\Requests\LangkahPendaftaranRequest;
use App\Http\Requests\OfflineAkunRequest;
use App\Http\Requests\OfflineAnakBaruRequest;
use App\Http\Requests\OfflineCariRequest;
use App\Http\Requests\OfflineSekolahRequest;
use App\Http\Requests\OrangTuaRequest;
use App\Http\Requests\UnggahBerkasRequest;
use App\Models\Berkas;
use App\Models\Gelombang;
use App\Models\JenisBerkas;
use App\Models\OrangTua;
use App\Models\Pendaftaran;
use App\Models\RincianBiaya;
use App\Models\Sekolah;
use App\Models\User;
use App\Policies\AkunWalisantriPolicy;
use App\Services\AkunWalisantriService;
use App\Services\AturanIsian;
use App\Services\BerkasService;
use App\Services\PendaftaranService;
use App\Support\Catat;
use App\Support\DataOrangTua;
use App\Support\NomorHp;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Pendaftar offline: panitia membuat atau mencari akun walisantri, lalu
 * mengisi seluruh formulir atas nama mereka memakai service dan tampilan
 * yang sama dengan tahap 2.
 */
class OfflineController extends Controller
{
    private const JUDUL_BAGIAN = [
        'identitas' => 'Data anak',
        'alamat' => 'Alamat',
        'kesehatan' => 'Kesehatan',
        'asal_sekolah' => 'Asal sekolah',
    ];

    private const BANNER = 'Diisi oleh panitia atas nama walisantri.';

    public function __construct(
        private readonly AturanIsian $aturanIsian,
        private readonly PendaftaranService $pendaftaranService,
        private readonly BerkasService $berkasService,
        private readonly AkunWalisantriService $akunService,
        private readonly AkunWalisantriPolicy $akunPolicy,
    ) {}

    public function index(Request $request): View
    {
        return view('panitia.offline.index', [
            'akun' => $this->akunOffline($request),
        ]);
    }

    public function buatAkun(OfflineAkunRequest $request): RedirectResponse
    {
        try {
            $hasil = $this->akunService->buatAkun(
                $request->user(),
                (string) $request->input('nama'),
                (string) $request->input('no_hp'),
                $request->input('email'),
            );
        } catch (AturanBisnisException $galat) {
            return back()->withInput()->withErrors(['akun' => $galat->getMessage()]);
        }

        $request->session()->put('offline_user_id', $hasil['user']->id);
        $request->session()->put('kartu_akun', [
            'nama' => $hasil['user']->nama,
            'identitas' => NomorHp::tampil($hasil['user']->no_hp) ?: (string) $hasil['user']->email,
            'kata_sandi' => $hasil['kata_sandi'],
            'sekolah' => $request->user()->sekolah?->nama ?? config('app.name'),
            'lanjut' => true,
        ]);

        return redirect()->route('panitia.akun.kartu');
    }

    public function cari(OfflineCariRequest $request): RedirectResponse
    {
        $akun = $this->akunService->cari($request->user(), (string) $request->input('no_hp'));

        if (! $akun) {
            return back()->withInput()->withErrors([
                'no_hp' => 'Akun tidak ditemukan. Buat akun baru, atau minta walisantri masuk dan mendaftar sendiri.',
            ]);
        }

        $request->session()->put('offline_user_id', $akun->id);

        return redirect()
            ->route('panitia.offline.sekolah')
            ->with('sukses', 'Akun ditemukan: '.$akun->nama.'.');
    }

    public function formOrangTua(Request $request): View|RedirectResponse
    {
        $akun = $this->akunOffline($request);

        if (! $akun) {
            return $this->pesanAkunBelumDipilih();
        }

        $data = $akun->orangTua()
            ->get()
            ->keyBy(fn (OrangTua $baris) => $baris->hubungan->value);

        return view('walisantri.orang-tua', [
            'layout' => 'layouts.panel',
            'banner' => 'Diisi oleh panitia atas nama '.$akun->nama.'.',
            'data' => $data,
            'hubungan' => HubunganOrangTua::cases(),
            'statusHidup' => OrangTuaRequest::STATUS_HIDUP,
            'aksiSimpan' => route('panitia.offline.orang-tua.simpan'),
            'tautanKembali' => route('panitia.offline.sekolah'),
            'labelKembali' => 'Lanjut pilih sekolah',
        ]);
    }

    public function simpanOrangTua(OrangTuaRequest $request): RedirectResponse
    {
        $akun = $this->akunOffline($request);

        if (! $akun) {
            return $this->pesanAkunBelumDipilih();
        }

        $data = $request->dataTernormalisasi();

        DB::transaction(function () use ($akun, $data) {
            foreach (HubunganOrangTua::cases() as $hubungan) {
                DataOrangTua::simpan($akun->id, $hubungan, $data[$hubungan->value] ?? []);
            }
        });

        Catat::aksi('ubah_orang_tua_offline', $akun, [], $request->user()->sekolah_id);

        return redirect()
            ->route('panitia.offline.sekolah')
            ->with('sukses', 'Data orang tua disimpan.');
    }

    public function formSekolah(Request $request): View|RedirectResponse
    {
        $akun = $this->akunOffline($request);

        if (! $akun) {
            return $this->pesanAkunBelumDipilih();
        }

        $sekolah = $request->user()->sekolah;

        if (! $sekolah) {
            abort(403);
        }

        return view('panitia.offline.sekolah', [
            'akun' => $akun,
            'sekolah' => $sekolah,
            'anak' => $akun->anak()->orderBy('nama_lengkap')->get(),
            'gelombang' => $this->gelombangDibuka($sekolah),
            'butuhProgram' => $sekolah->kode === config('spmb.nomor_peserta.kode_program'),
        ]);
    }

    public function simpanSekolah(OfflineSekolahRequest $request): RedirectResponse
    {
        $akun = $this->akunOffline($request);

        if (! $akun) {
            return $this->pesanAkunBelumDipilih();
        }

        $sekolah = $request->user()->sekolah;

        if (! $sekolah) {
            abort(403);
        }

        $gelombang = Gelombang::query()
            ->whereKey($request->integer('gelombang_id'))
            ->where('sekolah_id', $sekolah->id)
            ->first();

        if (! $gelombang || ! $this->pendaftaranService->gelombangDibuka($gelombang)) {
            return back()->withInput()->withErrors([
                'gelombang_id' => 'Gelombang tidak tersedia atau sudah ditutup.',
            ]);
        }

        try {
            if ($request->filled('anak_id')) {
                $anak = $akun->anak()->whereKey($request->integer('anak_id'))->first();

                if (! $anak) {
                    return back()->withInput()->withErrors(['anak_id' => 'Anak tidak ditemukan pada akun ini.']);
                }

                $pendaftaran = $this->pendaftaranService->buatUntukAnak(
                    $request->user(),
                    $akun,
                    $anak,
                    $sekolah,
                    $gelombang,
                    $request->input('program'),
                );

                if (! $pendaftaran->masihDraf()) {
                    return redirect()
                        ->route('panitia.pendaftar.detail', $pendaftaran->uuid)
                        ->with('pesan', 'Anak ini sudah mendaftar pada gelombang tersebut.');
                }

                return redirect()
                    ->route('panitia.offline.langkah', ['pendaftaran' => $pendaftaran->uuid, 'n' => 2])
                    ->with('sukses', 'Draf pendaftaran dibuat. Lengkapi data anak.');
            }
        } catch (\InvalidArgumentException $galat) {
            return back()->withInput()->withErrors(['program' => $galat->getMessage()]);
        }

        $request->session()->put('pendaftaran_baru', [
            'sekolah_id' => $sekolah->id,
            'gelombang_id' => $gelombang->id,
            'program' => $sekolah->kode === config('spmb.nomor_peserta.kode_program')
                ? strtoupper((string) $request->input('program'))
                : null,
            'user_id' => $akun->id,
        ]);

        return redirect()->route('panitia.offline.anak-baru');
    }

    public function formAnakBaru(Request $request): View|RedirectResponse
    {
        $sesi = $this->sesiPendaftaranBaru($request);

        if ($sesi === null) {
            return redirect()->route('panitia.offline.sekolah')
                ->with('pesan', 'Pilih sekolah dan gelombang terlebih dahulu.');
        }

        $sekolah = Sekolah::query()->find($sesi['sekolah_id']);

        if (! $sekolah) {
            $request->session()->forget('pendaftaran_baru');

            return redirect()->route('panitia.offline.sekolah');
        }

        return view('walisantri.pendaftaran.langkah', [
            'layout' => 'layouts.panel',
            'banner' => self::BANNER,
            'pendaftaranUuid' => null,
            'langkah' => 2,
            'bagian' => 'identitas',
            'judulBagian' => self::JUDUL_BAGIAN['identitas'],
            'isian' => $this->aturanIsian->isianTampil($sekolah->id, 'identitas'),
            'nilai' => [],
            'judulSekolah' => $sekolah->nama,
            'program' => $sesi['program'],
            'aksiSimpan' => route('panitia.offline.anak-baru.simpan'),
            'lulusanYayasan' => false,
            'tautanDetail' => null,
        ]);
    }

    public function simpanAnakBaru(OfflineAnakBaruRequest $request): RedirectResponse
    {
        $sesi = $this->sesiPendaftaranBaru($request);

        if ($sesi === null) {
            return redirect()->route('panitia.offline.sekolah')
                ->with('pesan', 'Pilih sekolah dan gelombang terlebih dahulu.');
        }

        $sekolah = Sekolah::query()->findOrFail($sesi['sekolah_id']);
        $gelombang = Gelombang::query()->findOrFail($sesi['gelombang_id']);
        $akun = User::query()->find($sesi['user_id']);

        if (! $akun || ! $this->akunPolicy->kelola($request->user(), $akun)) {
            return $this->pesanAkunBelumDipilih();
        }

        try {
            $pendaftaran = $this->pendaftaranService->buatDenganAnakBaru(
                $request->user(),
                $akun,
                $sekolah,
                $gelombang,
                $sesi['program'],
                $request->validated(),
            );
        } catch (DataGandaException $galat) {
            return back()->withInput()->withErrors([
                'data_ganda' => $galat->getMessage(),
                'nama_lengkap' => $galat->getMessage(),
            ]);
        }

        $request->session()->forget('pendaftaran_baru');

        return redirect()
            ->route('panitia.offline.langkah', ['pendaftaran' => $pendaftaran->uuid, 'n' => 3])
            ->with('sukses', 'Data anak tersimpan. Lanjutkan ke alamat.');
    }

    public function langkah(Request $request, Pendaftaran $pendaftaran, int $n): View|RedirectResponse
    {
        $this->pastikanDrafOffline($pendaftaran, $request->user());

        if ($n < 2 || $n > 8) {
            abort(404);
        }

        if ($n === 6) {
            return redirect()->route('panitia.offline.berkas.index', $pendaftaran->uuid);
        }

        if ($n === 8) {
            return redirect()->route('panitia.offline.ringkasan', $pendaftaran->uuid);
        }

        $pendaftaran->load(['anak', 'sekolah', 'gelombang.tahunAjaran']);

        if ($n >= 2 && $n <= 5) {
            $bagian = AturanIsian::bagianDariLangkah($n);

            return view('walisantri.pendaftaran.langkah', [
                'layout' => 'layouts.panel',
                'banner' => self::BANNER,
                'pendaftaranUuid' => $pendaftaran->uuid,
                'langkah' => $n,
                'bagian' => $bagian,
                'judulBagian' => self::JUDUL_BAGIAN[$bagian],
                'isian' => $this->aturanIsian->isianTampil($pendaftaran->sekolah_id, $bagian),
                'nilai' => $this->aturanIsian->nilaiAnak($pendaftaran->anak, $pendaftaran->sekolah_id, $bagian),
                'judulSekolah' => $pendaftaran->sekolah->nama,
                'program' => $pendaftaran->program,
                'aksiSimpan' => route('panitia.offline.langkah.simpan', [
                    'pendaftaran' => $pendaftaran->uuid,
                    'n' => $n,
                ]),
                'lulusanYayasan' => (bool) $pendaftaran->lulusan_yayasan,
                'ruteLangkahNama' => 'panitia.offline.langkah',
                'tautanDetail' => route('panitia.offline.ringkasan', $pendaftaran->uuid),
            ]);
        }

        return $this->tampilkanBiaya($pendaftaran);
    }

    public function simpanLangkah(LangkahPendaftaranRequest $request, Pendaftaran $pendaftaran, int $n): RedirectResponse
    {
        $this->pastikanDrafOffline($pendaftaran, $request->user());

        if ($n < 2 || $n > 5) {
            abort(404);
        }

        try {
            $this->pendaftaranService->simpanLangkah($request->user(), $pendaftaran, $n, $request->validated());
        } catch (DataGandaException $galat) {
            return back()->withInput()->withErrors([
                'data_ganda' => $galat->getMessage(),
                'nama_lengkap' => $galat->getMessage(),
            ]);
        }

        if ($request->input('aksi') === 'draf') {
            return redirect()
                ->route('panitia.offline.langkah', ['pendaftaran' => $pendaftaran->uuid, 'n' => $n])
                ->with('sukses', 'Draf tersimpan.');
        }

        $berikutnya = $n + 1;

        return redirect()->route(
            $berikutnya === 6 ? 'panitia.offline.berkas.index' : 'panitia.offline.langkah',
            $berikutnya === 6 ? $pendaftaran->uuid : ['pendaftaran' => $pendaftaran->uuid, 'n' => $berikutnya],
        )->with('sukses', 'Tersimpan.');
    }

    public function berkasIndex(Request $request, Pendaftaran $pendaftaran): View
    {
        $this->pastikanDrafOffline($pendaftaran, $request->user());
        $pendaftaran->load(['anak', 'sekolah']);

        $jenis = JenisBerkas::query()
            ->where('sekolah_id', $pendaftaran->sekolah_id)
            ->where('aktif', true)
            ->orderBy('urutan')
            ->orderBy('id')
            ->get();

        $berkas = Berkas::query()
            ->where('pendaftaran_id', $pendaftaran->id)
            ->get()
            ->keyBy('jenis_berkas_id');

        return view('walisantri.pendaftaran.berkas', [
            'layout' => 'layouts.panel',
            'banner' => self::BANNER,
            'pendaftaranUuid' => $pendaftaran->uuid,
            'langkah' => 6,
            'jenis' => $jenis,
            'berkas' => $berkas,
            'bolehDiubah' => true,
            'namaAnak' => $pendaftaran->anak->nama_lengkap,
            'ruteLangkahNama' => 'panitia.offline.langkah',
            'ruteBerkasNama' => 'panitia.offline.berkas.index',
            'ruteTampilNama' => 'panitia.berkas.tampil',
            'ruteHapusNama' => 'panitia.offline.berkas.hapus',
            'ruteSimpanNama' => 'panitia.offline.berkas.simpan',
        ]);
    }

    public function berkasSimpan(UnggahBerkasRequest $request, Pendaftaran $pendaftaran): RedirectResponse
    {
        $this->pastikanDrafOffline($pendaftaran, $request->user());

        $jenis = JenisBerkas::query()
            ->whereKey($request->integer('jenis_berkas_id'))
            ->where('sekolah_id', $pendaftaran->sekolah_id)
            ->first();

        abort_if(! $jenis, 404);

        try {
            $this->berkasService->unggah($request->user(), $pendaftaran, $jenis, $request->file('berkas'));
        } catch (BerkasTidakValidException $galat) {
            return back()->withErrors(['berkas' => $galat->getMessage()]);
        } catch (AuthorizationException) {
            abort(403);
        }

        return redirect()
            ->route('panitia.offline.berkas.index', $pendaftaran->uuid)
            ->with('sukses', 'Berkas '.$jenis->nama.' berhasil diunggah.');
    }

    public function berkasHapus(Request $request, Pendaftaran $pendaftaran, Berkas $berkas): RedirectResponse
    {
        $this->pastikanDrafOffline($pendaftaran, $request->user());

        abort_if((int) $berkas->pendaftaran_id !== (int) $pendaftaran->id, 404);

        try {
            $this->berkasService->hapus($request->user(), $berkas);
        } catch (AuthorizationException) {
            abort(403);
        }

        return redirect()
            ->route('panitia.offline.berkas.index', $pendaftaran->uuid)
            ->with('sukses', 'Berkas dihapus.');
    }

    public function biaya(Request $request, Pendaftaran $pendaftaran): View
    {
        $this->pastikanDrafOffline($pendaftaran, $request->user());

        return $this->tampilkanBiaya($pendaftaran);
    }

    public function simpanBiaya(BiayaPendaftaranRequest $request, Pendaftaran $pendaftaran): RedirectResponse
    {
        $this->pastikanDrafOffline($pendaftaran, $request->user());

        if ($this->pendaftaranService->rincianBiaya($pendaftaran)->isNotEmpty()
            && ! $request->boolean('setuju_biaya')) {
            return back()->withInput()->withErrors([
                'setuju_biaya' => 'Centang persetujuan rincian biaya untuk melanjutkan.',
            ]);
        }

        try {
            $this->pendaftaranService->simpanBiaya($request->user(), $pendaftaran, $request->validated());
        } catch (\InvalidArgumentException $galat) {
            return back()->withInput()->withErrors(['pilihan_iap' => $galat->getMessage()]);
        }

        return redirect()
            ->route('panitia.offline.ringkasan', $pendaftaran->uuid)
            ->with('sukses', 'Biaya tersimpan.');
    }

    public function ringkasan(Request $request, Pendaftaran $pendaftaran): View
    {
        $this->pastikanDrafOffline($pendaftaran, $request->user());

        $pendaftaran->load(['anak', 'sekolah', 'gelombang']);

        return view('walisantri.pendaftaran.ringkasan', [
            'layout' => 'layouts.panel',
            'banner' => self::BANNER,
            'pendaftaranUuid' => $pendaftaran->uuid,
            'langkah' => 8,
            'ringkasan' => $this->pendaftaranService->ringkasan($pendaftaran),
            'kekurangan' => session('kekurangan', []),
            'ruteLangkahNama' => 'panitia.offline.langkah',
            'ruteBerkasNama' => 'panitia.offline.berkas.index',
            'tautanOrangTua' => route('panitia.offline.orang-tua'),
            'aksiKirim' => route('panitia.offline.kirim', $pendaftaran->uuid),
            'tautanDetail' => route('panitia.offline.sekolah'),
        ]);
    }

    public function kirim(KirimPendaftaranRequest $request, Pendaftaran $pendaftaran): RedirectResponse
    {
        $this->pastikanDrafOffline($pendaftaran, $request->user());

        try {
            $this->pendaftaranService->kirim(
                $request->user(),
                $pendaftaran,
                $request->boolean('pernyataan_data'),
                $request->boolean('pernyataan_privasi'),
            );
        } catch (SyaratKirimException $galat) {
            return redirect()
                ->route('panitia.offline.ringkasan', $pendaftaran->uuid)
                ->with('kekurangan', $galat->kekurangan)
                ->withErrors(['kirim' => $galat->getMessage()]);
        } catch (AuthorizationException $galat) {
            return redirect()
                ->route('panitia.offline.ringkasan', $pendaftaran->uuid)
                ->with('pesan', $galat->getMessage());
        }

        Catat::aksi('konfirmasi_lisan_offline', $pendaftaran, [
            'pernyataan_data' => true,
            'pernyataan_privasi' => true,
        ], $pendaftaran->sekolah_id);

        $request->session()->forget(['offline_user_id', 'pendaftaran_baru', 'kartu_akun']);

        return redirect()
            ->route('panitia.pendaftar.detail', $pendaftaran->uuid)
            ->with('sukses', 'Pendaftaran offline terkirim atas nama walisantri.');
    }

    private function tampilkanBiaya(Pendaftaran $pendaftaran): View
    {
        $pendaftaran->load(['anak', 'sekolah', 'gelombang']);
        $rincian = $this->pendaftaranService->rincianBiaya($pendaftaran);

        return view('walisantri.pendaftaran.biaya', [
            'layout' => 'layouts.panel',
            'banner' => self::BANNER,
            'pendaftaranUuid' => $pendaftaran->uuid,
            'langkah' => 7,
            'kelompok' => $rincian->groupBy(fn (RincianBiaya $biaya) => $biaya->kelompok ?: 'Biaya lainnya'),
            'rincian' => $rincian,
            'pilihanIap' => $pendaftaran->pilihan_iap,
            'setuju' => $pendaftaran->setuju_biaya_pada !== null,
            'aksiSimpan' => route('panitia.offline.biaya.simpan', $pendaftaran->uuid),
            'ruteLangkahNama' => 'panitia.offline.langkah',
            'ruteBerkasNama' => 'panitia.offline.berkas.index',
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Gelombang>
     */
    private function gelombangDibuka(Sekolah $sekolah)
    {
        $hariIni = now()->toDateString();

        return Gelombang::query()
            ->where('sekolah_id', $sekolah->id)
            ->where('status', 'dibuka')
            ->where(fn ($query) => $query->whereNull('tanggal_buka')->orWhere('tanggal_buka', '<=', $hariIni))
            ->where(fn ($query) => $query->whereNull('tanggal_tutup')->orWhere('tanggal_tutup', '>=', $hariIni))
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sesiPendaftaranBaru(Request $request): ?array
    {
        $sesi = $request->session()->get('pendaftaran_baru');

        if (! is_array($sesi)
            || empty($sesi['sekolah_id'])
            || empty($sesi['gelombang_id'])
            || empty($sesi['user_id'])) {
            return null;
        }

        return $sesi;
    }

    private function akunOffline(Request $request): ?User
    {
        $id = $request->session()->get('offline_user_id');

        if (! $id) {
            return null;
        }

        $user = User::query()->find($id);

        if (! $user || ! $this->akunPolicy->kelola($request->user(), $user)) {
            return null;
        }

        return $user;
    }

    private function pesanAkunBelumDipilih(): RedirectResponse
    {
        return redirect()
            ->route('panitia.offline.index')
            ->with('pesan', 'Pilih atau buat akun walisantri terlebih dahulu.');
    }

    /**
     * Hanya draf yang dibuat staf sekolah ini yang boleh dikelola lewat alur
     * offline. Draf milik walisantri tidak pernah terbuka di sini.
     */
    private function pastikanDrafOffline(Pendaftaran $pendaftaran, User $staf): void
    {
        abort_unless($pendaftaran->masihDraf(), 404);
        abort_unless($pendaftaran->dibuat_oleh !== null, 404);

        $pembuat = User::query()->find($pendaftaran->dibuat_oleh);

        abort_unless(
            $pembuat
            && in_array($pembuat->peran, [PeranPengguna::Panitia, PeranPengguna::Admin], true)
            && (int) $pembuat->sekolah_id === (int) $staf->sekolah_id
            && (int) $pendaftaran->sekolah_id === (int) $staf->sekolah_id,
            404
        );
    }
}
