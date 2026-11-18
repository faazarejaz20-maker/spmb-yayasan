<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Terjadi bila nama lengkap + tanggal lahir anak cocok dengan pendaftar
 * terkirim lain di sekolah dan tahun ajaran yang sama. Pesan sengaja umum
 * dan tidak menyebut akun atau data pemilik lain.
 */
class DataGandaException extends RuntimeException
{
    public const PESAN = 'Data anak ini sudah terdaftar di sekolah tersebut. Jika menurut Anda ini keliru, hubungi panitia.';
}
