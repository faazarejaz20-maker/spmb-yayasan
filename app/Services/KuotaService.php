<?php

namespace App\Services;

use App\Enums\StatusHasil;
use App\Enums\StatusMurid;
use App\Models\Gelombang;
use App\Models\Pendaftaran;

/**
 * Hitungan kuota per gelombang.
 *
 * Kuota terisi = pendaftaran dengan `status_hasil = Diterima` dan
 * `status_murid` bukan `Mengundurkan diri`. `kuota` kosong berarti tanpa batas.
 */
class KuotaService
{
    public function terisi(Gelombang $gelombang): int
    {
        return Pendaftaran::query()
            ->tanpaSekolah()
            ->where('gelombang_id', $gelombang->id)
            ->where('status_hasil', StatusHasil::Diterima)
            ->where(function ($query) {
                $query->whereNull('status_murid')
                    ->orWhere('status_murid', '!=', StatusMurid::MengundurkanDiri);
            })
            ->count();
    }

    public function penuh(Gelombang $gelombang): bool
    {
        if ($gelombang->kuota === null) {
            return false;
        }

        return $this->terisi($gelombang) >= (int) $gelombang->kuota;
    }

    public function sisa(Gelombang $gelombang): ?int
    {
        if ($gelombang->kuota === null) {
            return null;
        }

        return max(0, (int) $gelombang->kuota - $this->terisi($gelombang));
    }

    /**
     * Jumlah pendaftar Diterima pada satu gelombang tanpa memandang
     * pengunduran diri (untuk ringkasan dasbor).
     */
    public function diterima(Gelombang $gelombang): int
    {
        return Pendaftaran::query()
            ->tanpaSekolah()
            ->where('gelombang_id', $gelombang->id)
            ->where('status_hasil', StatusHasil::Diterima)
            ->count();
    }
}
