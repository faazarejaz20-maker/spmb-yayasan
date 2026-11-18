<?php

namespace App\Services;

use App\Models\Anak;
use App\Models\PengaturanIsian;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Satu sumber aturan isian formulir: katalog `config/isian.php` dikurangi
 * pengaturan `pengaturan_isian` sekolah tujuan.
 *
 * Isian yang disembunyikan tidak pernah diterima dari masukan pengguna:
 * pemanggil wajib memakai `saring()` sebelum menyimpan.
 */
class AturanIsian
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function katalog(string $bagian): array
    {
        return config('isian.'.$bagian, []);
    }

    public static function bagianDariLangkah(int $langkah): ?string
    {
        return match ($langkah) {
            2 => 'identitas',
            3 => 'alamat',
            4 => 'kesehatan',
            5 => 'asal_sekolah',
            default => null,
        };
    }

    /**
     * Isian yang tampil untuk satu sekolah, lengkap dengan penanda wajib.
     * Isian inti selalu tampil dan wajib.
     *
     * @return array<string, array<string, mixed>>
     */
    public function isianTampil(int $sekolahId, string $bagian): array
    {
        $katalog = $this->katalog($bagian);

        if ($katalog === []) {
            return [];
        }

        $pengaturan = PengaturanIsian::query()
            ->where('sekolah_id', $sekolahId)
            ->whereIn('kunci_isian', array_keys($katalog))
            ->get()
            ->keyBy('kunci_isian');

        $hasil = [];

        foreach ($katalog as $kunci => $definisi) {
            $baris = $pengaturan->get($kunci);
            $inti = (bool) ($definisi['inti'] ?? false);

            $tampil = $inti ? true : ($baris ? (bool) $baris->tampil : true);

            if (! $tampil) {
                continue;
            }

            $hasil[$kunci] = array_merge($definisi, [
                'wajib' => $inti ? true : ($baris ? (bool) $baris->wajib : false),
            ]);
        }

        return $hasil;
    }

    /**
     * Hanya kunci yang tampil. Kunci lain dibuang, termasuk bila dikirim
     * paksa oleh pengguna.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function saring(int $sekolahId, string $bagian, array $data): array
    {
        return array_intersect_key($data, $this->isianTampil($sekolahId, $bagian));
    }

    /**
     * @return array<string, mixed>
     */
    public function aturanValidasi(int $sekolahId, string $bagian): array
    {
        $aturan = [];

        foreach ($this->isianTampil($sekolahId, $bagian) as $kunci => $definisi) {
            $dasar = ($definisi['wajib'] ?? false) ? ['required'] : ['nullable'];

            $aturan[$kunci] = array_merge($dasar, $this->aturanTipe($kunci, $definisi));
        }

        return $aturan;
    }

    /**
     * @param  array<string, mixed>  $definisi
     * @return array<int, mixed>
     */
    private function aturanTipe(string $kunci, array $definisi): array
    {
        $tipe = $definisi['tipe'] ?? 'teks';

        return match ($tipe) {
            'tanggal' => ['date', 'before:today'],
            'angka' => ['integer', $this->rentangAngka($kunci)],
            'pilihan' => [Rule::in(array_keys($definisi['pilihan'] ?? []))],
            'teks_panjang' => ['string', 'max:2000'],
            'centang' => ['boolean'],
            default => ['string', $this->batasTeks($kunci)],
        };
    }

    private function batasTeks(string $kunci): string
    {
        return match ($kunci) {
            'nama_lengkap', 'asal_sekolah' => 'max:150',
            'nama_panggilan', 'bahasa_sehari_hari', 'jarak_ke_sekolah',
            'transportasi', 'tinggal_dengan', 'pendidikan' => 'max:50',
            'tempat_lahir', 'pekerjaan', 'desa', 'kecamatan', 'kabupaten', 'provinsi' => 'max:100',
            'agama' => 'max:30',
            'rt', 'rw' => 'max:5',
            'nik', 'no_kk' => 'digits:16',
            'nisn' => 'digits:10',
            'kode_pos' => 'digits:5',
            default => 'max:150',
        };
    }

    private function rentangAngka(string $kunci): string
    {
        return match ($kunci) {
            'anak_ke' => 'between:1,20',
            'jumlah_saudara' => 'between:0,20',
            'tinggi_badan' => 'between:50,250',
            'berat_badan' => 'between:10,150',
            default => 'between:0,1000',
        };
    }

    /**
     * Nilai isian dari model anak, untuk ditampilkan di formulir.
     *
     * @return array<string, mixed>
     */
    public function nilaiAnak(Anak $anak, int $sekolahId, string $bagian): array
    {
        $nilai = [];

        foreach (array_keys($this->isianTampil($sekolahId, $bagian)) as $kunci) {
            $nilai[$kunci] = $anak->getAttribute($kunci);
        }

        return $nilai;
    }

    /**
     * Periksa seluruh isian wajib Tahap 1 pada data anak.
     *
     * @return array<int, array{pesan: string, langkah: int}>
     */
    public function kekuranganTahap1(Anak $anak, int $sekolahId): array
    {
        $kekurangan = [];

        foreach ([2 => 'identitas', 3 => 'alamat', 4 => 'kesehatan', 5 => 'asal_sekolah'] as $langkah => $bagian) {
            $nilai = $this->nilaiAnak($anak, $sekolahId, $bagian);
            $validator = Validator::make($nilai, $this->aturanValidasi($sekolahId, $bagian));

            foreach ($validator->errors()->all() as $pesan) {
                $kekurangan[] = ['pesan' => $pesan, 'langkah' => $langkah];
            }
        }

        return $kekurangan;
    }
}
