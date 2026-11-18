<?php

namespace App\Policies;

use App\Enums\PeranPengguna;
use App\Models\User;

/**
 * Satu-satunya policy untuk seluruh pengaturan sekolah di panel admin.
 *
 * Admin hanya boleh mengubah sekolahnya sendiri. `sekolah_id` tidak pernah
 * berasal dari formulir; pemanggil mengambilnya dari akun yang masuk.
 */
class PengaturanSekolahPolicy
{
    public function kelola(User $pengguna, ?int $sekolahId): bool
    {
        return $pengguna->peran === PeranPengguna::Admin
            && $pengguna->sekolah_id !== null
            && $sekolahId !== null
            && (int) $pengguna->sekolah_id === (int) $sekolahId;
    }

    public function kelolaSekolah(User $pengguna, int $sekolahId): bool
    {
        return $this->kelola($pengguna, $sekolahId);
    }
}
