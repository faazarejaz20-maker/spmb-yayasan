<?php

namespace App\Services;

use App\Enums\PeranPengguna;
use App\Enums\StatusHasil;
use App\Exceptions\AturanBisnisException;
use App\Models\Gelombang;
use App\Models\Pendaftaran;
use App\Models\Pengumuman;
use App\Models\User;
use App\Support\Catat;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat yang menjawab apakah hasil satu pendaftaran sudah boleh
 * dilihat walisantri dan satu-satunya tempat yang membuat baris `pengumuman`.
 */
class PengumumanService
{
    public function sudahDiumumkan(Pendaftaran $pendaftaran): bool
    {
        return Pengumuman::query()
            ->tanpaSekolah()
            ->where('sekolah_id', $pendaftaran->sekolah_id)
            ->where('gelombang_id', $pendaftaran->gelombang_id)
            ->exists();
    }

    public function sudahDiumumkanGelombang(int $sekolahId, int $gelombangId): bool
    {
        return Pengumuman::query()
            ->tanpaSekolah()
            ->where('sekolah_id', $sekolahId)
            ->where('gelombang_id', $gelombangId)
            ->exists();
    }

    /**
     * Ringkasan jumlah per status untuk layar konfirmasi. Hanya angka.
     *
     * @return array{total: int, status: array<string, int>, belum_final: int}
     */
    public function ringkasan(Gelombang $gelombang): array
    {
        $baris = Pendaftaran::query()
            ->tanpaSekolah()
            ->where('gelombang_id', $gelombang->id)
            ->whereNotNull('dikirim_pada')
            ->selectRaw('status_hasil, count(*) as jumlah')
            ->groupBy('status_hasil')
            ->pluck('jumlah', 'status_hasil');

        $status = [];

        foreach (StatusHasil::cases() as $kasus) {
            $status[$kasus->value] = (int) ($baris[$kasus->value] ?? 0);
        }

        return [
            'total' => array_sum($status),
            'status' => $status,
            'belum_final' => $status[StatusHasil::Baru->value]
                + $status[StatusHasil::Diverifikasi->value]
                + $status[StatusHasil::PerluPerbaikan->value],
        ];
    }

    public function umumkan(User $admin, Gelombang $gelombang): Pengumuman
    {
        if ($admin->peran !== PeranPengguna::Admin || ! $admin->sekolah_id) {
            throw new AturanBisnisException('Hanya admin sekolah yang dapat mengumumkan hasil.');
        }

        if ((int) $admin->sekolah_id !== (int) $gelombang->sekolah_id) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(Gelombang::class, [$gelombang->getKey()]);
        }

        return DB::transaction(function () use ($admin, $gelombang) {
            /** @var Gelombang $segar */
            $segar = Gelombang::query()
                ->tanpaSekolah()
                ->whereKey($gelombang->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->sudahDiumumkanGelombang((int) $admin->sekolah_id, $segar->id)) {
                throw new AturanBisnisException(
                    'Hasil gelombang ini sudah diumumkan. Pengumuman tidak dapat dibuat dua kali.'
                );
            }

            $ringkasan = $this->ringkasan($segar);

            try {
                $pengumuman = Pengumuman::create([
                    'sekolah_id' => $admin->sekolah_id,
                    'gelombang_id' => $segar->id,
                    'diumumkan_oleh' => $admin->id,
                    'diumumkan_pada' => now(),
                    'teks_pesan' => $this->teksPesan($segar),
                ]);
            } catch (QueryException $galat) {
                if (in_array((string) $galat->getCode(), ['23000', '19'], true)) {
                    throw new AturanBisnisException(
                        'Hasil gelombang ini baru saja diumumkan oleh pengguna lain. Muat ulang halaman.'
                    );
                }

                throw $galat;
            }

            Catat::aksi('umumkan_hasil', $pengumuman, [
                'gelombang_id' => $segar->id,
                'gelombang' => $segar->nama,
                'jumlah' => $ringkasan,
            ], $admin->sekolah_id);

            return $pengumuman->refresh();
        });
    }

    /**
     * Teks siap salin. Tidak memuat nama anak, nomor peserta, atau hasil.
     */
    public function teksPesan(Gelombang $gelombang): string
    {
        $gelombang->loadMissing('sekolah');

        return "Assalamu'alaikum, hasil SPMB "
            .($gelombang->sekolah?->nama ?? 'sekolah')
            .' '.$gelombang->nama
            .' sudah diumumkan. Silakan masuk ke '.url('/')
            .' untuk melihat hasil anak Bapak/Ibu.';
    }
}
