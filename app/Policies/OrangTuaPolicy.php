<?php

namespace App\Policies;

use App\Enums\PeranPengguna;
use App\Models\User;

class OrangTuaPolicy
{
    public function kelola(User $pengguna): bool
    {
        return $pengguna->peran === PeranPengguna::Walisantri;
    }
}
