<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Gambar publik halaman sekolah.
 *
 * Semua berkas dibaca dari `public/images/` (logo, foto gedung, ekskul,
 * prestasi) atau dari disk publik khusus konten (`storage/app/public/konten`).
 * Bila tersedia salinan ringan hasil `php artisan spmb:optimalkan-gambar`
 * di `public/images/optimasi`, salinan itulah yang dipakai agar foto asli
 * beresolusi besar tidak dikirim ke pengunjung. Berkas yang belum ada selalu
 * menghasilkan null, sehingga tampilan memakai placeholder "Foto menyusul" /
 * "Logo menyusul" tanpa galat.
 */
class GambarPublik
{
    /** Folder turunan WebP hasil perintah `spmb:optimalkan-gambar`. */
    public const FOLDER_OPTIMASI = 'images/optimasi';

    /**
     * Nama berkas logo yang diharapkan (docs/data-awal.md) dan nama berkas
     * yang benar-benar ada di folder proyek. Kandidat pertama yang ditemukan
     * dipakai.
     *
     * @var array<string, array<int, string>>
     */
    private const LOGO = [
        'TKK' => ['tkit-al-ishlah.png', 'tkit-al-ishlah.jpg'],
        'KB' => ['tkit-insan-mulia-kb.png', 'kbit-insan-mulia.png'],
        'TK' => ['tkit-insan-mulia-tk.png', 'tkit-insan-mulia.png'],
        'TPA' => ['tkit-insan-mulia-tpa.png', 'tkit-insan-mulia.png'],
        'TKI' => ['tkit-insan-mulia.png', 'kbit-insan-mulia.png'],
        'SD' => ['sdit.png', 'sdit.jpg'],
        'SMP' => ['smpit.png', 'smpit.jpg'],
        'JSIT' => ['jsit.png'],
    ];

    /**
     * Folder foto yang tersedia di proyek. Nama folder mengikuti berkas yang
     * sudah diunggah tim sekolah; struktur baru `sekolah/{kode}/...` juga
     * dicoba lebih dulu.
     *
     * @var array<string, array<int, string>>
     */
    private const FOLDER_SEKOLAH = [
        'TKK' => ['sekolah/tkk', 'sekolah/gedungtkit_alishlah'],
        'TKI' => ['sekolah/tki', 'sekolah/gedungtkit_kbit_insanmulia'],
        'SD' => ['sekolah/sd', 'sekolah/gedungsdit'],
        'SMP' => ['sekolah/smp', 'sekolah/gedungsmpit'],
    ];

    /** @var array<string, array<int, string>> */
    private const FOLDER_EKSKUL = [
        'SD' => ['ekskul/sd', 'ekskul/ekskulsd'],
        'SMP' => ['ekskul/smp', 'ekskul/ekskulsmp'],
    ];

    /** @var array<string, array<int, string>> */
    private const FOLDER_PRESTASI = [
        'SD' => ['prestasi/sd'],
        'SMP' => ['prestasi/smp', 'prestasi/smpit'],
    ];

    /**
     * Foto prestasi per sekolah yang benar-benar diunggah sekolah. Dipakai
     * dasbor walisantri agar orang tua melihat prestasi tiap sekolah, bukan
     * foto buatan. Urutan di sini adalah urutan tampil; berkas yang belum ada
     * dilewati, sehingga sekolah tanpa foto menampilkan "Foto prestasi
     * menyusul".
     *
     * @var array<string, array<int, array{berkas: string, alt: string, keterangan: string}>>
     */
    private const PRESTASI_SEKOLAH = [
        'SD' => [
            [
                'berkas' => 'prestasi/sd/prestasisd (1).jpeg',
                'alt' => 'Dua murid SDIT Insan Mulia Wonosobo memegang piala dan celengan penghargaan',
                'keterangan' => 'Juara kencleng terbanyak SDIT Insan Mulia Wonosobo',
            ],
            [
                'berkas' => 'prestasi/sd/prestasisd (2).jpeg',
                'alt' => 'Lima murid SDIT Insan Mulia Wonosobo berfoto bersama piala lomba',
                'keterangan' => 'Murid SDIT Insan Mulia Wonosobo membawa pulang piala lomba',
            ],
            [
                'berkas' => 'prestasi/sd/prestasisd (4).jpeg',
                'alt' => 'Murid SDIT Insan Mulia Wonosobo bersandar di samping piala Juara III bulu tangkis putri',
                'keterangan' => 'Juara III bulu tangkis putri Popda SD/MI Kecamatan Wonosobo',
            ],
            [
                'berkas' => 'prestasi/sd/prestasisd (3).jpeg',
                'alt' => 'Dua murid SDIT Insan Mulia Wonosobo memakai seragam pencak silat sambil memegang piala',
                'keterangan' => 'Dua murid SDIT Insan Mulia Wonosobo memegang piala lomba',
            ],
        ],
        'SMP' => [
            [
                'berkas' => 'prestasi/smpit/prestasismp (1).jpeg',
                'alt' => 'Tiga murid SMPIT Insan Mulia Wonosobo memegang piala dan piagam Gebyar Apresiasi',
                'keterangan' => 'Pemenang Gebyar Apresiasi SMPIT Insan Mulia Wonosobo',
            ],
            [
                'berkas' => 'prestasi/smpit/prestasismp (2).jpg',
                'alt' => 'Murid dan ustadzah SMPIT Insan Mulia Wonosobo berfoto bersama piala lomba di halaman sekolah',
                'keterangan' => 'Murid SMPIT Insan Mulia Wonosobo berfoto bersama piala lomba',
            ],
            [
                'berkas' => 'prestasi/smpit/prestasismp (3).jpg',
                'alt' => 'Seorang ustadzah menyerahkan piala kepada murid SMPIT Insan Mulia Wonosobo',
                'keterangan' => 'Penyerahan piala kepada murid SMPIT Insan Mulia Wonosobo',
            ],
        ],
    ];

