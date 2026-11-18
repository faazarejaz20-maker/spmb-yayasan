<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan bisnis yang harus ditampilkan sebagai pesan sopan ke
 * pengguna, bukan galat 500.
 */
class AturanBisnisException extends RuntimeException
{
}
