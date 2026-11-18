<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Pendaftaran belum memenuhi syarat kirim. `$kekurangan` berisi daftar
 * ringkas dengan tautan ke langkah yang harus diperbaiki.
 */
class SyaratKirimException extends RuntimeException
{
    /**
     * @param  array<int, array{pesan: string, langkah: int, tautan: string|null}>  $kekurangan
     */
    public function __construct(public readonly array $kekurangan, string $message = 'Pendaftaran belum dapat dikirim.')
    {
        parent::__construct($message);
    }
}
