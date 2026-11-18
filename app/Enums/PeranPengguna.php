<?php

namespace App\Enums;

enum PeranPengguna: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Panitia = 'panitia';
    case Yayasan = 'yayasan';
    case Walisantri = 'walisantri';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super admin',
            self::Admin => 'Admin sekolah',
            self::Panitia => 'Panitia',
            self::Yayasan => 'Yayasan',
            self::Walisantri => 'Walisantri',
        };
    }

    /** Apakah peran ini terikat pada satu sekolah (admin atau panitia). */
    public function terikatSekolah(): bool
    {
        return in_array($this, [self::Admin, self::Panitia], true);
    }

    /** Apakah peran ini staf yang memakai masa sesi pendek (120 menit). */
    public function staf(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin, self::Panitia, self::Yayasan], true);
    }
}
