<?php

namespace App\Models;

use App\Enums\MetodePembayaran;
use App\Enums\StatusPembayaran;
use App\Models\Concerns\MilikSekolah;
use App\Models\Concerns\PunyaUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pembayaran extends Model
{
    use PunyaUuid, MilikSekolah;

    protected $table = 'pembayaran';

    protected $fillable = [
        'pendaftaran_id',
        'rincian_biaya_id',
        'metode',
        'nominal',
        'bukti_path',
        'status',
        'catatan_penolakan',
        'diproses_oleh',
        'dibayar_pada',
    ];

    protected $attributes = [
        'rincian_biaya_id' => null,
        'nominal' => null,
        'bukti_path' => null,
        'status' => 'menunggu',
        'catatan_penolakan' => null,
        'diproses_oleh' => null,
        'dibayar_pada' => null,
    ];

    protected function casts(): array
    {
        return [
            'metode' => MetodePembayaran::class,
            'status' => StatusPembayaran::class,
            'nominal' => 'decimal:0',
            'dibayar_pada' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(Pendaftaran::class);
    }

    public function rincianBiaya(): BelongsTo
    {
        return $this->belongsTo(RincianBiaya::class);
    }

    public function pemroses(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }
}
