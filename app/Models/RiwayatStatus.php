<?php

namespace App\Models;

use App\Enums\JenisRiwayat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak perubahan status. Hanya boleh ditambah, tidak boleh diubah/dihapus.
 */
class RiwayatStatus extends Model
{
    protected $table = 'riwayat_status';

    public const CREATED_AT = 'dibuat_pada';

    public const UPDATED_AT = null;

    protected $fillable = [
        'pendaftaran_id',
        'jenis',
        'dari',
        'ke',
        'oleh',
        'catatan',
        'dibuat_pada',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisRiwayat::class,
            'dibuat_pada' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (RiwayatStatus $riwayat) {
            if ($riwayat->exists) {
                throw new \DomainException('Riwayat status hanya boleh ditambah.');
            }
        });

        static::updating(function () {
            throw new \DomainException('Riwayat status tidak dapat diubah.');
        });

        static::deleting(function () {
            throw new \DomainException('Riwayat status tidak dapat dihapus.');
        });
    }

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(Pendaftaran::class);
    }

    public function olehUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oleh');
    }
}