    /**
     * Foto sampul kartu sekolah di beranda. Hanya dipakai bila berkasnya ada;
     * bila tidak, kartu memakai foto pertama dari folder sekolah.
     *
     * @var array<string, string>
     */
    private const SAMPUL = [
        'TKK' => 'sekolah/gedungtkit_alishlah/gedungtkitalishlah.jpeg',
        'TKI' => 'sekolah/gedungtkit_kbit_insanmulia/gedungtkitinsanmuliawonosobo (1).jpg',
        'SD' => 'sekolah/gedungsdit/gedungsdit (1).jpg',
        'SMP' => 'sekolah/gedungsmpit/gedungsmpit (2).jpg',
    ];

    /**
     * Sorotan beranda: foto kegiatan dan prestasi murid yang benar-benar
     * diunggah sekolah. Urutan di sini adalah urutan tampil.
     *
     * @var array<int, array{berkas: string, alt: string, keterangan: string, sekolah: string}>
     */
    private const SOROTAN = [
        [
            'berkas' => 'prestasi/sd/prestasisd (1).jpeg',
            'alt' => 'Dua murid SDIT Insan Mulia Wonosobo memegang piala dan celengan penghargaan',
            'keterangan' => 'Juara kencleng terbanyak SDIT Insan Mulia Wonosobo',
            'sekolah' => 'SDIT Insan Mulia',
        ],
        [
            'berkas' => 'prestasi/sd/prestasisd (2).jpeg',
            'alt' => 'Lima murid SDIT Insan Mulia Wonosobo berfoto bersama piala lomba',
            'keterangan' => 'Murid SDIT Insan Mulia Wonosobo membawa pulang piala lomba',
            'sekolah' => 'SDIT Insan Mulia',
        ],
        [
            'berkas' => 'prestasi/sd/prestasisd (4).jpeg',
            'alt' => 'Murid SDIT Insan Mulia Wonosobo bersandar di samping piala Juara III bulu tangkis putri',
            'keterangan' => 'Juara III bulu tangkis putri Popda SD/MI Kecamatan Wonosobo',
            'sekolah' => 'SDIT Insan Mulia',
        ],
        [
            'berkas' => 'prestasi/smpit/prestasismp (1).jpeg',
            'alt' => 'Tiga murid SMPIT Insan Mulia Wonosobo memegang piala dan piagam Gebyar Apresiasi',
            'keterangan' => 'Pemenang Gebyar Apresiasi SMPIT Insan Mulia Wonosobo',
            'sekolah' => 'SMPIT Insan Mulia',
        ],
        [
            'berkas' => 'ekskul/ekskulsd/ekskulsd (1).jpeg',
            'alt' => 'Regu pramuka SDIT Insan Mulia Wonosobo berfoto di depan bangunan pionering',
            'keterangan' => 'Regu pramuka SDIT berlatih pionering di halaman sekolah',
            'sekolah' => 'SDIT Insan Mulia',
        ],
        [
            'berkas' => 'ekskul/ekskulsmp/eksulsmp (1).png',
            'alt' => 'Seorang santri SMPIT Insan Mulia Wonosobo bersalaman dengan ustadzah',
            'keterangan' => 'Pembiasaan adab santri SMPIT Insan Mulia',
            'sekolah' => 'SMPIT Insan Mulia',
        ],
        [
            'berkas' => 'sekolah/gedungsmpit/gedungsmpit (2).jpg',
            'alt' => 'Upacara bendera regu pramuka SMPIT Insan Mulia Wonosobo di halaman sekolah',
            'keterangan' => 'Upacara bendera di halaman SMPIT Insan Mulia Wonosobo',
            'sekolah' => 'SMPIT Insan Mulia',
        ],
    ];

