<?php

namespace App\Support;

use InvalidArgumentException;

class NomorHp
{
    /**
     * Normalisasi nomor HP menjadi format internasional `62...` (hanya angka).
     *
     * - Menghapus spasi, tanda hubung, dan karakter non-angka.
     * - `08...` diubah menjadi `628...`.
     * - `+62` dan `62` dipertahankan sebagai `62`.
     * - Menolak hasil yang bukan 9–14 digit.
     */
    public static function normalisasi(?string $nomor): ?string
    {
        if (blank($nomor)) {
            return null;
        }

        $angka = preg_replace('/\D+/', '', (string) $nomor);

        if (str_starts_with($angka, '0')) {
            $angka = '62'.substr($angka, 1);
        }

        $panjang = strlen($angka);

        if ($panjang < 9 || $panjang > 14) {
            throw new InvalidArgumentException('Nomor HP tidak valid.');
        }

        return $angka;
    }

    /**
     * Tampilkan nomor HP dalam format lokal ramah baca, mis. `0856-9542-2700`.
     */
    public static function tampil(?string $nomor): string
    {
        if (blank($nomor)) {
            return '';
        }

        $angka = preg_replace('/\D+/', '', (string) $nomor);

        if (str_starts_with($angka, '62') && strlen($angka) > 2) {
            $angka = '0'.substr($angka, 2);
        }

        // Format Indonesia sederhana: 0XXX-XXXX-XXXX
        if (strlen($angka) >= 8) {
            $pecah = preg_split('//u', $angka, -1, PREG_SPLIT_NO_EMPTY);

            $depan = implode('', array_slice($pecah, 0, 4));
            $sisa = implode('', array_slice($pecah, 4));
            $sisa = rtrim(chunk_split($sisa, 4, '-'), '-');

            return $depan.'-'.$sisa;
        }

        return $angka;
    }

    /**
     * Samarkan nomor HP untuk log (tampilkan sebagian digit terakhir saja).
     */
    public static function samarkan(?string $nomor): string
    {
        if (blank($nomor)) {
            return '';
        }

        $angka = preg_replace('/\D+/', '', (string) $nomor);

        if (strlen($angka) <= 4) {
            return str_repeat('*', strlen($angka));
        }

        return str_repeat('*', strlen($angka) - 4).substr($angka, -4);
    }
}
