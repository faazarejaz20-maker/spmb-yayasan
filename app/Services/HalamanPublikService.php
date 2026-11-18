<?php

namespace App\Services;

use App\Models\Gelombang;
use App\Models\JenisBerkas;
use App\Models\KontenSekolah;
use App\Models\Sekolah;
use App\Models\TahunAjaran;
use App\Support\GambarPublik;
use App\Support\NomorHp;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Data halaman publik.
 *
 * Service ini sengaja hanya mengambil kolom yang boleh tampil di halaman
 * publik. `rincian_biaya`, `rekening_sekolah`, `gelombang.kuota`,
 * `gelombang.tautan_grup_wa`, dan tabel `pendaftaran` tidak pernah disentuh
 * (K-17). Hasilnya disimpan singkat di cache dan dibuang setiap kali konten,
 * sekolah, gelombang, jenis berkas, atau tahun ajaran berubah.
 */
class HalamanPublikService
{
    private const MENIT_CACHE = 10;

    /**
     * Alur umum pendaftaran (docs/data-awal.md bagian 4). Dipakai bila admin
     * belum mengisi teks alur khusus sekolah.
     *
     * @var array<int, string>
     */
    private const ALUR_UMUM = [
        'Buat akun walisantri',
        'Isi formulir dan unggah berkas',
        'Verifikasi oleh panitia',
        'Pengumuman hasil di akun',
        'Daftar ulang dan wawancara di sekolah bagi yang diterima',
        'Bergabung ke grup WhatsApp murid baru',
    ];

    public function __construct(private readonly GambarPublik $gambar) {}

    /**
     * @return array<string, mixed>
     */
    public function beranda(): array
    {
        return Cache::remember($this->kunci('beranda'), now()->addMinutes(self::MENIT_CACHE), function () {
            $sekolah = $this->sekolahRingkas();
            $yayasan = $this->kontenYayasan();

            return [
                'tahun_ajaran' => $this->tahunAjaranAktif(),
                'yayasan' => $yayasan,
                'sekolah' => $sekolah,
                'alur' => self::ALUR_UMUM,
                'sorotan' => $this->gambar->sorotan(),
                'logo_yayasan' => $this->gambar->logoYayasan(),
                'logo_jsit' => $this->gambar->logo('JSIT', 'Logo JSIT'),
            ];
        });
    }

