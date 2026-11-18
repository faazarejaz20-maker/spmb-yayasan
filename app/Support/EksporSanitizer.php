<?php

namespace App\Support;

/**
 * Netralisasi formula injection untuk semua ekspor (Excel dan CSV).
 *
 * Sel teks yang diawali `=`, `+`, `-`, `@`, tab, atau carriage return bisa
 * dieksekusi Excel/LibreOffice sebagai rumus bila diawali `=`, `+`, atau `@`
 * (mis. `=HYPERLINK("http://evil","klik")`). Seluruh nilai teks yang ditulis
 * ke file ekspor harus melewati `EksporSanitizer::sel()`.
 */
final class EksporSanitizer
{
    private const AWALAN_BERBAHAYA = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Netralkan satu nilai sel. Nilai non-string (angka, tanggal) lewat apa
     * adanya supaya tetap ditulis sebagai tipe aslinya.
     */
    public static function sel(mixed $nilai): mixed
    {
        if (! is_string($nilai)) {
            return $nilai;
        }

        if ($nilai !== '' && in_array($nilai[0], self::AWALAN_BERBAHAYA, true)) {
            return '\''.$nilai;
        }

        return $nilai;
    }

    /**
     * Netralkan seluruh nilai satu baris data ekspor.
     *
     * @param  array<int|string, mixed>  $baris
     * @return array<int|string, mixed>
     */
    public static function baris(array $baris): array
    {
        return array_map(fn ($nilai) => self::sel($nilai), $baris);
    }
}
