<?php

namespace App\Services;

use App\Enums\StatusHasil;
use App\Models\Anak;
use Illuminate\Support\Str;

class DeteksiGandaService
{
    /**
     * Cocok bila anak lain (akun mana pun) punya nama yang sama setelah
     * dinormalisasi dan tanggal lahir yang sama, serta pendaftaran terkirim di
     * sekolah dan tahun ajaran yang sama dengan status bukan Ditolak.
     */
    public function adaGanda(Anak $anak, int $sekolahId, int $tahunAjaranId): bool
    {
        $nama = $this->normalisasi((string) $anak->nama_lengkap);
        $tanggal = $anak->tanggal_lahir?->toDateString();

        if ($nama === '' || $tanggal === null) {
            return false;
        }

        $kandidat = Anak::query()
            ->whereDate('tanggal_lahir', $tanggal)
            ->when($anak->exists, fn ($query) => $query->whereKeyNot($anak->getKey()))
            ->whereHas('pendaftaran', function ($query) use ($sekolahId, $tahunAjaranId) {
                $query->withoutGlobalScope('milik_sekolah')
                    ->where('sekolah_id', $sekolahId)
                    ->whereNotNull('dikirim_pada')
                    ->where('status_hasil', '!=', StatusHasil::Ditolak->value)
                    ->whereHas('gelombang', function ($gelombang) use ($tahunAjaranId) {
                        $gelombang->withoutGlobalScope('milik_sekolah')
                            ->where('tahun_ajaran_id', $tahunAjaranId);
                    });
            })
            ->get(['id', 'nama_lengkap']);

        return $kandidat->contains(
            fn (Anak $lain) => $this->normalisasi((string) $lain->nama_lengkap) === $nama
        );
    }

    public function normalisasi(string $nama): string
    {
        return Str::lower(trim(preg_replace('/\s+/u', ' ', $nama) ?? ''));
    }
}
