<?php

namespace App\Models;

use App\Models\Concerns\MilikSekolah;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak tindakan pengguna. Hanya boleh ditambah, tidak boleh diubah/dihapus.
 */
class LogAktivitas extends Model
{
    use MilikSekolah;

    protected $table = 'log_aktivitas';

    public const CREATED_AT = 'dibuat_pada';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'sekolah_id',
        'aksi',
        'objek',
        'objek_id',
        'data',
        'ip_address',
        'dibuat_pada',
    ];

    protected function casts(): array
    {
        return [
            'dibuat_pada' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (LogAktivitas $log) {
            if ($log->exists) {
                throw new \DomainException('Log aktivitas hanya boleh ditambah.');
            }
        });

        static::updating(function () {
            throw new \DomainException('Log aktivitas tidak dapat diubah.');
        });

        static::deleting(function () {
            throw new \DomainException('Log aktivitas tidak dapat dihapus.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }
}
