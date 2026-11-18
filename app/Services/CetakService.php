<?php

namespace App\Services;

use App\Exceptions\AturanBisnisException;
use App\Models\JenisBerkas;
use App\Models\OrangTua;
use App\Models\Pendaftaran;
use App\Models\Pembayaran;

/**
 * Pengumpul data halaman cetak (formulir, bukti pendaftaran, kuitansi).
 *
 * Aturan cetak:
 * - Formulir pendaftaran: data lengkap termasuk NIK utuh (halaman arsip
 *   sekolah), tetapi TANPA `catatan_panitia` dan `catatan_wawancara`.
 * - Bukti pendaftaran: status memakai tampilan walisantri
 *   (`StatusTampilService`) sehingga hasil seleksi tidak bocor sebelum
 *   pengumuman; NIK disamarkan (empat digit terakhir saja).
 * - Kuitansi: hanya pembayaran `disetujui`; nomor kuitansi turunan terkontrol
 *   `KWT-{kode}-{tahun}-{id 5 digit}`.
 */
class CetakService
{
    public function __construct(
        private readonly AturanIsian $aturanIsian,
        private readonly StatusTampilService $statusTampil,
        private readonly PendaftaranService $pendaftaranService,
    ) {}

    /**
     * Seluruh data anak dan orang tua untuk formulir cetak.
     *
     * @return array<string, mixed>
     */
    public function dataFormulir(Pendaftaran $pendaftaran): array
    {
        $pendaftaran->loadMissing(['anak', 'sekolah', 'gelombang', 'berkas.jenisBerkas', 'daftarUlang']);

        $bagian = [];

        foreach ([
            'identitas' => 'Data anak',
            'alamat' => 'Alamat',
            'kesehatan' => 'Kesehatan',
            'asal_sekolah' => 'Asal sekolah',
        ] as $kunciBagian => $judul) {
            $isian = $this->aturanIsian->isianTampil($pendaftaran->sekolah_id, $kunciBagian);
            $nilai = $this->aturanIsian->nilaiAnak($pendaftaran->anak, $pendaftaran->sekolah_id, $kunciBagian);

            $bagian[$kunciBagian] = [
                'judul' => $judul,
                'isian' => collect($isian)->map(function (array $definisi, string $kunci) use ($nilai) {
                    $isi = $nilai[$kunci] ?? null;

                    // NIK utuh: halaman ini untuk arsip sekolah.
                    if ($isi instanceof \DateTimeInterface) {
                        $isi = $isi->format('d-m-Y');
                    } elseif (is_bool($isi)) {
                        $isi = $isi ? 'Ya' : 'Tidak';
                    } elseif (isset($definisi['pilihan'][$isi])) {
                        $isi = $definisi['pilihan'][$isi];
                    }

                    return [
                        'label' => $definisi['label'],
                        'nilai' => filled($isi) ? (string) $isi : '—',
                    ];
                })->values()->all(),
            ];
        }

        $orangTua = OrangTua::query()
            ->where('user_id', $pendaftaran->anak->user_id)
            ->get()
            ->filter(fn (OrangTua $baris) => filled($baris->nama))
            ->map(fn (OrangTua $baris) => [
                'hubungan' => $baris->hubungan->label(),
                'nama' => (string) $baris->nama,
                'nik' => filled($baris->nik) ? (string) $baris->nik : null,
                'tempat_lahir' => $baris->tempat_lahir,
                'tanggal_lahir' => $baris->tanggal_lahir?->format('d-m-Y'),
                'status_hidup' => $baris->status_hidup,
                'pendidikan' => $baris->pendidikan,
                'pekerjaan' => $baris->pekerjaan,
                'penghasilan' => $baris->penghasilan,
                'no_hp' => $baris->no_hp,
                'alamat' => $baris->alamat,
            ])
            ->all();

        $totalJenis = JenisBerkas::query()
            ->where('sekolah_id', $pendaftaran->sekolah_id)
            ->where('aktif', true)
            ->count();

        $berkas = $pendaftaran->berkas
            ->map(fn ($b) => [
                'nama' => $b->jenisBerkas?->nama ?? 'Berkas',
                'status' => $b->status->label(),
                'catatan' => $b->status === \App\Enums\StatusBerkas::PerluPerbaikan ? $b->catatan : null,
            ])
            ->all();

        return [
            'pendaftaran' => $pendaftaran,
            'sekolah' => $pendaftaran->sekolah,
            'bagian' => $bagian,
            'orangTua' => $orangTua,
            'berkas' => $berkas,
            'berkasRingkas' => $pendaftaran->berkas->where('status', \App\Enums\StatusBerkas::Sesuai)->count()
                ."/{$totalJenis} sesuai",
            'rincian' => $this->pendaftaranService->rincianBiaya($pendaftaran),
            'tahapDua' => $this->tahapDua($pendaftaran),
        ];
    }

