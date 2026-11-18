<?php

namespace App\Support;

class Samarkan
{
    public static function nik(?string $nik): string
    {
        if (blank($nik)) {
            return '—';
        }

        $angka = preg_replace('/\D+/', '', $nik);

        if (strlen($angka) <= 8) {
            return str_repeat('*', strlen($angka));
        }

        return substr($angka, 0, 4).str_repeat('*', strlen($angka) - 8).substr($angka, -4);
    }

    public static function nomorHp(?string $nomor): string
    {
        return NomorHp::samarkan($nomor);
    }
}
