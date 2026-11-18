<?php

namespace App\Policies;

use App\Enums\PeranPengguna;
use App\Enums\StatusHasil;
use App\Models\Pendaftaran;
use App\Models\User;
use App\Services\StatusTampilService;

class PendaftaranPolicy
{
    public function lihat(User $pengguna, Pendaftaran $pendaftaran): bool
    {
        return $this->milik($pengguna, $pendaftaran) || $this->stafSekolah($pengguna, $pendaftaran);
    }

    public function ubah(User $pengguna, Pendaftaran $pendaftaran): bool
    {
        if ($this->stafSekolah($pengguna, $pendaftaran)) {
            return true;
        }

        return $this->milik($pengguna, $pendaftaran) && $pendaftaran->bolehDiubah();
    }

    public function kirim(User $pengguna, Pendaftaran $pendaftaran): bool
    {
        return $this->milik($pengguna, $pendaftaran) && $pendaftaran->masihDraf();
    }

    public function kirimPerbaikan(User $pengguna, Pendaftaran $pendaftaran): bool
    {
        return $this->milik($pengguna, $pendaftaran)
            && $pendaftaran->status_hasil === StatusHasil::PerluPerbaikan;
    }

    public function lihatHasil(User $pengguna, Pendaftaran $pendaftaran): bool
    {
        return $this->milik($pengguna, $pendaftaran);
    }

    /**
     * Panitia atau admin sekolah terkait boleh memverifikasi, memutuskan,
     * mencatat pembayaran, dan mencatat daftar ulang.
     */
    public function kelola(User $pengguna, Pendaftaran $pendaftaran): bool
    {
        return $this->stafSekolah($pengguna, $pendaftaran);
    }

    public function isiTahap2(User $pengguna, Pendaftaran $pendaftaran): bool
    {
        if (! $this->milik($pengguna, $pendaftaran)) {
            return false;
        }

        if ($pendaftaran->status_hasil !== StatusHasil::Diterima) {
            return false;
        }

        if (! app(StatusTampilService::class)->sudahDiumumkan($pendaftaran)) {
            return false;
        }

        return ! $pendaftaran->daftarUlang()->where('selesai', true)->exists();
    }

    public function bayar(User $pengguna, Pendaftaran $pendaftaran): bool
    {
        if (! $this->milik($pengguna, $pendaftaran)) {
            return false;
        }

        return $pendaftaran->status_hasil === StatusHasil::Diterima
            && app(StatusTampilService::class)->sudahDiumumkan($pendaftaran);
    }

    private function stafSekolah(User $pengguna, Pendaftaran $pendaftaran): bool
    {
        return in_array($pengguna->peran, [PeranPengguna::Panitia, PeranPengguna::Admin], true)
            && $pengguna->sekolah_id
            && (int) $pengguna->sekolah_id === (int) $pendaftaran->sekolah_id;
    }

    private function milik(User $pengguna, Pendaftaran $pendaftaran): bool
    {
        if ($pengguna->peran !== PeranPengguna::Walisantri) {
            return false;
        }

        return $pendaftaran->anak()
            ->where('user_id', $pengguna->id)
            ->exists();
    }
}