    /**
     * Isian tahap 2 (daftar ulang) bila ada — tanpa catatan wawancara.
     *
     * @return array<string, string>|null
     */
    private function tahapDua(Pendaftaran $pendaftaran): ?array
    {
        $du = $pendaftaran->daftarUlang;

        if ($du === null) {
            return null;
        }

        return [
            'hadir' => $du->hadir ? 'Ya' : 'Belum',
            'kekurangan' => filled($du->kekurangan) ? (string) $du->kekurangan : '—',
            'selesai' => $du->selesai ? 'Ya' : 'Belum',
        ];
    }

    /**
     * Data bukti pendaftaran; status memakai tampilan walisantri dan NIK
     * disamarkan.
     *
     * @return array<string, mixed>
     */
    public function dataBukti(Pendaftaran $pendaftaran): array
    {
        $pendaftaran->loadMissing(['anak', 'sekolah', 'gelombang', 'berkas.jenisBerkas']);

        $status = $this->statusTampil->untuk($pendaftaran);

        return [
            'pendaftaran' => $pendaftaran,
            'sekolah' => $pendaftaran->sekolah,
            'gelombang' => $pendaftaran->gelombang,
            'anak' => $pendaftaran->anak,
            'nomorPeserta' => (string) ($pendaftaran->nomor_peserta ?? '—'),
            'tanggalKirim' => $pendaftaran->dikirim_pada?->format('d-m-Y H:i') ?? '—',
            'nikTersamarkan' => $this->nikEmpatTerakhir($pendaftaran->anak->getAttribute('nik')),
            'status' => $status,
            'berkas' => $pendaftaran->berkas
                ->map(fn ($b) => ['nama' => $b->jenisBerkas?->nama ?? 'Berkas', 'status' => $b->status->label()])
                ->all(),
        ];
    }

    /**
     * Data kuitansi pembayaran. Hanya pembayaran disetujui.
     *
     * @return array<string, mixed>
     */
    public function dataKuitansi(Pembayaran $pembayaran): array
    {
        $pembayaran->loadMissing([
            'pendaftaran.anak',
            'pendaftaran.sekolah',
            'pendaftaran.gelombang.tahunAjaran',
            'rincianBiaya',
            'pemroses',
        ]);

        if ($pembayaran->status !== \App\Enums\StatusPembayaran::Disetujui) {
            throw new AturanBisnisException('Kuitansi hanya dapat dicetak untuk pembayaran yang sudah disetujui.');
        }

        $pendaftaran = $pembayaran->pendaftaran;

        return [
            'pembayaran' => $pembayaran,
            'pendaftaran' => $pendaftaran,
            'sekolah' => $pendaftaran->sekolah,
            'nomorKuitansi' => $this->nomorKuitansi($pendaftaran, $pembayaran),
            'namaAnak' => (string) ($pendaftaran->anak?->nama_lengkap ?? ''),
            'nomorPeserta' => (string) ($pendaftaran->nomor_peserta ?? '—'),
            'komponen' => $pembayaran->rincianBiaya?->nama ?? 'Biaya pendidikan',
            'nominal' => (int) $pembayaran->nominal,
            'metode' => $pembayaran->metode->label(),
            'tanggal' => $pembayaran->dibayar_pada?->format('d-m-Y H:i') ?? $pembayaran->created_at?->format('d-m-Y H:i') ?? '—',
            'penerima' => (string) ($pembayaran->pemroses?->nama ?? 'Panitia'),
        ];
    }

    /**
     * Nomor kuitansi turunan terkontrol: KWT-{kode}-{tahun}-{id 5 digit}.
     */
    public function nomorKuitansi(Pendaftaran $pendaftaran, Pembayaran $pembayaran): string
    {
        $kode = (string) ($pendaftaran->sekolah?->kode ?? 'SPMB');

        $tahun = (int) $pembayaran->created_at?->format('Y');

        if ($pendaftaran->gelombang?->tahunAjaran?->nama) {
            $tahun = (int) mb_substr((string) $pendaftaran->gelombang->tahunAjaran->nama, 0, 4);
        }

        return sprintf('KWT-%s-%d-%05d', $kode, $tahun, $pembayaran->id);
    }

    /**
     * NIK pada bukti pendaftaran hanya menampilkan empat digit terakhir.
     */
    private function nikEmpatTerakhir(mixed $nik): string
    {
        $angka = preg_replace('/\D+/', '', (string) ($nik ?? ''));

        if ($angka === '' || $angka === false) {
            return '—';
        }

        if (strlen($angka) <= 4) {
            return str_repeat('*', strlen($angka));
        }

        return str_repeat('*', strlen($angka) - 4).substr($angka, -4);
    }
}
