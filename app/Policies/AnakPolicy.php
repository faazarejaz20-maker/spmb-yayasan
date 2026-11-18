<?php

namespace App\Policies;

use App\Enums\PeranPengguna;
use App\Models\Anak;
use App\Models\User;

class AnakPolicy
{
    public function lihat(User $pengguna, Anak $anak): bool
    {
        return $this->milik($pengguna, $anak);
    }

    public function ubah(User $pengguna, Anak $anak): bool
    {
        return $this->milik($pengguna, $anak);
    }

    private function milik(User $pengguna, Anak $anak): bool
    {
        return $pengguna->peran === PeranPengguna::Walisantri
            && (int) $anak->user_id === (int) $pengguna->id;
    }
}
