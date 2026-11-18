<?php

namespace App\Enums;

enum TipeBiaya: string
{
    case Tetap = 'tetap';
    case Pilihan = 'pilihan';

    public function label(): string
    {
        return match ($this) {
            self::Tetap => 'Tetap',
            self::Pilihan => 'Pilihan',
        };
    }
}
