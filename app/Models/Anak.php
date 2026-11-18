<?php

namespace App\Models;

use App\Models\Concerns\PunyaUuid;
use Database\Factories\AnakFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Anak extends Model
{
    /** @use HasFactory<AnakFactory> */
    use HasFactory, PunyaUuid;

    protected $table = 'anak';

    /**
     * `user_id` tidak fillable: pemilik anak selalu ditentukan kode, bukan
     * masukan formulir.
     */
    protected $fillable = [
        'nama_lengkap',
        'nama_panggilan',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'nik',
        'no_kk',
        'nisn',
        'agama',
        'anak_ke',
        'jumlah_saudara',
        'bahasa_sehari_hari',
        'tinggi_badan',
        'berat_badan',
        'golongan_darah',
        'riwayat_penyakit',
        'alergi',
        'kebutuhan_khusus',
        'alamat',
        'rt',
        'rw',
        'desa',
        'kecamatan',
        'kabupaten',
        'provinsi',
        'kode_pos',
        'jarak_ke_sekolah',
        'transportasi',
        'tinggal_dengan',
        'asal_sekolah',
        'jenis_asal_sekolah',
    ];

    protected $hidden = [
        'nik',
        'no_kk',
    ];

    /**
     * Nilai bawaan di memori supaya atribut opsional tetap bisa dibaca
     * walau baru dibuat dengan data minimal (strict mode Laravel).
     */
    protected $attributes = [
        'nama_panggilan' => null,
        'nik' => null,
        'no_kk' => null,
        'nisn' => null,
        'agama' => 'Islam',
        'anak_ke' => null,
        'jumlah_saudara' => null,
        'bahasa_sehari_hari' => null,
        'tinggi_badan' => null,
        'berat_badan' => null,
        'golongan_darah' => null,
        'riwayat_penyakit' => null,
        'alergi' => null,
        'kebutuhan_khusus' => null,
        'rt' => null,
        'rw' => null,
        'desa' => null,
        'kecamatan' => null,
        'kabupaten' => null,
        'provinsi' => null,
        'kode_pos' => null,
        'jarak_ke_sekolah' => null,
        'transportasi' => null,
        'tinggal_dengan' => null,
        'asal_sekolah' => null,
        'jenis_asal_sekolah' => null,
    ];

    protected function casts(): array
    {
        return [
            'nik' => 'encrypted',
            'no_kk' => 'encrypted',
            'tanggal_lahir' => 'date',
            'anak_ke' => 'integer',
            'jumlah_saudara' => 'integer',
            'tinggi_badan' => 'integer',
            'berat_badan' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pendaftaran(): HasMany
    {
        return $this->hasMany(Pendaftaran::class);
    }
}
