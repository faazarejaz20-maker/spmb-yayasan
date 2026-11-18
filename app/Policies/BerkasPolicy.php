<?php

namespace App\Policies;

use App\Enums\PeranPengguna;
use App\Models\Berkas;
use App\Models\User;

class BerkasPolicy
{
    public function lihat(User $pengguna, Berkas $berkas): bool
    {
        return $this->milik($pengguna, $berkas) || $this->stafSekolah($pengguna, $berkas);
    }

    public function hapus(User $pengguna, Berkas $berkas): bool
    {
        if (! $this->milik($pengguna, $berkas)) {
            return false;
        }

        $berkas->loadMissing('pendaftaran');

        return $berkas->pendaftaran->bolehDiubah();
    }

    public function unggah(User $pengguna, Berkas $berkas): bool
    {
        if ($this->stafSekolah($pengguna, $berkas)) {
            return true;
        }

        return $this->hapus($pengguna, $berkas);
    }

    public function periksa(User $pengguna, Berkas $berkas): bool
    {
        return $this->stafSekolah($pengguna, $berkas);
    }

    private function stafSekolah(User $pengguna, Berkas $berkas): bool
    {
        if (! in_array($pengguna->peran, [PeranPengguna::Panitia, PeranPengguna::Admin], true)) {
            return false;
        }

        if (! $pengguna->sekolah_id) {
            return false;
        }

        $berkas->loadMissing('pendaftaran');

        return (int) $pengguna->sekolah_id === (int) $berkas->pendaftaran->sekolah_id;
    }

    private function milik(User $pengguna, Berkas $berkas): bool
    {
        if ($pengguna->peran !== PeranPengguna::Walisantri) {
            return false;
        }

        return $berkas->pendaftaran()
            ->whereHas('anak', fn ($query) => $query->where('user_id', $pengguna->id))
            ->exists();
    }
}