    /**
     * @return array<string, mixed>|null Null bila kode sekolah tidak dikenal.
     */
    public function sekolah(string $kode): ?array
    {
        $kode = strtoupper(trim($kode));

        return Cache::remember($this->kunci('sekolah:'.$kode), now()->addMinutes(self::MENIT_CACHE), function () use ($kode) {
            /** @var Sekolah|null $sekolah */
            $sekolah = Sekolah::query()
                ->where('aktif', true)
                ->where('kode', $kode)
                ->first(['id', 'kode', 'nama', 'singkatan', 'jenjang', 'alamat', 'kontak', 'instagram', 'mode_penerimaan']);

            if (! $sekolah) {
                return null;
            }

            $konten = KontenSekolah::query()
                ->tanpaSekolah()
                ->where('sekolah_id', $sekolah->id)
                ->orderBy('urutan')
                ->orderBy('id')
                ->get(['bagian', 'judul', 'isi', 'gambar_path', 'urutan'])
                ->groupBy('bagian');

            $teks = function (string $bagian) use ($konten): ?string {
                $baris = $konten->get($bagian)?->first();

                return $baris?->isi;
            };

            $daftar = function (string $bagian) use ($konten, $sekolah): array {
                return ($konten->get($bagian) ?? collect())
                    ->map(function (KontenSekolah $baris) use ($sekolah) {
                        $gambar = $this->gambar->dariKonten(
                            $baris->gambar_path,
                            $baris->judul ?: 'Foto '.$sekolah->nama,
                        );

                        return [
                            'judul' => $baris->judul,
                            'isi' => $baris->isi,
                            'gambar' => $gambar,
                        ];
                    })
                    ->values()
                    ->all();
            };

            $gelombang = Gelombang::query()
                ->tanpaSekolah()
                ->where('sekolah_id', $sekolah->id)
                ->where('status', 'dibuka')
                ->orderBy('tanggal_buka')
                ->orderBy('id')
                ->get(['nama', 'tanggal_buka', 'tanggal_tutup']);

            $galeriKonten = $daftar('galeri');
            $galeri = collect($galeriKonten)->pluck('gambar')->filter()->values()->all();

            if ($galeri === []) {
                $galeri = $this->gambar->galeri($sekolah->kode, $sekolah->nama);
            }

            $program = $daftar('program_unggulan');
            $prestasi = $daftar('prestasi');

            return [
                'tahun_ajaran' => $this->tahunAjaranAktif(),
                'sekolah' => [
                    'kode' => $sekolah->kode,
                    'nama' => $sekolah->nama,
                    'singkatan' => $sekolah->singkatan,
                    'jenjang' => $sekolah->jenjang,
                    'alamat' => $sekolah->alamat,
                    'kontak' => $sekolah->kontak,
                    'whatsapp_tampil' => NomorHp::tampil($sekolah->kontak),
                    'whatsapp_tautan' => $sekolah->kontak ? 'https://wa.me/'.preg_replace('/\D+/', '', $sekolah->kontak) : null,
                    'instagram' => $sekolah->instagram,
                    'instagram_tautan' => $sekolah->instagram ? 'https://instagram.com/'.$sekolah->instagram : null,
                    'logo' => $this->gambar->logo($sekolah->kode, 'Logo '.$sekolah->nama),
                ],
                'status' => $this->statusPendaftaran($gelombang),
                'konten' => [
                    'slogan' => $teks('slogan'),
                    'profil' => $teks('profil'),
                    'visi' => $teks('visi'),
                    'indikator_visi' => $this->nilaiDaftar($konten->get('indikator_visi')),
                    'misi' => $this->nilaiDaftar($konten->get('misi')),
                    'program_unggulan' => $program,
                    'ekskul' => $this->nilaiDaftar($konten->get('ekskul')),
                    'prestasi' => $prestasi,
                    'fasilitas' => $daftar('fasilitas'),
                    'galeri' => $galeri,
                    'jam_belajar' => $teks('jam_belajar'),
                    'kontak' => $teks('kontak'),
                    'alur' => $teks('alur'),
                    'syarat' => $teks('syarat'),
                ],
                'alur_umum' => self::ALUR_UMUM,
                'jenis_berkas' => JenisBerkas::query()
                    ->tanpaSekolah()
                    ->where('sekolah_id', $sekolah->id)
                    ->where('aktif', true)
                    ->orderBy('urutan')
                    ->orderBy('id')
                    ->get(['nama', 'wajib', 'jumlah_lembar', 'petunjuk'])
                    ->map(fn (JenisBerkas $baris) => [
                        'nama' => $baris->nama,
                        'wajib' => $baris->wajib,
                        'jumlah_lembar' => $baris->jumlah_lembar,
                        'petunjuk' => $baris->petunjuk,
                    ])
                    ->all(),
                'program_tki' => $sekolah->kode === 'TKI' ? $this->programTki($sekolah->nama) : [],
            ];
        });
    }

    /**
     * Daftar kode sekolah aktif untuk sitemap.
     *
     * @return array<int, string>
     */
    public function kodeSekolahAktif(): array
    {
        return Sekolah::query()
            ->where('aktif', true)
            ->orderBy('id')
            ->pluck('kode')
            ->all();
    }

