<?php

namespace App\Services;

use App\Enums\JenisRiwayat;
use App\Enums\PeranPengguna;
use App\Enums\StatusHasil;
use App\Exceptions\AturanBisnisException;
use App\Models\Gelombang;
use App\Models\Pendaftaran;
use App\Models\RiwayatStatus;
use App\Models\User;
use App\Support\Catat;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat yang mengubah `pendaftaran.status_hasil`.
 *
 * Semua aturan perpindahan dari `docs/aturan-sistem.md` bagian 4 ditegakkan
 * di sini, di dalam transaksi. Pemanggil (controller) hanya menyiapkan
 * masukan dan menampilkan pesan galat.
 */
class StatusHasilService
{
    public function __construct(
        private readonly PengumumanService $pengumuman,
        private readonly KuotaService $kuota,
        private readonly BerkasService $berkas,
    ) {}

    /**
     * Status tujuan yang boleh dipilih sekarang, lengkap dengan alasan bila
     * tombolnya harus dinonaktifkan di tampilan.
     *
     * @return array<int, array{status: StatusHasil, label: string, aktif: bool, alasan: string|null}>
     */
    public function pilihan(Pendaftaran $pendaftaran, User $pelaku): array
    {
        $pendaftaran->loadMissing('gelombang');

        $dari = $pendaftaran->status_hasil;
        $kandidat = match ($dari) {
            StatusHasil::Baru => [StatusHasil::Diverifikasi, StatusHasil::PerluPerbaikan],
            StatusHasil::Diverifikasi => [
                StatusHasil::Diterima,
                StatusHasil::Ditolak,
                StatusHasil::Cadangan,
                StatusHasil::PerluPerbaikan,
            ],
            StatusHasil::Cadangan => [StatusHasil::Diterima, StatusHasil::Ditolak],
            StatusHasil::Diterima, StatusHasil::Ditolak => [
                StatusHasil::Diterima,
                StatusHasil::Ditolak,
                StatusHasil::Cadangan,
            ],
            default => [],
        };

        $diumumkan = $this->pengumuman->sudahDiumumkan($pendaftaran);
        $hasil = [];

        foreach ($kandidat as $tujuan) {
            if ($tujuan === $dari) {
                continue;
            }

            if ($this->hasilAkhir($dari) && $diumumkan && $pelaku->peran !== PeranPengguna::Admin) {
                continue;
            }

            $alasan = null;

            if ($tujuan === StatusHasil::Diterima && $this->kuota->penuh($pendaftaran->gelombang)) {
                $alasan = 'Kuota penuh, pilih Cadangan atau minta admin menaikkan kuota.';
            }

            $hasil[] = [
                'status' => $tujuan,
                'label' => $tujuan->label(),
                'aktif' => $alasan === null,
                'alasan' => $alasan,
            ];
        }

        return $hasil;
    }

