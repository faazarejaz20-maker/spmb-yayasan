<?php

namespace App\Enums;

enum StatusHasil: string
{
    case Baru = 'Baru';
    case Diverifikasi = 'Diverifikasi';
    case PerluPerbaikan = 'Perlu perbaikan';
    case Diterima = 'Diterima';
    case Ditolak = 'Ditolak';
    case Cadangan = 'Cadangan';

    public function label(): string
    {
        return $this->value;
    }
}