    /**
     * @return array{url: string, alt: string, lebar: int|null, tinggi: int|null}|null
     */
    public function logo(?string $kode, ?string $alt = null): ?array
    {
        $kode = strtoupper((string) $kode);

        foreach (self::LOGO[$kode] ?? [] as $berkas) {
            $hasil = $this->gambarDariRelatif('logo/'.$berkas, $alt ?? 'Logo sekolah');

            if ($hasil) {
                return $hasil;
            }
        }

        return null;
    }

    /**
     * @return array{url: string, alt: string, lebar: int|null, tinggi: int|null}|null
     */
    public function logoYayasan(): ?array
    {
        return $this->gambarDariRelatif('logo/yayasan.png', 'Logo Yayasan Al-Ishlah Insan Mulia');
    }

    /**
     * Foto sampul kartu sekolah. Bila sekolah sudah mengunggah foto sendiri
     * lewat panel, foto itulah yang dipakai. Bila belum, dipakai foto pilihan
     * di konstanta SAMPUL, lalu foto pertama folder sekolah, lalu null.
     *
     * @return array{url: string, alt: string, lebar: int|null, tinggi: int|null}|null
     */
    public function sampulSekolah(?string $kode, string $namaSekolah, ?string $pathUnggahan = null): ?array
    {
        if (filled($pathUnggahan)) {
            $unggahan = $this->dariKonten($pathUnggahan, 'Foto '.$namaSekolah);

            if ($unggahan) {
                return $unggahan;
            }
        }

        $kode = strtoupper((string) $kode);
        $berkas = self::SAMPUL[$kode] ?? null;

        if ($berkas !== null) {
            $gambar = $this->gambarDariRelatif($berkas, 'Foto '.$namaSekolah);

            if ($gambar) {
                return $gambar;
            }
        }

        foreach (self::FOLDER_SEKOLAH[$kode] ?? [] as $folder) {
            $daftar = $this->dariFolder('images/'.$folder, 'Foto '.$namaSekolah);

            if ($daftar !== []) {
                return $daftar[0];
            }
        }

        return null;
    }

    /**
     * Foto sorotan beranda: prestasi dan kegiatan murid. Hanya berkas yang ada
     * yang dikembalikan, lengkap dengan keterangan singkatnya.
     *
     * @return array<int, array{url: string, alt: string, keterangan: string, sekolah: string, lebar: int|null, tinggi: int|null}>
     */
    public function sorotan(): array
    {
        $hasil = [];

        foreach (self::SOROTAN as $baris) {
            $gambar = $this->gambarDariRelatif($baris['berkas'], $baris['alt']);

            if ($gambar === null) {
                continue;
            }

            $hasil[] = [
                'url' => $gambar['url'],
                'alt' => $gambar['alt'],
                'keterangan' => $baris['keterangan'],
                'sekolah' => $baris['sekolah'],
                'lebar' => $gambar['lebar'],
                'tinggi' => $gambar['tinggi'],
            ];
        }

        return $hasil;
    }

    /**
     * Foto prestasi milik satu sekolah, lengkap dengan keterangan singkat.
     * Hanya berkas yang benar-benar ada yang dikembalikan.
     *
     * @return array<int, array{url: string, alt: string, keterangan: string, lebar: int|null, tinggi: int|null}>
     */
    public function prestasiSekolah(?string $kode): array
    {
        $kode = strtoupper((string) $kode);
        $hasil = [];

        foreach (self::PRESTASI_SEKOLAH[$kode] ?? [] as $baris) {
            $gambar = $this->gambarDariRelatif($baris['berkas'], $baris['alt']);

            if ($gambar === null) {
                continue;
            }

            $hasil[] = [
                'url' => $gambar['url'],
                'alt' => $gambar['alt'],
                'keterangan' => $baris['keterangan'],
                'lebar' => $gambar['lebar'],
                'tinggi' => $gambar['tinggi'],
            ];
        }

        return $hasil;
    }

    /**
     * Foto-foto yang bisa dipakai halaman galeri: foto gedung, ekskul, dan
     * prestasi. Berkas yang belum ada tidak ikut.
     *
     * @return array<int, array{url: string, alt: string, lebar: int|null, tinggi: int|null}>
     */
    public function galeri(string $kode, string $namaSekolah): array
    {
        $kode = strtoupper($kode);
        $hasil = [];

        foreach (self::FOLDER_SEKOLAH[$kode] ?? [] as $folder) {
            $hasil = array_merge($hasil, $this->dariFolder('images/'.$folder, 'Foto '.$namaSekolah));
        }

        foreach (self::FOLDER_EKSKUL[$kode] ?? [] as $folder) {
            $hasil = array_merge($hasil, $this->dariFolder('images/'.$folder, 'Kegiatan ekstrakurikuler '.$namaSekolah));
        }

        foreach (self::FOLDER_PRESTASI[$kode] ?? [] as $folder) {
            $hasil = array_merge($hasil, $this->dariFolder('images/'.$folder, 'Prestasi '.$namaSekolah));
        }

        return array_slice($hasil, 0, 8);
    }

