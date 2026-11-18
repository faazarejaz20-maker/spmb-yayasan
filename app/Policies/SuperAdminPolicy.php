<?php

namespace App\Policies;

use App\Enums\PeranPengguna;
use App\Models\User;

/**
 * Kewenangan super admin. Super admin hanya mengelola sekolah dan akun staf;
 * tidak ada kemampuan apa pun yang membuka data pendaftar per orang.
 */
class SuperAdminPolicy
{
    public function kelola(User $pengguna): bool
    {
        return $pengguna->peran === PeranPengguna::SuperAdmin && $pengguna->aktif;
    }

    public function kelolaAkunStaf(User $pengguna, User $target): bool
    {
        if (! $this->kelola($pengguna)) {
            return false;
        }

        return in_array($target->peran, [PeranPengguna::Admin, PeranPengguna::Yayasan], true);
    }
}
