<?php

namespace App\Http\Controllers\Panitia;

use App\Enums\ModePenerimaan;
use App\Enums\PeranPengguna;
use App\Enums\StatusBerkas;
use App\Enums\StatusHasil;
use App\Enums\StatusPembayaran;
use App\Http\Controllers\Controller;
use App\Http\Requests\CatatanPanitiaRequest;
use App\Http\Requests\DataPendaftarRequest;
use App\Models\Anak;
use App\Models\Berkas;
use App\Models\Gelombang;
use App\Models\JenisBerkas;
use App\Models\LogAktivitas;
use App\Models\OrangTua;
use App\Models\Pendaftaran;
use App\Models\Pembayaran;
use App\Services\AturanIsian;
use App\Services\BerkasService;
use App\Services\DaftarUlangService;
use App\Services\KuotaService;
use App\Services\PembayaranService;
use App\Services\PendaftaranService;
use App\Services\StatusHasilService;
use App\Support\Catat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PanitiaController extends Controller
{
    private const JUDUL_BAGIAN = [
        'identitas' => 'Data anak',
        'alamat' => 'Alamat',
        'kesehatan' => 'Kesehatan',
        'asal_sekolah' => 'Asal sekolah',
    ];

    private const URUTAN_SAH = ['nomor_peserta', 'nama', 'dikirim_pada', 'status_hasil'];

    private const TAB_SAH = ['data', 'berkas', 'pembayaran', 'daftar-ulang', 'riwayat'];

    public function __construct(
        private readonly AturanIsian $aturanIsian,
        private readonly PendaftaranService $pendaftaranService,
        private readonly BerkasService $berkasService,
        private readonly StatusHasilService $statusHasil,
        private readonly KuotaService $kuota,
        private readonly PembayaranService $pembayaranService,
        private readonly DaftarUlangService $daftarUlangService,
    ) {}

    public function beranda(Request $request): View
    {
        $pengguna = $request->user();
        $sekolah = $pengguna->sekolah;

        $dasar = Pendaftaran::query()->terkirim();

        $jumlah = [
            'total' => (clone $dasar)->count(),
            'baru' => (clone $dasar)->where('status_hasil', StatusHasil::Baru)->count(),
            'perlu_perbaikan' => (clone $dasar)->where('status_hasil', StatusHasil::PerluPerbaikan)->count(),
            'diterima' => (clone $dasar)->where('status_hasil', StatusHasil::Diterima)->count(),
            'cadangan' => (clone $dasar)->where('status_hasil', StatusHasil::Cadangan)->count(),
        ];

        $gelombang = Gelombang::query()
            ->where('status', 'dibuka')
            ->orderBy('id')
            ->get()
            ->map(fn (Gelombang $baris) => [
                'nama' => $baris->nama,
                'kuota' => $baris->kuota,
                'terisi' => $this->kuota->terisi($baris),
                'sisa' => $this->kuota->sisa($baris),
            ]);

        $antrean = $this->pembayaranService->antrean((int) $pengguna->sekolah_id)->count();

        return view('panitia.beranda', [
            'sekolah' => $sekolah,
            'jumlah' => $jumlah,
            'gelombang' => $gelombang,
            'antrean' => $antrean,
        ]);
    }

    public function daftar(Request $request): View
    {
        $pengguna = $request->user();
        $sekolah = $pengguna->sekolah;

        $cari = trim((string) $request->query('cari', ''));
        $gelombangId = $request->integer('gelombang') ?: null;
        $status = (string) $request->query('status', '');
        $jenis = (string) $request->query('jenis', '');
        $pembayaran = (string) $request->query('pembayaran', '');
        $program = (string) $request->query('program', '');
        $kelengkapan = (string) $request->query('kelengkapan', '');

        $urut = (string) $request->query('urut', '');
        $urut = in_array($urut, self::URUTAN_SAH, true) ? $urut : 'dikirim_pada';

        $arah = strtolower((string) $request->query('arah', '')) === 'asc' ? 'asc' : 'desc';

        if ($urut === 'dikirim_pada' && ! $request->filled('arah')) {
            $arah = $sekolah?->mode_penerimaan === ModePenerimaan::TanpaSeleksi ? 'asc' : 'desc';
        }

        $daftar = Pendaftaran::query()
            ->terkirim()
            ->with(['anak', 'gelombang', 'berkas', 'pembayaran'])
            ->when($cari !== '', function ($query) use ($cari) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $cari);
                $pola = '%'.$escaped.'%';

                $query->where(function ($sub) use ($pola) {
                    $sub->whereHas('anak', fn ($anak) => $anak->whereRaw("anak.nama_lengkap LIKE ? ESCAPE '\\'", [$pola]))
                        ->orWhereRaw("pendaftaran.nomor_peserta LIKE ? ESCAPE '\\'", [$pola]);
                });
            })
            ->when($gelombangId, fn ($query) => $query->where('gelombang_id', $gelombangId))
            ->when(in_array($status, array_column(StatusHasil::cases(), 'value'), true),
                fn ($query) => $query->where('status_hasil', $status))
            ->when($jenis === 'baru', fn ($query) => $query->where('lulusan_yayasan', false))
            ->when($jenis === 'lulusan', fn ($query) => $query->where('lulusan_yayasan', true))
            ->when($program !== '', fn ($query) => $query->where('program', strtoupper($program)))
            ->when($pembayaran === 'menunggu', fn ($query) => $query->whereHas('pembayaran',
                fn ($bayar) => $bayar->where('status', StatusPembayaran::Menunggu)))
            ->when($pembayaran === 'disetujui', fn ($query) => $query->whereHas('pembayaran',
                fn ($bayar) => $bayar->where('status', StatusPembayaran::Disetujui)))
            ->when($pembayaran === 'ditolak', fn ($query) => $query->whereHas('pembayaran',
                fn ($bayar) => $bayar->where('status', StatusPembayaran::Ditolak)))
            ->when($pembayaran === 'belum', fn ($query) => $query->whereDoesntHave('pembayaran'))
            ->when($kelengkapan === 'perlu_perbaikan', fn ($query) => $query->whereHas('berkas',
                fn ($berkas) => $berkas->where('status', StatusBerkas::PerluPerbaikan)))
            ->when($kelengkapan === 'lengkap', fn ($query) => $query->whereDoesntHave('berkas',
                fn ($berkas) => $berkas->where('status', StatusBerkas::PerluPerbaikan)));

        if ($urut === 'nama') {
            $daftar->orderBy(
                Anak::query()->select('nama_lengkap')->whereColumn('anak.id', 'pendaftaran.anak_id'),
                $arah,
            );
        } else {
            $daftar->orderBy('pendaftaran.'.$urut, $arah);
        }

        $daftar->orderBy('pendaftaran.id', 'asc');

        $daftar = $daftar->paginate(25)->withQueryString();

        $totalJenis = JenisBerkas::query()
            ->where('sekolah_id', $pengguna->sekolah_id)
            ->where('aktif', true)
            ->count();

        $gelombangPilihan = Gelombang::query()->orderBy('id')->get();

        $drafOffline = Pendaftaran::query()
            ->whereNull('dikirim_pada')
            ->whereHas('pembuat', fn ($pembuat) => $pembuat->where('sekolah_id', $pengguna->sekolah_id))
            ->with(['anak', 'gelombang'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        return view('panitia.pendaftar.index', [
            'daftar' => $daftar,
            'cari' => $cari,
            'gelombangPilihan' => $gelombangPilihan,
            'gelombangId' => $gelombangId,
            'status' => $status,
            'jenis' => $jenis,
            'pembayaran' => $pembayaran,
            'program' => $program,
            'kelengkapan' => $kelengkapan,
            'urut' => $urut,
            'arah' => $arah,
            'totalJenis' => $totalJenis,
            'drafOffline' => $drafOffline,
            'sekolah' => $sekolah,
        ]);
    }

    public function detail(Request $request, Pendaftaran $pendaftaran): View
    {
        $this->pastikanTerkirim($pendaftaran);

        $pendaftaran->load([
            'anak',
            'sekolah',
            'gelombang',
            'berkas.jenisBerkas',
            'pembayaran.rincianBiaya',
            'daftarUlang',
            'riwayatStatus.olehUser',
        ]);

        $tab = (string) $request->query('tab', 'data');
        $tab = in_array($tab, self::TAB_SAH, true) ? $tab : 'data';

        $bagian = [];

        foreach (self::JUDUL_BAGIAN as $kunci => $judul) {
            $bagian[$kunci] = [
                'judul' => $judul,
                'isian' => $this->aturanIsian->isianTampil($pendaftaran->sekolah_id, $kunci),
                'nilai' => $this->aturanIsian->nilaiAnak($pendaftaran->anak, $pendaftaran->sekolah_id, $kunci),
            ];
        }

        $orangTua = OrangTua::query()
            ->where('user_id', $pendaftaran->anak->user_id)
            ->get()
            ->keyBy(fn (OrangTua $baris) => $baris->hubungan->value);

        $jenisBerkas = JenisBerkas::query()
            ->where('sekolah_id', $pendaftaran->sekolah_id)
            ->where('aktif', true)
            ->orderBy('urutan')
            ->orderBy('id')
            ->get();

        $berkasPerJenis = $pendaftaran->berkas->keyBy('jenis_berkas_id');

        $log = LogAktivitas::query()
            ->tanpaSekolah()
            ->where(function ($query) use ($pendaftaran) {
                $query->where(fn ($sub) => $sub->where('objek', 'Pendaftaran')->where('objek_id', $pendaftaran->id))
                    ->orWhere(fn ($sub) => $sub->where('objek', 'Pembayaran')
                        ->whereIn('objek_id', $pendaftaran->pembayaran->pluck('id')->all()))
                    ->orWhere(fn ($sub) => $sub->where('objek', 'DaftarUlang')
                        ->where('objek_id', $pendaftaran->daftarUlang?->id ?? 0));
            })
            ->with('user')
            ->orderByDesc('dibuat_pada')
            ->limit(100)
            ->get();

        $pilihanStatus = $this->statusHasil->pilihan($pendaftaran, $request->user());

        $posisiGelombang = null;

        if ($pendaftaran->sekolah->mode_penerimaan === ModePenerimaan::TanpaSeleksi) {
            $posisiGelombang = Pendaftaran::query()
                ->terkirim()
                ->where('gelombang_id', $pendaftaran->gelombang_id)
                ->where('dikirim_pada', '<=', $pendaftaran->dikirim_pada)
                ->count();
        }

        return view('panitia.pendaftar.detail', [
            'pendaftaran' => $pendaftaran,
            'tab' => $tab,
            'bagian' => $bagian,
            'orangTua' => $orangTua,
            'jenisBerkas' => $jenisBerkas,
            'berkasPerJenis' => $berkasPerJenis,
            'log' => $log,
            'pilihanStatus' => $pilihanStatus,
            'kuotaTerisi' => $this->kuota->terisi($pendaftaran->gelombang),
            'kuotaSisa' => $this->kuota->sisa($pendaftaran->gelombang),
            'posisiGelombang' => $posisiGelombang,
            'wajibBelumSiap' => $this->berkasService->wajibBelumSiap($pendaftaran),
            'adaPerluPerbaikan' => $this->berkasService->adaPerluPerbaikan($pendaftaran),
            'catatanPerbaikan' => $this->statusHasil->catatanPerbaikan($pendaftaran),
            'rincian' => $this->pendaftaranService->rincianBiaya($pendaftaran),
        ]);
    }

    public function simpanData(DataPendaftarRequest $request, Pendaftaran $pendaftaran): RedirectResponse
    {
        $this->pastikanTerkirim($pendaftaran);

        $orangTua = [];

        foreach (['ayah', 'ibu', 'wali'] as $hubungan) {
            $baris = $request->input($hubungan);

            if (is_array($baris)) {
                $orangTua[$hubungan] = $baris;
            }
        }

        try {
            $this->pendaftaranService->simpanDataOlehStaf($request->user(), $pendaftaran, $request->validated());
            $this->pendaftaranService->simpanOrangTuaOlehStaf($request->user(), $pendaftaran, $orangTua);
        } catch (\InvalidArgumentException $galat) {
            return back()->withInput()->withErrors(['data' => $galat->getMessage()]);
        }

        return redirect()
            ->route('panitia.pendaftar.detail', ['pendaftaran' => $pendaftaran->uuid, 'tab' => 'data'])
            ->with('sukses', 'Data pendaftar diperbarui.');
    }

    public function simpanCatatan(CatatanPanitiaRequest $request, Pendaftaran $pendaftaran): RedirectResponse
    {
        $this->pastikanTerkirim($pendaftaran);

        $catatan = trim((string) $request->input('catatan_panitia'));

        $pendaftaran->forceFill([
            'catatan_panitia' => $catatan === '' ? null : $catatan,
        ])->save();

        Catat::aksi('simpan_catatan_panitia', $pendaftaran, [], $pendaftaran->sekolah_id);

        return redirect()
            ->route('panitia.pendaftar.detail', ['pendaftaran' => $pendaftaran->uuid])
            ->with('sukses', 'Catatan internal disimpan.');
    }

    /**
     * @return array<int, string>
     */
    public static function tabSah(): array
    {
        return self::TAB_SAH;
    }

    private function pastikanTerkirim(Pendaftaran $pendaftaran): void
    {
        abort_unless($pendaftaran->sudahDikirim(), 404);
        abort_unless(Gate::forUser(request()->user())->allows('lihat', $pendaftaran), 404);
    }
}
