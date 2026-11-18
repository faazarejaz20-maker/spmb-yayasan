<?php

namespace App\Enums;

enum StatusGelombang: string
{
    case Draf = 'draf';
    case Dibuka = 'dibuka';
    case Ditutup = 'ditutup';

    public function label(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Dibuka => 'Dibuka',
            self::Ditutup => 'Ditutup',
        };
    }
}
