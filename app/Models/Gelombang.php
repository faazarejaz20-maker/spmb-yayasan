<?php

namespace App\Models;

use App\Enums\StatusGelombang;
use App\Models\Concerns\MilikSekolah;
use Database\Factories\GelombangFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gelombang extends Model
{
    /** @use HasFactory<GelombangFactory> */
    use HasFactory, MilikSekolah;

    protected $table = 'gelombang';

    protected $fillable = [
        'sekolah_id',
        'tahun_ajaran_id',
        'nama',
        'tanggal_buka',
        'tanggal_tutup',
        'kuota',
        'status',
        'tautan_grup_wa',
    ];

    protected $attributes = [
        'tanggal_buka' => null,
        'tanggal_tutup' => null,
        'kuota' => null,
        'status' => 'draf',
        'tautan_grup_wa' => null,
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusGelombang::class,
            'tanggal_buka' => 'date',
            'tanggal_tutup' => 'date',
            'kuota' => 'integer',
        ];
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function pendaftaran(): HasMany
    {
        return $this->hasMany(Pendaftaran::class);
    }
}
