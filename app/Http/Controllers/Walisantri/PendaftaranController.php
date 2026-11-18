<?php

namespace App\Http\Controllers\Walisantri;

use App\Enums\StatusBerkas;
use App\Exceptions\DataGandaException;
use App\Exceptions\SyaratKirimException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnakBaruRequest;
use App\Http\Requests\BiayaPendaftaranRequest;
use App\Http\Requests\KirimPendaftaranRequest;
use App\Http\Requests\LangkahPendaftaranRequest;
use App\Http\Requests\MulaiPendaftaranRequest;
use App\Models\Berkas;
use App\Models\Gelombang;
use App\Models\JenisBerkas;
use App\Models\Pendaftaran;
use App\Models\RincianBiaya;
use App\Models\Sekolah;
use App\Services\AturanIsian;
use App\Services\PendaftaranService;
use App\Services\StatusHasilService;
use App\Services\StatusTampilService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PendaftaranController extends Controller
{
    private const JUDUL_BAGIAN = [
        'identitas' => 'Data anak',
        'alamat' => 'Alamat',
        'kesehatan' => 'Kesehatan',
        'asal_sekolah' => 'Asal sekolah',
    ];

    public function __construct(
        private readonly AturanIsian $aturanIsian,
        private readonly PendaftaranService $pendaftaranService,
        private readonly StatusTampilService $statusTampil,
        private readonly StatusHasilService $statusHasil,
    ) {}

    public function formBaru(Request $request): View
    {
        $hariIni = now()->toDateString();

        $gelombang = Gelombang::query()
            ->where('status', 'dibuka')
            ->where(fn ($query) => $query->whereNull('tanggal_buka')->orWhere('tanggal_buka', '<=', $hariIni))
            ->where(fn ($query) => $query->whereNull('tanggal_tutup')->orWhere('tanggal_tutup', '>=', $hariIni))
            ->with('sekolah')
            ->orderBy('sekolah_id')
            ->orderBy('id')
            ->get()
            ->filter(fn (Gelombang $g) => $g->sekolah?->aktif)
            ->values();

        return view('walisantri.pendaftaran.baru', [
            'gelombang' => $gelombang,
            'anak' => $request->user()->anak()->orderBy('nama_lengkap')->get(),
            'jenisBerkas' => JenisBerkas::query()
                ->whereIn('sekolah_id', $gelombang->pluck('sekolah_id')->unique()->all())
                ->where('aktif', true)
                ->orderBy('urutan')
                ->orderBy('id')
                ->get()
                ->groupBy('sekolah_id'),
        ]);
    }

    public function mulai(MulaiPendaftaranRequest $request): RedirectResponse
    {
        $pengguna = $request->user();
        $sekolah = Sekolah::query()->findOrFail($request->integer('sekolah_id'));
        $gelombang = Gelombang::query()->findOrFail($request->integer('gelombang_id'));

        $anakId = $request->input('anak_id');

        if (blank($anakId)) {
            $request->session()->put('pendaftaran_baru', [
                'sekolah_id' => $sekolah->id,
                'gelombang_id' => $gelombang->id,
                'program' => $sekolah->kode === config('spmb.nomor_peserta.kode_program')
                    ? $request->input('program')
                    : null,
            ]);

            return redirect()->route('walisantri.daftar.anak-baru');
        }

        $anak = $pengguna->anak()->whereKey($anakId)->firstOrFail();

        $pendaftaran = $this->pendaftaranService->buatUntukAnak(
            $pengguna,
            $pengguna,
            $anak,
            $sekolah,
            $gelombang,
            $request->input('program'),
        );

        if (! $pendaftaran->wasRecentlyCreated) {
            return redirect()
                ->route('walisantri.pendaftaran.detail', $pendaftaran->uuid)
                ->with('pesan', 'Anak ini sudah memiliki pendaftaran pada sekolah dan gelombang tersebut.');
        }

        return redirect()
            ->route('walisantri.pendaftaran.langkah', ['pendaftaran' => $pendaftaran->uuid, 'n' => 2])
            ->with('sukses', 'Pendaftaran dimulai. Lengkapi data anak.');
    }

    public function formAnakBaru(Request $request): View|RedirectResponse
    {
        $sesi = $this->sesiBaru($request);

        if ($sesi === null) {
            return redirect()->route('walisantri.daftar.baru')
                ->with('pesan', 'Pilih sekolah dan gelombang terlebih dahulu.');
        }

        $sekolah = Sekolah::query()->find($sesi['sekolah_id']);
        $gelombang = Gelombang::query()->find($sesi['gelombang_id']);

        if (! $sekolah || ! $gelombang || ! $this->pendaftaranService->gelombangDibuka($gelombang)) {
            $request->session()->forget('pendaftaran_baru');

            return redirect()->route('walisantri.daftar.baru')
                ->with('pesan', 'Pendaftaran belum dibuka untuk pilihan tersebut.');
        }

        $isian = $this->aturanIsian->isianTampil($sekolah->id, 'identitas');

        return view('walisantri.pendaftaran.langkah', [
            'pendaftaranUuid' => null,
            'langkah' => 2,
            'bagian' => 'identitas',
            'judulBagian' => self::JUDUL_BAGIAN['identitas'],
            'isian' => $isian,
            'nilai' => [],
            'judulSekolah' => $sekolah->nama,
            'program' => $sesi['program'],
            'aksiSimpan' => route('walisantri.daftar.anak-baru.simpan'),
            'lulusanYayasan' => false,
        ]);
    }

    public function simpanAnakBaru(AnakBaruRequest $request): RedirectResponse
    {
        $sesi = $this->sesiBaru($request);

        if ($sesi === null) {
            return redirect()->route('walisantri.daftar.baru')
                ->with('pesan', 'Pilih sekolah dan gelombang terlebih dahulu.');
        }

        $sekolah = Sekolah::query()->findOrFail($sesi['sekolah_id']);
        $gelombang = Gelombang::query()->findOrFail($sesi['gelombang_id']);

        if (! $this->pendaftaranService->gelombangDibuka($gelombang)) {
            $request->session()->forget('pendaftaran_baru');

            return redirect()->route('walisantri.daftar.baru')
                ->with('pesan', 'Pendaftaran belum dibuka untuk pilihan tersebut.');
        }

        try {
            $pendaftaran = $this->pendaftaranService->buatDenganAnakBaru(
                $request->user(),
                $request->user(),
                $sekolah,
                $gelombang,
                $sesi['program'],
                $request->validated(),
            );
        } catch (DataGandaException $galat) {
            return back()
                ->withInput()
                ->withErrors([
                    'data_ganda' => $galat->getMessage(),
                    'nama_lengkap' => $galat->getMessage(),
                ]);
        }

        $request->session()->forget('pendaftaran_baru');

        $this->peringatkanUsia($request, $pendaftaran);

        return redirect()
            ->route('walisantri.pendaftaran.langkah', ['pendaftaran' => $pendaftaran->uuid, 'n' => 3])
            ->with('sukses', 'Data anak tersimpan. Lanjutkan ke alamat.');
    }

    public function langkah(Request $request, Pendaftaran $pendaftaran, int $n): View|RedirectResponse
    {
        $this->pastikanMilik($request, $pendaftaran);

        if ($n < 2 || $n > 8) {
            abort(404);
        }

        $pendaftaran->load(['anak', 'sekolah', 'gelombang.tahunAjaran']);

        if ($n === 6) {
            return redirect()->route('walisantri.pendaftaran.berkas.index', $pendaftaran->uuid);
        }

        if (! $pendaftaran->bolehDiubah()) {
            return redirect()
                ->route('walisantri.pendaftaran.detail', $pendaftaran->uuid)
                ->with('pesan', 'Pendaftaran sudah dikirim. Data tidak dapat diubah kecuali diminta diperbaiki oleh panitia.');
        }

        if ($n >= 2 && $n <= 5) {
            $bagian = AturanIsian::bagianDariLangkah($n);

            return view('walisantri.pendaftaran.langkah', [
                'pendaftaranUuid' => $pendaftaran->uuid,
                'langkah' => $n,
                'bagian' => $bagian,
                'judulBagian' => self::JUDUL_BAGIAN[$bagian],
                'isian' => $this->aturanIsian->isianTampil($pendaftaran->sekolah_id, $bagian),
                'nilai' => $this->aturanIsian->nilaiAnak($pendaftaran->anak, $pendaftaran->sekolah_id, $bagian),
                'judulSekolah' => $pendaftaran->sekolah->nama,
                'program' => $pendaftaran->program,
                'aksiSimpan' => route('walisantri.pendaftaran.langkah.simpan', [
                    'pendaftaran' => $pendaftaran->uuid,
                    'n' => $n,
                ]),
                'lulusanYayasan' => (bool) $pendaftaran->lulusan_yayasan,
            ]);
        }

        if ($n === 7) {
            $rincian = $this->pendaftaranService->rincianBiaya($pendaftaran);

            return view('walisantri.pendaftaran.biaya', [
                'pendaftaranUuid' => $pendaftaran->uuid,
                'langkah' => 7,
                'kelompok' => $rincian->groupBy(fn (RincianBiaya $biaya) => $biaya->kelompok ?: 'Biaya lainnya'),
                'rincian' => $rincian,
                'pilihanIap' => $pendaftaran->pilihan_iap,
                'setuju' => $pendaftaran->setuju_biaya_pada !== null,
                'aksiSimpan' => route('walisantri.pendaftaran.biaya.simpan', $pendaftaran->uuid),
            ]);
        }

        return view('walisantri.pendaftaran.ringkasan', [
            'pendaftaranUuid' => $pendaftaran->uuid,
            'langkah' => 8,
            'ringkasan' => $this->pendaftaranService->ringkasan($pendaftaran),
            'kekurangan' => session('kekurangan', []),
        ]);
    }

    public function simpanLangkah(LangkahPendaftaranRequest $request, Pendaftaran $pendaftaran, int $n): RedirectResponse
    {
        $this->pastikanMilik($request, $pendaftaran);

        if (! $pendaftaran->bolehDiubah()) {
            abort(403);
        }

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

        if ($n === 2) {
            $this->peringatkanUsia($request, $pendaftaran);
        }

        if ($request->input('aksi') === 'draf') {
            return redirect()
                ->route('walisantri.pendaftaran.langkah', ['pendaftaran' => $pendaftaran->uuid, 'n' => $n])
                ->with('sukses', 'Draf tersimpan.');
        }

        $berikutnya = $n + 1;

        return redirect()->route(
            $berikutnya === 6 ? 'walisantri.pendaftaran.berkas.index' : 'walisantri.pendaftaran.langkah',
            $berikutnya === 6 ? $pendaftaran->uuid : ['pendaftaran' => $pendaftaran->uuid, 'n' => $berikutnya],
        )->with('sukses', 'Tersimpan.');
    }

    public function simpanBiaya(BiayaPendaftaranRequest $request, Pendaftaran $pendaftaran): RedirectResponse
    {
        $this->pastikanMilik($request, $pendaftaran);

        if (! $pendaftaran->bolehDiubah()) {
            abort(403);
        }

        if ($this->pendaftaranService->rincianBiaya($pendaftaran)->isNotEmpty() && ! $request->boolean('setuju_biaya')) {
            return back()
                ->withInput()
                ->withErrors(['setuju_biaya' => 'Centang persetujuan rincian biaya untuk melanjutkan.']);
        }

        try {
            $this->pendaftaranService->simpanBiaya($request->user(), $pendaftaran, $request->validated());
        } catch (\InvalidArgumentException $galat) {
            return back()->withInput()->withErrors(['pilihan_iap' => $galat->getMessage()]);
        }

        return redirect()
            ->route('walisantri.pendaftaran.langkah', ['pendaftaran' => $pendaftaran->uuid, 'n' => 8])
            ->with('sukses', 'Biaya tersimpan.');
    }

    public function kirim(KirimPendaftaranRequest $request, Pendaftaran $pendaftaran): RedirectResponse
    {
        $this->pastikanMilik($request, $pendaftaran);

        try {
            $this->pendaftaranService->kirim(
                $request->user(),
                $pendaftaran,
                $request->boolean('pernyataan_data'),
                $request->boolean('pernyataan_privasi'),
            );
        } catch (SyaratKirimException $galat) {
            return redirect()
                ->route('walisantri.pendaftaran.langkah', ['pendaftaran' => $pendaftaran->uuid, 'n' => 8])
                ->with('kekurangan', $galat->kekurangan)
                ->withErrors(['kirim' => $galat->getMessage()]);
        } catch (AuthorizationException $galat) {
            return redirect()
                ->route('walisantri.pendaftaran.detail', $pendaftaran->uuid)
                ->with('pesan', $galat->getMessage());
        }

        return redirect()
            ->route('walisantri.pendaftaran.sukses', $pendaftaran->uuid)
            ->with('sukses', 'Pendaftaran terkirim.');
    }

    public function sukses(Request $request, Pendaftaran $pendaftaran): View|RedirectResponse
    {
        $this->pastikanMilik($request, $pendaftaran);

        $pendaftaran->load(['anak', 'sekolah']);

        if ($pendaftaran->masihDraf()) {
            return redirect()->route('walisantri.pendaftaran.detail', $pendaftaran->uuid);
        }

        return view('walisantri.pendaftaran.sukses', [
            'pendaftaranUuid' => $pendaftaran->uuid,
            'nomorPeserta' => $pendaftaran->nomor_peserta,
            'namaAnak' => $pendaftaran->anak->nama_lengkap,
            'sekolah' => $pendaftaran->sekolah->nama,
        ]);
    }

    public function detail(Request $request, Pendaftaran $pendaftaran): View
    {
        $this->pastikanMilik($request, $pendaftaran);

        $pendaftaran->load(['anak', 'sekolah', 'gelombang', 'pembayaran', 'daftarUlang']);
        $status = $this->statusTampil->untuk($pendaftaran);

        $berkasPerbaikan = Berkas::query()
            ->where('pendaftaran_id', $pendaftaran->id)
            ->where('status', StatusBerkas::PerluPerbaikan)
            ->with('jenisBerkas')
            ->get()
            ->map(fn (Berkas $berkas) => [
                'nama' => $berkas->jenisBerkas->nama,
                'catatan' => $berkas->catatan,
            ])
            ->values();

        return view('walisantri.pendaftaran.detail', [
            'pendaftaranUuid' => $pendaftaran->uuid,
            'namaAnak' => $pendaftaran->anak->nama_lengkap,
            'sekolah' => $pendaftaran->sekolah->nama,
            'kodeSekolah' => $pendaftaran->sekolah->kode,
            'program' => $pendaftaran->program,
            'nomorPeserta' => $pendaftaran->nomor_peserta,
            'status' => $status,
            'kemajuan' => $this->statusTampil->garisWaktu($pendaftaran, $status),
            'berkasPerbaikan' => $berkasPerbaikan,
            'catatanPerbaikan' => $this->statusHasil->catatanPerbaikan($pendaftaran),
            'tautanGrupWa' => $this->tautanGrupWa($pendaftaran, $status->tampilkanGrupWa),
        ]);
    }

    public function hasil(Request $request, Pendaftaran $pendaftaran): View
    {
        $this->pastikanMilik($request, $pendaftaran);

        $pendaftaran->load(['anak', 'sekolah', 'gelombang']);
        $status = $this->statusTampil->untuk($pendaftaran);

        return view('walisantri.pendaftaran.hasil', [
            'pendaftaranUuid' => $pendaftaran->uuid,
            'namaAnak' => $pendaftaran->anak->nama_lengkap,
            'sekolah' => $pendaftaran->sekolah->nama,
            'nomorPeserta' => $pendaftaran->nomor_peserta,
            'status' => $status,
            'tautanGrupWa' => $this->tautanGrupWa($pendaftaran, $status->tampilkanGrupWa),
        ]);
    }

    public function kirimPerbaikan(Request $request, Pendaftaran $pendaftaran): RedirectResponse
    {
        $this->pastikanMilik($request, $pendaftaran);

        try {
            $this->pendaftaranService->kirimPerbaikan($request->user(), $pendaftaran);
        } catch (SyaratKirimException $galat) {
            return redirect()
                ->route('walisantri.pendaftaran.detail', $pendaftaran->uuid)
                ->with('kekurangan', $galat->kekurangan)
                ->withErrors(['kirim' => $galat->getMessage()]);
        } catch (AuthorizationException $galat) {
            return redirect()
                ->route('walisantri.pendaftaran.detail', $pendaftaran->uuid)
                ->with('pesan', $galat->getMessage());
        }

        return redirect()
            ->route('walisantri.pendaftaran.detail', $pendaftaran->uuid)
            ->with('sukses', 'Perbaikan terkirim. Pendaftaran menunggu verifikasi ulang.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sesiBaru(Request $request): ?array
    {
        $sesi = $request->session()->get('pendaftaran_baru');

        if (! is_array($sesi) || empty($sesi['sekolah_id']) || empty($sesi['gelombang_id'])) {
            return null;
        }

        return $sesi;
    }

    private function pastikanMilik(Request $request, Pendaftaran $pendaftaran): void
    {
        abort_unless(
            $pendaftaran->anak()->where('user_id', $request->user()->id)->exists(),
            404
        );
    }

    private function peringatkanUsia(Request $request, Pendaftaran $pendaftaran): void
    {
        $pendaftaran->loadMissing(['anak', 'sekolah']);

        $jenjang = match (true) {
            str_contains((string) $pendaftaran->sekolah->jenjang, 'SMP') => 'SMP',
            str_contains((string) $pendaftaran->sekolah->jenjang, 'SD') => 'SD',
            default => 'TK',
        };

        $rentang = config('spmb.usia.'.$jenjang);

        if (! $rentang || ! $pendaftaran->anak->tanggal_lahir) {
            return;
        }

        $usia = $pendaftaran->anak->tanggal_lahir->age;

        if ($usia < $rentang[0] || $usia > $rentang[1]) {
            $request->session()->flash(
                'peringatan_usia',
                'Periksa kembali tanggal lahir. Usia anak '.$usia.' tahun, di luar rentang lazim '.$jenjang.' ('.$rentang[0].'â€“'.$rentang[1].' tahun).'
            );
        }
    }

    private function tautanGrupWa(Pendaftaran $pendaftaran, bool $boleh): ?string
    {
        if (! $boleh) {
            return null;
        }

        $tautan = $pendaftaran->gelombang->tautan_grup_wa;

        if (blank($tautan)) {
            return null;
        }

        return str_starts_with($tautan, 'https://chat.whatsapp.com/') ? $tautan : null;
    }
}
