<?php

namespace App\Services;

use App\Enums\JenisRiwayat;
use App\Enums\PeranPengguna;
use App\Enums\StatusHasil;
use App\Enums\StatusMurid;
use App\Enums\StatusPembayaran;
use App\Exceptions\AturanBisnisException;
use App\Models\DaftarUlang;
use App\Models\Pendaftaran;
use App\Models\RiwayatStatus;
use App\Models\User;
use App\Support\Catat;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Pencatatan daftar ulang, wawancara, dan status murid.
 *
 * `catatan_wawancara`, `kekurangan`, dan seluruh data di sini tidak pernah
 * dibaca oleh tampilan walisantri.
 */
class DaftarUlangService
{
    /**
     * @param  array{hadir?: bool, kekurangan?: string|null, catatan_wawancara?: string|null, selesai?: bool}  $data
     */
    public function catat(User $pelaku, Pendaftaran $pendaftaran, array $data): DaftarUlang
    {
        $this->pastikanStafSekolah($pelaku, $pendaftaran);

        if ($pendaftaran->status_hasil !== StatusHasil::Diterima) {
            throw new AturanBisnisException('Daftar ulang hanya dapat dicatat untuk pendaftar yang sudah Diterima.');
        }

        $adaPembayaran = $pendaftaran->pembayaran()
            ->where('status', StatusPembayaran::Disetujui)
            ->exists();

        if (! $adaPembayaran) {
            throw new AturanBisnisException(
                'Belum ada pembayaran yang disetujui. Setujui bukti transfer atau catat pembayaran tunai terlebih dahulu.'
            );
        }

        return DB::transaction(function () use ($pelaku, $pendaftaran, $data) {
            /** @var DaftarUlang $baris */
            $baris = DaftarUlang::query()->firstOrNew(['pendaftaran_id' => $pendaftaran->id]);

            $sebelumnyaSelesai = (bool) $baris->selesai;
            $selesai = (bool) ($data['selesai'] ?? false);
            $catatan = trim((string) ($data['catatan'] ?? ''));

            if ($sebelumnyaSelesai && ! $selesai) {
                $this->pastikanAdminMembatalkan($pelaku, $catatan);
            }

            $baris->fill([
                'hadir' => (bool) ($data['hadir'] ?? false),
                'kekurangan' => $this->teksAtauNull($data['kekurangan'] ?? null),
                'catatan_wawancara' => $this->teksAtauNull($data['catatan_wawancara'] ?? null),
                'selesai' => $selesai,
                'dicatat_oleh' => $pelaku->id,
            ])->save();

            if ($selesai && $pendaftaran->status_murid !== StatusMurid::Resmi) {
                $pendaftaran->forceFill(['status_murid' => StatusMurid::Resmi])->save();

                RiwayatStatus::create([
                    'pendaftaran_id' => $pendaftaran->id,
                    'jenis' => JenisRiwayat::Murid,
                    'dari' => $pendaftaran->status_murid?->value,
                    'ke' => StatusMurid::Resmi->value,
                    'oleh' => $pelaku->id,
                    'catatan' => 'Daftar ulang selesai.',
                ]);
            } elseif (! $selesai && $sebelumnyaSelesai && $pendaftaran->status_murid === StatusMurid::Resmi) {
                $pendaftaran->forceFill(['status_murid' => null])->save();

                RiwayatStatus::create([
                    'pendaftaran_id' => $pendaftaran->id,
                    'jenis' => JenisRiwayat::Murid,
                    'dari' => StatusMurid::Resmi->value,
                    'ke' => null,
                    'oleh' => $pelaku->id,
                    'catatan' => $catatan,
                ]);
            }

            Catat::aksi(
                $sebelumnyaSelesai && ! $selesai ? 'batal_daftar_ulang' : 'catat_daftar_ulang',
                $baris,
                ['selesai' => $selesai],
                $pendaftaran->sekolah_id,
            );

            return $baris->refresh();
        });
    }

