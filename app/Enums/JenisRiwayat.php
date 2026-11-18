<?php

namespace App\Enums;

enum JenisRiwayat: string
{
    case Hasil = 'hasil';
    case Murid = 'murid';

    public function label(): string
    {
        return match ($this) {
            self::Hasil => 'Hasil seleksi',
            self::Murid => 'Status murid',
        };
    }
}
