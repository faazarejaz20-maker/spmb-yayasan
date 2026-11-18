<?php

namespace App\Enums;

enum ModePenerimaan: string
{
    case Seleksi = 'seleksi';
    case TanpaSeleksi = 'tanpa_seleksi';

    public function label(): string
    {
        return match ($this) {
            self::Seleksi => 'Seleksi',
            self::TanpaSeleksi => 'Tanpa seleksi',
        };
    }
}