    /**
     * Hanya admin yang boleh membatalkan daftar ulang yang sudah selesai.
     */
    public function batalSelesai(User $pelaku, Pendaftaran $pendaftaran, string $catatan): DaftarUlang
    {
        $this->pastikanStafSekolah($pelaku, $pendaftaran);
        $this->pastikanAdminMembatalkan($pelaku, $catatan);

        /** @var DaftarUlang|null $baris */
        $baris = DaftarUlang::query()->where('pendaftaran_id', $pendaftaran->id)->first();

        if (! $baris || ! $baris->selesai) {
            throw new AturanBisnisException('Daftar ulang ini belum ditandai selesai.');
        }

        return DB::transaction(function () use ($pelaku, $pendaftaran, $baris, $catatan) {
            $baris->fill(['selesai' => false, 'dicatat_oleh' => $pelaku->id])->save();

            if ($pendaftaran->status_murid === StatusMurid::Resmi) {
                $pendaftaran->forceFill(['status_murid' => null])->save();

                RiwayatStatus::create([
                    'pendaftaran_id' => $pendaftaran->id,
                    'jenis' => JenisRiwayat::Murid,
                    'dari' => StatusMurid::Resmi->value,
                    'ke' => null,
                    'oleh' => $pelaku->id,
                    'catatan' => $catatan,
                ]);
            }

            Catat::aksi('batal_daftar_ulang', $baris, ['catatan' => $catatan], $pendaftaran->sekolah_id);

            return $baris->refresh();
        });
    }

    /**
     * Catat mengundurkan diri. Membebaskan kuota.
     */
    public function mengundurkanDiri(User $pelaku, Pendaftaran $pendaftaran, ?string $catatan = null): Pendaftaran
    {
        $this->pastikanStafSekolah($pelaku, $pendaftaran);

        if (! in_array($pendaftaran->status_hasil, [StatusHasil::Diterima], true)
            && $pendaftaran->status_murid !== StatusMurid::Resmi) {
            throw new AturanBisnisException(
                'Mengundurkan diri hanya dapat dicatat untuk pendaftar yang Diterima atau sudah menjadi murid resmi.'
            );
        }

        if ($pendaftaran->status_murid === StatusMurid::MengundurkanDiri) {
            throw new AturanBisnisException('Pendaftar ini sudah tercatat mengundurkan diri.');
        }

        $catatan = trim((string) $catatan);

        return DB::transaction(function () use ($pelaku, $pendaftaran, $catatan) {
            $dari = $pendaftaran->status_murid;
            $pendaftaran->forceFill(['status_murid' => StatusMurid::MengundurkanDiri])->save();

            RiwayatStatus::create([
                'pendaftaran_id' => $pendaftaran->id,
                'jenis' => JenisRiwayat::Murid,
                'dari' => $dari?->value,
                'ke' => StatusMurid::MengundurkanDiri->value,
                'oleh' => $pelaku->id,
                'catatan' => $catatan !== '' ? $catatan : 'Dicatat panitia.',
            ]);

            Catat::aksi('mengundurkan_diri', $pendaftaran, [], $pendaftaran->sekolah_id);

            return $pendaftaran->refresh();
        });
    }

    private function pastikanAdminMembatalkan(User $pelaku, string $catatan): void
    {
        if ($pelaku->peran !== PeranPengguna::Admin) {
            throw new AturanBisnisException('Hanya admin sekolah yang boleh membatalkan daftar ulang yang sudah selesai.');
        }

        if (trim($catatan) === '') {
            throw new AturanBisnisException('Tuliskan alasan pembatalan daftar ulang.');
        }
    }

    private function pastikanStafSekolah(User $pelaku, Pendaftaran $pendaftaran): void
    {
        if (! in_array($pelaku->peran, [PeranPengguna::Panitia, PeranPengguna::Admin], true)) {
            throw new AuthorizationException('Hanya panitia atau admin yang boleh mencatat daftar ulang.');
        }

        if (! $pelaku->sekolah_id || (int) $pelaku->sekolah_id !== (int) $pendaftaran->sekolah_id) {
            throw (new ModelNotFoundException)->setModel(Pendaftaran::class, [$pendaftaran->getKey()]);
        }
    }

    private function teksAtauNull(mixed $nilai): ?string
    {
        $teks = trim((string) $nilai);

        return $teks === '' ? null : $teks;
    }
}