    /**
     * Buang cache halaman publik. Dipanggil model event saat data berubah.
     */
    public function lupakan(): void
    {
        $versi = (int) Cache::get('publik:versi', 1);
        Cache::forever('publik:versi', $versi + 1);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sekolahRingkas(): array
    {
        $sekolah = Sekolah::query()
            ->where('aktif', true)
            ->orderBy('id')
            ->get(['id', 'kode', 'nama', 'singkatan', 'jenjang', 'alamat', 'kontak', 'instagram', 'foto_sampul_path']);

        if ($sekolah->isEmpty()) {
            return [];
        }

        $ids = $sekolah->pluck('id')->all();

        $slogan = KontenSekolah::query()
            ->tanpaSekolah()
            ->whereIn('sekolah_id', $ids)
            ->where('bagian', 'slogan')
            ->orderBy('urutan')
            ->get(['sekolah_id', 'isi'])
            ->groupBy('sekolah_id')
            ->map(fn ($baris) => $baris->first()?->isi);

        $gelombang = Gelombang::query()
            ->tanpaSekolah()
            ->whereIn('sekolah_id', $ids)
            ->where('status', 'dibuka')
            ->orderBy('tanggal_buka')
            ->orderBy('id')
            ->get(['sekolah_id', 'nama', 'tanggal_buka', 'tanggal_tutup'])
            ->groupBy('sekolah_id');

        return $sekolah->map(function (Sekolah $s) use ($slogan, $gelombang) {
            return [
                'kode' => $s->kode,
                'nama' => $s->nama,
                'singkatan' => $s->singkatan,
                'jenjang' => $s->jenjang,
                'alamat' => $s->alamat,
                'kontak' => $s->kontak,
                'whatsapp_tampil' => NomorHp::tampil($s->kontak),
                'whatsapp_tautan' => $s->kontak ? 'https://wa.me/'.preg_replace('/\D+/', '', $s->kontak) : null,
                'instagram' => $s->instagram,
                'instagram_tautan' => $s->instagram ? 'https://instagram.com/'.$s->instagram : null,
                'slogan' => $slogan->get($s->id),
                'status' => $this->statusPendaftaran($gelombang->get($s->id) ?? collect()),
                'logo' => $this->gambar->logo($s->kode, 'Logo '.$s->nama),
                'sampul' => $this->gambar->sampulSekolah($s->kode, $s->nama, $s->foto_sampul_path),
            ];
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function kontenYayasan(): array
    {
        $baris = KontenSekolah::query()
            ->tanpaSekolah()
            ->whereNull('sekolah_id')
            ->orderBy('urutan')
            ->orderBy('id')
            ->get(['bagian', 'judul', 'isi', 'gambar_path'])
            ->groupBy('bagian');

        $teks = fn (string $bagian) => $baris->get($bagian)?->first()?->isi;

        return [
            'nama' => (string) config('spmb.yayasan.nama'),
            'alamat' => config('spmb.yayasan.alamat'),
            'profil' => $teks('profil'),
            'visi' => $teks('visi'),
            'misi' => $this->nilaiDaftar($baris->get('misi')),
            'tautan_sosial' => $teks('tautan_sosial'),
            'logo' => $this->gambar->logoYayasan(),
        ];
    }

    /**
     * @param  Collection<int, Gelombang>  $gelombang
     * @return array<string, mixed>
     */
    private function statusPendaftaran($gelombang): array
    {
        $hariIni = Carbon::today();

        $aktif = $gelombang->first(function (Gelombang $baris) use ($hariIni) {
            $buka = $baris->tanggal_buka === null || $baris->tanggal_buka->lte($hariIni);
            $tutup = $baris->tanggal_tutup === null || $baris->tanggal_tutup->gte($hariIni);

            return $buka && $tutup;
        });

        $berikutnya = $gelombang->first(fn (Gelombang $baris) => $baris->tanggal_buka !== null && $baris->tanggal_buka->gt($hariIni));

        return [
            'dibuka' => $aktif !== null,
            'label' => $aktif ? 'Pendaftaran dibuka' : 'Belum dibuka',
            'gelombang' => $aktif?->nama,
            'tanggal_buka' => $aktif?->tanggal_buka?->translatedFormat('j F Y')
                ?? $berikutnya?->tanggal_buka?->translatedFormat('j F Y'),
            'tanggal_tutup' => $aktif?->tanggal_tutup?->translatedFormat('j F Y'),
        ];
    }

    /**
     * @param  Collection<int, KontenSekolah>|null  $baris
     * @return array<int, string>
     */
    private function nilaiDaftar($baris): array
    {
        return ($baris ?? collect())
            ->pluck('isi')
            ->filter(fn ($nilai) => filled($nilai))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function programTki(string $namaSekolah): array
    {
        return [
            ['kode' => 'KB', 'nama' => 'KB (Kelompok Bermain)', 'logo' => $this->gambar->logo('KB', 'Logo KB '.$namaSekolah)],
            ['kode' => 'TK', 'nama' => 'TK (Taman Kanak-kanak)', 'logo' => $this->gambar->logo('TK', 'Logo TK '.$namaSekolah)],
            ['kode' => 'TPA', 'nama' => 'TPA (Taman Penitipan Anak)', 'logo' => $this->gambar->logo('TPA', 'Logo TPA '.$namaSekolah)],
        ];
    }

    private function tahunAjaranAktif(): ?string
    {
        return TahunAjaran::query()->where('aktif', true)->orderByDesc('id')->value('nama');
    }

    private function kunci(string $nama): string
    {
        $versi = (int) Cache::get('publik:versi', 1);

        return 'publik:'.$versi.':'.$nama;
    }
}
