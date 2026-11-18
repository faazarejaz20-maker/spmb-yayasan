<?php

namespace App\Support;

/**
 * Pembuat kata sandi sementara acak yang aman secara kriptografis.
 *
 * Huruf dan angka yang mudah dibedakan saja: tanpa `0 O 1 l I`.
 */
class KataSandiSementara
{
    private const HURUF = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';

    private const ANGKA = '23456789';

    public static function buat(int $panjang = 10): string
    {
        if ($panjang < 4) {
            throw new \InvalidArgumentException('Panjang kata sandi sementara minimal 4 karakter.');
        }

        $semua = self::HURUF.self::ANGKA;
        $karakter = [
            self::HURUF[random_int(0, strlen(self::HURUF) - 1)],
            self::ANGKA[random_int(0, strlen(self::ANGKA) - 1)],
        ];

        for ($i = count($karakter); $i < $panjang; $i++) {
            $karakter[] = $semua[random_int(0, strlen($semua) - 1)];
        }

        // Fisher-Yates dengan sumber acak aman.
        for ($i = count($karakter) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$karakter[$i], $karakter[$j]] = [$karakter[$j], $karakter[$i]];
        }

        return implode('', $karakter);
    }
}
