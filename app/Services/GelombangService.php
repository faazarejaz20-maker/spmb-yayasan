<?php

namespace App\Services;

use App\Enums\PeranPengguna;
use App\Enums\StatusGelombang;
use App\Exceptions\AturanBisnisException;
use App\Models\Gelombang;
use App\Models\Pengumuman;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Support\Catat;
use Illuminate\Support\Facades\DB;

class GelombangService
{
    public function __construct(private readonly KuotaService $kuota) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function simpan(User $admin, array $data, ?Gelombang $gelombang = null): Gelombang
    {
        $this->pastikanAdmin($admin);

        $tahun = TahunAjaran::query()->where('aktif', true)->first();

        if (! $tahun) {
            throw new AturanBisnisException('Belum ada tahun ajaran aktif. Hubungi super admin.');
        }

        return DB::transaction(function () use ($admin, $data, $gelombang, $tahun) {
            $baru = $gelombang === null;

            if ($baru) {
                $segar = new Gelombang;
            } else {
                /** @var Gelombang $segar */
                $segar = Gelombang::query()
                    ->tanpaSekolah()
                    ->whereKey($gelombang->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->pastikanMilik($admin, $segar);
            }

            $lama = $baru ? null : $this->ringkasan($segar);

            $kuotaBaru = array_key_exists('kuota', $data) && $data['kuota'] !== null && $data['kuota'] !== ''
                ? (int) $data['kuota']
                : null;

            if (! $baru && $kuotaBaru !== null && $this->kuota->terisi($segar) > $kuotaBaru) {
                throw new AturanBisnisException(
                    'Kuota tidak boleh lebih kecil dari jumlah Diterima saat ini ('.$this->kuota->terisi($segar).' orang).'
                );
            }

            $segar->fill([
                'nama' => trim((string) ($data['nama'] ?? '')),
                'tanggal_buka' => ($data['tanggal_buka'] ?? '') ?: null,
                'tanggal_tutup' => ($data['tanggal_tutup'] ?? '') ?: null,
                'kuota' => $kuotaBaru,
                'status' => $data['status'] ?? StatusGelombang::Draf->value,
                'tautan_grup_wa' => filled($data['tautan_grup_wa'] ?? null) ? trim((string) $data['tautan_grup_wa']) : null,
            ]);

            if ($baru) {
                $segar->forceFill([
                    'sekolah_id' => $admin->sekolah_id,
                    'tahun_ajaran_id' => $tahun->id,
                ]);
            }

            $segar->save();

            Catat::aksi($baru ? 'buat_gelombang' : 'ubah_gelombang', $segar, [
                'lama' => $lama,
                'baru' => $this->ringkasan($segar),
            ], $admin->sekolah_id);

            return $segar->refresh();
        });
    }

    public function hapus(User $admin, Gelombang $gelombang): void
    {
        $this->pastikanAdmin($admin);
        $this->pastikanMilik($admin, $gelombang);

        if ($gelombang->pendaftaran()->exists()) {
            throw new AturanBisnisException(
                'Gelombang ini sudah memiliki pendaftaran sehingga tidak dapat dihapus. Tutup gelombang bila tidak dipakai lagi.'
            );
        }

        if (Pengumuman::query()->tanpaSekolah()->where('gelombang_id', $gelombang->id)->exists()) {
            throw new AturanBisnisException('Gelombang ini sudah pernah diumumkan sehingga tidak dapat dihapus.');
        }

        $ringkasan = $this->ringkasan($gelombang);

        $gelombang->delete();

        Catat::aksi('hapus_gelombang', null, [
            'gelombang' => $ringkasan,
        ], $admin->sekolah_id);
    }

    /**
     * @return array<string, mixed>
     */
    public function ringkasan(Gelombang $gelombang): array
    {
        return [
            'nama' => $gelombang->nama,
            'tanggal_buka' => $gelombang->tanggal_buka?->toDateString(),
            'tanggal_tutup' => $gelombang->tanggal_tutup?->toDateString(),
            'kuota' => $gelombang->kuota,
            'status' => $gelombang->status?->value,
            'tautan_grup_wa' => $gelombang->tautan_grup_wa,
        ];
    }

    private function pastikanAdmin(User $pengguna): void
    {
        if ($pengguna->peran !== PeranPengguna::Admin || ! $pengguna->sekolah_id) {
            throw new AturanBisnisException('Hanya admin sekolah yang dapat mengatur gelombang.');
        }
    }

    private function pastikanMilik(User $pengguna, Gelombang $gelombang): void
    {
        if ((int) $gelombang->sekolah_id !== (int) $pengguna->sekolah_id) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(Gelombang::class, [$gelombang->getKey()]);
        }
    }
}
