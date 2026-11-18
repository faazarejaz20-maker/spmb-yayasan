<?php

namespace App\Policies;

use App\Enums\PeranPengguna;
use App\Models\Pendaftaran;
use App\Models\User;

/**
 * Aturan siapa yang boleh mengelola satu akun walisantri.
 *
 * Staf hanya boleh mengelola akun `walisantri` yang (a) dibuat oleh staf
 * sekolahnya, atau (b) punya minimal satu pendaftaran terkirim ke sekolahnya.
 * Akun staf tidak pernah dikelola dari sini.
 */
class AkunWalisantriPolicy
{
    public function kelola(User $staf, User $walisantri): bool
    {
        if (! in_array($staf->peran, [PeranPengguna::Panitia, PeranPengguna::Admin], true)) {
            return false;
        }

        if (! $staf->sekolah_id) {
            return false;
        }

        if ($walisantri->peran !== PeranPengguna::Walisantri) {
            return false;
        }

        if ($walisantri->dibuat_oleh !== null) {
            $pembuat = User::query()->find($walisantri->dibuat_oleh);

            if ($pembuat
                && in_array($pembuat->peran, [PeranPengguna::Panitia, PeranPengguna::Admin], true)
                && (int) $pembuat->sekolah_id === (int) $staf->sekolah_id) {
                return true;
            }
        }

        return Pendaftaran::query()
            ->tanpaSekolah()
            ->where('sekolah_id', $staf->sekolah_id)
            ->whereNotNull('dikirim_pada')
            ->whereHas('anak', fn ($query) => $query->where('user_id', $walisantri->id))
            ->exists();
    }
}
