<?php

namespace App\Models;

use App\Enums\StatusBerkas;
use App\Models\Concerns\MilikSekolah;
use App\Models\Concerns\PunyaUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Berkas extends Model
{
    use PunyaUuid, MilikSekolah;

    protected $table = 'berkas';

    protected $fillable = [
        'pendaftaran_id',
        'jenis_berkas_id',
        'path',
        'status',
        'catatan',
        'diperiksa_oleh',
        'diperiksa_pada',
    ];

    protected $attributes = [
        'path' => null,
        'status' => 'menunggu',
        'catatan' => null,
        'diperiksa_oleh' => null,
        'diperiksa_pada' => null,
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusBerkas::class,
            'diperiksa_pada' => 'datetime',
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

    public function jenisBerkas(): BelongsTo
    {
        return $this->belongsTo(JenisBerkas::class);
    }

    public function pemeriksa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diperiksa_oleh');
    }
}
