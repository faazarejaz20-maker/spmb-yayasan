<?php

namespace App\Enums;

enum StatusMurid: string
{
    case Resmi = 'Resmi';
    case MengundurkanDiri = 'Mengundurkan diri';

    public function label(): string
    {
        return $this->value;
    }
}
