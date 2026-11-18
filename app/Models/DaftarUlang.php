<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DaftarUlang extends Model
{
    protected $table = 'daftar_ulang';

    protected $fillable = [
        'pendaftaran_id',
        'hadir',
        'kekurangan',
        'catatan_wawancara',
        'selesai',
        'dicatat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'hadir' => 'boolean',
            'selesai' => 'boolean',
        ];
    }

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(Pendaftaran::class);
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }
}