    /**
     * URL relatif untuk gambar konten di disk publik (`konten/...`).
     *
     * Sengaja hanya mengambil jalur (`/storage/...`) tanpa host, supaya gambar
     * tetap tampil pada host mana pun aplikasi dibuka, meskipun `APP_URL` di
     * `.env` berbeda dengan alamat yang dipakai pengguna.
     */
    public function urlKonten(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $disk = Storage::disk('public');
        $url = $disk->url($path);
        $jalur = parse_url($url, PHP_URL_PATH);

        return is_string($jalur) && $jalur !== '' ? $jalur : $url;
    }

    /**
     * Foto konten yang disimpan di disk publik (`konten/...`).
     *
     * @return array{url: string, alt: string, lebar: int|null, tinggi: int|null}|null
     */
    public function dariKonten(?string $path, string $alt): ?array
    {
        if (blank($path)) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        $ukuran = @getimagesize($disk->path($path));
        $url = $this->urlKonten($path) ?? $disk->url($path);

        return [
            'url' => $url,
            'alt' => $alt,
            'lebar' => $ukuran[0] ?? null,
            'tinggi' => $ukuran[1] ?? null,
        ];
    }

    /**
     * Nama berkas turunan WebP untuk sebuah berkas di `public/images`.
     * Dipakai bersama oleh perintah optimasi dan pembaca gambar agar
     * keduanya menunjuk berkas yang sama.
     */
    public static function namaOptimasi(string $relatif): string
    {
        $relatif = trim(str_replace('\\', '/', $relatif), '/');
        $folder = trim(str_replace('\\', '/', dirname($relatif)), './');
        $dasar = Str::slug(pathinfo($relatif, PATHINFO_FILENAME));
        $sidik = substr(sha1($relatif), 0, 8);

        return ltrim(
            ($folder !== '' ? $folder.'/' : '').($dasar !== '' ? $dasar : 'gambar').'-'.$sidik.'.webp',
            '/'
        );
    }

    /**
     * @return array{url: string, alt: string, lebar: int|null, tinggi: int|null}
     */
    private function gambarDariRelatif(string $relatif, string $alt): ?array
    {
        $relatif = trim(str_replace('\\', '/', $relatif), '/');
        $asli = public_path('images/'.$relatif);

        if (! is_file($asli)) {
            return null;
        }

        $turunan = self::namaOptimasi($relatif);
        $jalur = is_file(public_path(self::FOLDER_OPTIMASI.'/'.$turunan))
            ? self::FOLDER_OPTIMASI.'/'.$turunan
            : 'images/'.$relatif;

        $ukuran = @getimagesize(public_path($jalur));

        if ($ukuran === false) {
            $ukuran = @getimagesize($asli);
        }

        if ($ukuran === false) {
            return null;
        }

        return [
            'url' => $this->urlPublik($jalur),
            'alt' => Str::limit($alt, 160, ''),
            'lebar' => (int) $ukuran[0],
            'tinggi' => (int) $ukuran[1],
        ];
    }

    /**
     * @return array<int, array{url: string, alt: string, lebar: int|null, tinggi: int|null}>
     */
    private function dariFolder(string $relatif, string $alt): array
    {
        $folder = public_path(ltrim(str_replace('\\', '/', $relatif), '/'));

        if (! is_dir($folder)) {
            return [];
        }

        $berkas = glob($folder.'/*');

        if ($berkas === false) {
            return [];
        }

        sort($berkas, SORT_NATURAL | SORT_FLAG_CASE);
        $hasil = [];

        foreach ($berkas as $path) {
            if (! is_file($path)) {
                continue;
            }

            $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                continue;
            }

            $gambar = $this->gambarDariRelatif(
                ltrim(str_replace('\\', '/', $relatif), '/').'/'.basename($path),
                $alt
            );

            if ($gambar) {
                $hasil[] = $gambar;
            }
        }

        return $hasil;
    }

    /**
     * URL publik dengan setiap bagian jalur di-encode, termasuk nama berkas
     * asli yang memuat spasi dan tanda kurung. Hasilnya relatif terhadap akar
     * situs (mis. `/images/optimasi/...`) supaya data yang disimpan di cache
     * tidak terikat pada nama host saat cache dibuat.
     */
    private function urlPublik(string $relatif): string
    {
        $segmen = array_map(
            'rawurlencode',
            explode('/', ltrim(str_replace('\\', '/', $relatif), '/'))
        );

        return '/'.implode('/', $segmen);
    }
}