    /**
     * Ubah status hasil dengan seluruh aturan bisnis.
     *
     * @throws AturanBisnisException bila perpindahan tidak diizinkan
     */
    public function ubah(
        User $pelaku,
        Pendaftaran $pendaftaran,
        StatusHasil $tujuan,
        ?string $catatan = null,
        bool $konfirmasiBerkas = false,
    ): Pendaftaran {
        $this->pastikanStafSekolah($pelaku, $pendaftaran);

        $catatan = trim((string) $catatan);

        if ($tujuan === StatusHasil::PerluPerbaikan && $catatan === '') {
            throw new AturanBisnisException(
                'Tuliskan catatan perbaikan untuk walisantri. Catatan ini akan tampil di akun mereka.'
            );
        }

        $dari = $pendaftaran->status_hasil;
        $pilihan = collect($this->pilihan($pendaftaran, $pelaku))
            ->keyBy(fn (array $baris) => $baris['status']->value);

        if (! $pilihan->has($tujuan->value)) {
            throw new AturanBisnisException(
                'Perpindahan dari status "'.$dari?->label().'" ke "'.$tujuan->label().'" tidak diizinkan.'
            );
        }

        /** @var array{aktif: bool, alasan: string|null} $info */
        $info = $pilihan->get($tujuan->value);

        if (! $info['aktif'] && $tujuan !== StatusHasil::Diterima) {
            throw new AturanBisnisException((string) $info['alasan']);
        }

        if ($tujuan === StatusHasil::Diverifikasi) {
            if ($this->berkas->adaPerluPerbaikan($pendaftaran)) {
                throw new AturanBisnisException(
                    'Masih ada berkas yang ditandai perlu perbaikan. Minta walisantri mengunggah ulang dahulu.'
                );
            }

            if (! $konfirmasiBerkas && $this->berkas->wajibBelumSiap($pendaftaran) > 0) {
                throw new AturanBisnisException(
                    'Masih ada berkas wajib yang belum diunggah atau belum diperiksa. Centang konfirmasi untuk melanjutkan.'
                );
            }
        }

        $diumumkan = $this->pengumuman->sudahDiumumkan($pendaftaran);
        $hasilAkhir = $this->hasilAkhir($dari);

        if ($hasilAkhir && $diumumkan) {
            if ($pelaku->peran !== PeranPengguna::Admin) {
                throw new AturanBisnisException(
                    'Hasil sudah diumumkan. Perubahan hasil hanya dapat dilakukan admin sekolah dengan catatan.'
                );
            }

            if ($catatan === '') {
                throw new AturanBisnisException(
                    'Hasil sudah diumumkan. Tuliskan alasan perubahan sebelum menyimpan.'
                );
            }
        }

        return DB::transaction(function () use ($pelaku, $pendaftaran, $tujuan, $catatan) {
            /** @var Pendaftaran $segar */
            $segar = Pendaftaran::query()
                ->tanpaSekolah()
                ->whereKey($pendaftaran->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($segar->status_hasil !== $pendaftaran->status_hasil) {
                throw new AturanBisnisException(
                    'Status pendaftaran baru saja berubah oleh pengguna lain. Muat ulang halaman lalu coba lagi.'
                );
            }

            if ($tujuan === StatusHasil::Diterima) {
                $this->pastikanKuotaTersedia($segar);
            }

            $dari = $segar->status_hasil;

            $segar->forceFill([
                'status_hasil' => $tujuan,
                'diputuskan_oleh' => $pelaku->id,
                'diputuskan_pada' => now(),
            ])->save();

            RiwayatStatus::create([
                'pendaftaran_id' => $segar->id,
                'jenis' => JenisRiwayat::Hasil,
                'dari' => $dari?->value,
                'ke' => $tujuan->value,
                'oleh' => $pelaku->id,
                'catatan' => $catatan !== '' ? $catatan : 'Status diubah oleh panitia.',
            ]);

            Catat::aksi('ubah_status_hasil', $segar, [
                'dari' => $dari?->value,
                'ke' => $tujuan->value,
            ], $segar->sekolah_id);

            return $segar->refresh();
        });
    }

    /**
     * Catatan perbaikan terakhir yang boleh dibaca walisantri.
     */
    public function catatanPerbaikan(Pendaftaran $pendaftaran): ?string
    {
        $baris = RiwayatStatus::query()
            ->where('pendaftaran_id', $pendaftaran->id)
            ->where('jenis', JenisRiwayat::Hasil)
            ->where('ke', StatusHasil::PerluPerbaikan->value)
            ->orderByDesc('dibuat_pada')
            ->orderByDesc('id')
            ->first();

        return $baris?->catatan;
    }

    public static function hasilAkhir(?StatusHasil $status): bool
    {
        return in_array($status, [
            StatusHasil::Diterima,
            StatusHasil::Ditolak,
            StatusHasil::Cadangan,
        ], true);
    }

    private function pastikanKuotaTersedia(Pendaftaran $pendaftaran): void
    {
        /** @var Gelombang $gelombang */
        $gelombang = Gelombang::query()
            ->tanpaSekolah()
            ->whereKey($pendaftaran->gelombang_id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($gelombang->kuota === null) {
            return;
        }

        if ($this->kuota->terisi($gelombang) >= (int) $gelombang->kuota) {
            throw new AturanBisnisException(
                'Kuota penuh, pilih Cadangan atau minta admin menaikkan kuota.'
            );
        }
    }

    private function pastikanStafSekolah(User $pelaku, Pendaftaran $pendaftaran): void
    {
        if (! in_array($pelaku->peran, [PeranPengguna::Panitia, PeranPengguna::Admin], true)) {
            throw new AuthorizationException('Hanya panitia atau admin yang boleh memutuskan hasil.');
        }

        if (! $pelaku->sekolah_id || (int) $pelaku->sekolah_id !== (int) $pendaftaran->sekolah_id) {
            throw (new ModelNotFoundException)->setModel(Pendaftaran::class, [$pendaftaran->getKey()]);
        }
    }
}
