<?php

namespace App\Models;

use App\Enums\ModePenerimaan;
use Database\Factories\SekolahFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sekolah extends Model
{
    /** @use HasFactory<SekolahFactory> */
    use HasFactory;

    protected $table = 'sekolah';

    protected $fillable = [
        'kode',
        'nama',
        'singkatan',
        'jenjang',
        'alamat',
        'kontak',
        'instagram',
        'mode_penerimaan',
        'aktif',
    ];

    protected $attributes = [
        'singkatan' => null,
        'alamat' => null,
        'kontak' => null,
        'instagram' => null,
        'aktif' => true,
    ];

    protected function casts(): array
    {
        return [
            'mode_penerimaan' => ModePenerimaan::class,
            'aktif' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function gelombang(): HasMany
    {
        return $this->hasMany(Gelombang::class);
    }

    public function jenisBerkas(): HasMany
    {
        return $this->hasMany(JenisBerkas::class);
    }

    public function rincianBiaya(): HasMany
    {
        return $this->hasMany(RincianBiaya::class);
    }

    public function kontenSekolah(): HasMany
    {
        return $this->hasMany(KontenSekolah::class);
    }
}
