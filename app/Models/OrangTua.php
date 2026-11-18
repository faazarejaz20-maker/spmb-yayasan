<?php

namespace App\Models;

use App\Enums\HubunganOrangTua;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrangTua extends Model
{
    protected $table = 'orang_tua';

    protected $fillable = [
        'user_id',
        'hubungan',
        'nama',
        'nik',
        'tempat_lahir',
        'tanggal_lahir',
        'status_hidup',
        'pendidikan',
        'pekerjaan',
        'penghasilan',
        'no_hp',
        'alamat',
    ];

    protected $hidden = [
        'nik',
    ];

    protected $attributes = [
        'nik' => null,
        'tempat_lahir' => null,
        'tanggal_lahir' => null,
        'status_hidup' => null,
        'pendidikan' => null,
        'pekerjaan' => null,
        'penghasilan' => null,
        'no_hp' => null,
        'alamat' => null,
    ];

    protected function casts(): array
    {
        return [
            'hubungan' => HubunganOrangTua::class,
            'nik' => 'encrypted',
            'tanggal_lahir' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
