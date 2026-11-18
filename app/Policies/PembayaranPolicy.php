<?php

namespace App\Policies;

use App\Enums\PeranPengguna;
use App\Models\Pembayaran;
use App\Models\User;

class PembayaranPolicy
{
    public function lihat(User $pengguna, Pembayaran $pembayaran): bool
    {
        return $this->milik($pengguna, $pembayaran) || $this->stafSekolah($pengguna, $pembayaran);
    }

    public function proses(User $pengguna, Pembayaran $pembayaran): bool
    {
        return $this->stafSekolah($pengguna, $pembayaran);
    }

    public function unggah(User $pengguna, \App\Models\Pendaftaran $pendaftaran): bool
    {
        return app(\App\Services\PembayaranService::class)->bolehBayar($pengguna, $pendaftaran);
    }

    private function stafSekolah(User $pengguna, Pembayaran $pembayaran): bool
    {
        if (! in_array($pengguna->peran, [PeranPengguna::Panitia, PeranPengguna::Admin], true)) {
            return false;
        }

        if (! $pengguna->sekolah_id) {
            return false;
        }

        $pembayaran->loadMissing('pendaftaran');

        return (int) $pengguna->sekolah_id === (int) $pembayaran->pendaftaran->sekolah_id;
    }

    private function milik(User $pengguna, Pembayaran $pembayaran): bool
    {
        if ($pengguna->peran !== PeranPengguna::Walisantri) {
            return false;
        }

        return $pembayaran->pendaftaran()
            ->whereHas('anak', fn ($query) => $query->where('user_id', $pengguna->id))
            ->exists();
    }
}
