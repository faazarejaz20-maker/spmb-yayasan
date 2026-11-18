<?php

namespace App\Services;

use App\Enums\StatusHasil;

/**
 * Objek ringan satu-satunya sumber tampilan status untuk walisantri.
 * `hasil` selalu null sebelum pengumuman dibuat.
 */
final readonly class StatusTampil
{
    public function __construct(
        public string $label,
        public string $warna,
        public ?string $langkahBerikutnya,
        public bool $sudahDiumumkan,
        public ?StatusHasil $hasil,
        public int $tahap,
        public bool $perluPerbaikan = false,
        public bool $draf = false,
        public bool $tampilkanGrupWa = false,
    ) {}
}
