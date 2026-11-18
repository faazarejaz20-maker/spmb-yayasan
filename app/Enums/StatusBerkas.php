<?php

namespace App\Enums;

enum StatusBerkas: string
{
    case Menunggu = 'menunggu';
    case Sesuai = 'sesuai';
    case PerluPerbaikan = 'perlu_perbaikan';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu',
            self::Sesuai => 'Sesuai',
            self::PerluPerbaikan => 'Perlu perbaikan',
        };
    }
}
