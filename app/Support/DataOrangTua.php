<?php

namespace App\Support;

use App\Enums\HubunganOrangTua;
use App\Models\OrangTua;

/**
 * Simpan atau kosongkan satu baris orang tua/wali milik satu akun walisantri.
 * Dipakai bersama oleh formulir walisantri, formulir offline panitia, dan
 * perubahan data pendaftar oleh panitia.
 *
 * Kolom `status_hidup` dianggap bukan data: dropdown status di formulir selalu
 * mengirim nilai (dibuka dengan pilihan bawaan "Masih hidup"), jadi status
 * saja tidak membuat baris baru dan tidak menghitung bagian itu sebagai diisi.
 * Sebaliknya, bagian yang dikosongkan menghapus barisnya (`nama` tidak boleh
 * kosong, jadi baris tanpa data tidak sah) supaya mengosongkan data wali
 * benar-benar kosong. Tidak ada tabel lain yang merujuk `orang_tua`, jadi
 * penghapusan ini aman.
 */
final class DataOrangTua
{
    public const KOLOM = [
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

    /**
     * @param  array<string, mixed>  $baris
     * @return array{aksi: string, kolom: list<string>} aksi: disimpan | dikosongkan | tanpa_perubahan
     */
    public static function simpan(int $userId, HubunganOrangTua $hubungan, array $baris): array
    {
        $baris = array_intersect_key($baris, array_flip(self::KOLOM));

        $nyata = collect($baris)
            ->except('status_hidup')
            ->contains(fn ($nilai) => filled($nilai));

        if (! $nyata) {
            $dihapus = OrangTua::query()
                ->where('user_id', $userId)
                ->where('hubungan', $hubungan)
                ->delete();

            if ($dihapus === 0) {
                return ['aksi' => 'tanpa_perubahan', 'kolom' => []];
            }

            return ['aksi' => 'dikosongkan', 'kolom' => self::KOLOM];
        }

        $baris = array_map(fn ($nilai) => $nilai === '' ? null : $nilai, $baris);

        OrangTua::updateOrCreate(
            ['user_id' => $userId, 'hubungan' => $hubungan],
            $baris,
        );

        return ['aksi' => 'disimpan', 'kolom' => array_keys($baris)];
    }
}
