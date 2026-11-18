<?php

namespace App\Services;

use App\Enums\PeranPengguna;
use App\Enums\StatusHasil;
use App\Enums\StatusPembayaran;
use App\Exceptions\AturanBisnisException;
use App\Models\Anak;
use App\Models\Gelombang;
use App\Models\JenisBerkas;
use App\Models\OrangTua;
use App\Models\Pendaftaran;
use App\Models\Pembayaran;
use App\Models\User;
use App\Support\Catat;
use App\Support\EksporSanitizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Ekspor data pendaftar (xlsx/csv) dan rekap agregat.
 *
 * Keamanan (K-20): query selalu dibatasi sekolah pengguna (global scope
 * `MilikSekolah` + `where` eksplisit), hanya pendaftaran terkirim, kolom dari
 * daftar putih `config/ekspor.php`, tiap sel teks lewat `EksporSanitizer`,
 * maksimum `config('ekspor.batas_baris')` baris, dan setiap unduhan dicatat.
 * File sementara dibuat di `storage/app/private/tmp/` dan dihapus setelah
 * dikirim (`deleteFileAfterSend`) atau langsung bila gagal.
 */
class EksporService
{
    public function __construct(
        private readonly RekapService $rekap,
    ) {}

    /**
     * Bangun query pendaftar terkirim milik sekolah pengguna beserta
     * penyaring yang sama dengan halaman daftar pendaftar.
     *
     * @param  array<string, mixed>  $saring
     * @return \Illuminate\Database\Eloquent\Builder<Pendaftaran>
     */
    public function queryPendaftar(User $pelaku, array $saring = []): \Illuminate\Database\Eloquent\Builder
    {
        $cari = trim((string) ($saring['cari'] ?? ''));
        $gelombangId = (int) ($saring['gelombang'] ?? 0) ?: null;
        $status = (string) ($saring['status'] ?? '');
        $jenis = (string) ($saring['jenis'] ?? '');
        $pembayaran = (string) ($saring['pembayaran'] ?? '');
        $program = (string) ($saring['program'] ?? '');
        $kelengkapan = (string) ($saring['kelengkapan'] ?? '');

        return Pendaftaran::query()
            ->terkirim()
            // Pertahanan ganda: global scope MilikSekolah sudah aktif untuk
            // panitia/admin, tapi batasan sekolah dinyatakan eksplisit.
            ->where('pendaftaran.sekolah_id', (int) $pelaku->sekolah_id)
            ->with(['anak', 'gelombang', 'berkas', 'pembayaran', 'daftarUlang'])
            ->when($cari !== '', function ($query) use ($cari) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $cari);
                $pola = '%'.$escaped.'%';

                $query->where(function ($sub) use ($pola) {
                    $sub->whereHas('anak', fn ($anak) => $anak->whereRaw("anak.nama_lengkap LIKE ? ESCAPE '\\'", [$pola]))
                        ->orWhereRaw("pendaftaran.nomor_peserta LIKE ? ESCAPE '\\'", [$pola]);
                });
            })
            ->when($gelombangId, fn ($query) => $query->where('gelombang_id', $gelombangId))
            ->when(in_array($status, array_column(StatusHasil::cases(), 'value'), true),
                fn ($query) => $query->where('status_hasil', $status))
            ->when($jenis === 'baru', fn ($query) => $query->where('lulusan_yayasan', false))
            ->when($jenis === 'lulusan', fn ($query) => $query->where('lulusan_yayasan', true))
            ->when($program !== '', fn ($query) => $query->where('program', strtoupper($program)))
            ->when($pembayaran === 'menunggu', fn ($query) => $query->whereHas('pembayaran',
                fn ($bayar) => $bayar->where('status', StatusPembayaran::Menunggu)))
            ->when($pembayaran === 'disetujui', fn ($query) => $query->whereHas('pembayaran',
                fn ($bayar) => $bayar->where('status', StatusPembayaran::Disetujui)))
            ->when($pembayaran === 'ditolak', fn ($query) => $query->whereHas('pembayaran',
                fn ($bayar) => $bayar->where('status', StatusPembayaran::Ditolak)))
            ->when($pembayaran === 'belum', fn ($query) => $query->whereDoesntHave('pembayaran'))
            ->when($kelengkapan === 'perlu_perbaikan', fn ($query) => $query->whereHas('berkas',
                fn ($berkas) => $berkas->where('status', \App\Enums\StatusBerkas::PerluPerbaikan)))
            ->when($kelengkapan === 'lengkap', fn ($query) => $query->whereDoesntHave('berkas',
                fn ($berkas) => $berkas->where('status', \App\Enums\StatusBerkas::PerluPerbaikan)))
            ->orderBy('pendaftaran.id', 'asc');
    }

    /**
     * Kembalikan definisi kolom terpilih dalam urutan pengiriman. Kolom di
     * luar daftar putih dibuang tanpa galat (tolak secara bawaan).
     *
     * @param  array<int|string, mixed>  $dipilih
     * @return array<string, array{label: string, teks?: bool}>
     */
    public function kolomTerpilih(User $pelaku, array $dipilih, bool $ikutSensitif, bool $konfirmasi): array
    {
        $boleh = [];

        foreach (config('ekspor.grup') as $grup) {
            foreach ($grup['kolom'] as $kunci => $definisi) {
                $boleh[$kunci] = ['label' => $definisi['label']];
            }
        }

        $sensitifDiizinkan = $ikutSensitif && $konfirmasi
            && $pelaku->peran === PeranPengguna::Admin;

        if ($sensitifDiizinkan) {
            foreach (config('ekspor.sensitif') as $grup) {
                foreach ($grup['kolom'] as $kunci => $definisi) {
                    $boleh[$kunci] = ['label' => $definisi['label'], 'teks' => (bool) ($definisi['teks'] ?? false)];
                }
            }
        }

        $terpilih = [];

        foreach (array_keys($dipilih) as $kunci) {
            if (isset($boleh[$kunci])) {
                $terpilih[$kunci] = $boleh[$kunci];
            }
        }

        // Nomor peserta selalu ikut agar baris tetap dapat diidentifikasi.
        if (! isset($terpilih['nomor_peserta'])) {
            $terpilih = ['nomor_peserta' => $boleh['nomor_peserta']] + $terpilih;
        }

        return $terpilih;
    }

    /**
     * Jalankan ekspor data pendaftar dan kembalikan respons unduhan.
     *
     * @param  array<string, mixed>  $saring
     * @param  array<int|string, mixed>  $dipilih
     */
    public function pendaftar(User $pelaku, array $saring, array $dipilih, string $format, bool $ikutSensitif, bool $konfirmasiSensitif): BinaryFileResponse
    {
        $format = $format === 'csv' ? 'csv' : 'xlsx';
        $kolom = $this->kolomTerpilih($pelaku, $dipilih, $ikutSensitif, $konfirmasiSensitif);
        $query = $this->queryPendaftar($pelaku, $saring);

        $jumlah = (clone $query)->count();
        $batas = (int) config('ekspor.batas_baris');

        if ($jumlah > $batas) {
            throw new AturanBisnisException(
                "Data {$jumlah} pendaftar terlalu besar untuk satu unduhan (maksimal {$batas} baris). Persempit dengan penyaring."
            );
        }

        if ($jumlah === 0) {
            throw new AturanBisnisException('Tidak ada pendaftar yang cocok dengan penyaring.');
        }

        $namaFile = $this->namaFilePendaftar($pelaku, $saring, $format);
        $pathLokal = $this->buatFileSementara($format);

        try {
            $ditulis = $this->tulisIsiPendaftar($pelaku, $query, $kolom, $saring, $format, $pathLokal);
        } catch (\Throwable $galat) {
            // Jangan tinggalkan file sementara bila penulisan gagal.
            @unlink($pathLokal);

            throw $galat;
        }

        Catat::aksi('ekspor_pendaftar', null, [
            'format' => $format,
            'saring' => $this->ringkasSaring($saring),
            'jumlah_baris' => $ditulis,
            'kolom' => array_column($kolom, 'label'),
        ], (int) $pelaku->sekolah_id);

        return $this->responsUnduhan($pathLokal, $namaFile, $format);
    }

    /**
     * Tulis isi ekspor ke file sementara dan kembalikan jumlah baris data.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Pendaftaran>  $query
     * @param  array<string, array{label: string, teks?: bool}>  $kolom
     * @param  array<string, mixed>  $saring
     */
    private function tulisIsiPendaftar(User $pelaku, $query, array $kolom, array $saring, string $format, string $pathLokal): int
    {
        $anakIds = (clone $query)->pluck('pendaftaran.anak_id');

        $pemilik = Anak::query()
            ->whereIn('id', $anakIds)
            ->pluck('user_id', 'id');

        $orangTua = OrangTua::query()
            ->whereIn('user_id', $pemilik->unique()->values())
            ->get()
            ->groupBy('user_id');

        // Jumlah jenis berkas aktif sekolah, dihitung sekali (bukan per baris).
        $totalJenis = JenisBerkas::query()
            ->where('sekolah_id', (int) $pelaku->sekolah_id)
            ->where('aktif', true)
            ->count();

        if ($format === 'xlsx') {
            $opsi = new \OpenSpout\Writer\XLSX\Options();
            $penulis = new XlsxWriter($opsi);
        } else {
            $penulis = new CsvWriter(new \OpenSpout\Writer\CSV\Options());
        }

        $penulis->openToFile($pathLokal);

        try {
            if ($format === 'xlsx') {
                $penulis->getCurrentSheet()->setName('Data');
            }

            $penulis->addRow(Row::fromValues(EksporSanitizer::baris(array_column($kolom, 'label'))));

            $jumlah = 0;

            foreach ($query->lazy() as $pendaftaran) {
                /** @var Pendaftaran $pendaftaran */
                $pendaftaran->loadMissing('anak');
                $baris = $this->barisPendaftar(
                    $pendaftaran,
                    $kolom,
                    $orangTua->get($pendaftaran->anak->user_id, collect()),
                    $totalJenis,
                );
                $penulis->addRow(Row::fromValues($baris));
                $jumlah++;
            }

            if ($format === 'xlsx') {
                $sheet = $penulis->addNewSheetAndMakeItCurrent();
                $sheet->setName('Ringkasan');

                foreach ($this->ringkasanEkspor($pelaku, $saring, $jumlah, array_column($kolom, 'label')) as $r) {
                    $penulis->addRow(Row::fromValues(EksporSanitizer::baris($r)));
                }
            }
        } finally {
            $penulis->close();
        }

        return $jumlah;
    }

    /**
     * Nilai satu baris pendaftar mengikuti kolom terpilih.
     *
     * @param  Collection<int, OrangTua>  $orangTua
     * @return array<int, string|float|int>
     */
    private function barisPendaftar(Pendaftaran $pendaftaran, array $kolom, Collection $orangTua, int $totalJenis = 0): array
    {
        $anak = $pendaftaran->anak;
        $wali = $this->dataWali($orangTua);
        $pembayaran = $pendaftaran->pembayaran;
        $daftarUlang = $pendaftaran->daftarUlang;

        $nilai = [
            'nomor_peserta' => (string) ($pendaftaran->nomor_peserta ?? ''),
            'nama' => (string) ($anak->nama_lengkap ?? ''),
            'jenis_kelamin' => match ($anak->jenis_kelamin) {
                'L' => 'Laki-laki',
                'P' => 'Perempuan',
                default => (string) ($anak->jenis_kelamin ?? ''),
            },
            'tempat_lahir' => (string) ($anak->tempat_lahir ?? ''),
            'tanggal_lahir' => $anak->tanggal_lahir?->format('d-m-Y') ?? '',
            'asal_sekolah' => (string) ($anak->asal_sekolah ?? ''),
            'jenis' => $pendaftaran->lulusan_yayasan ? 'Lulusan yayasan' : 'Siswa baru',
            'program' => (string) ($pendaftaran->program ?? ''),
            'gelombang' => (string) ($pendaftaran->gelombang?->nama ?? ''),
            'wali_nama' => (string) ($wali['nama'] ?? ''),
            'wali_no_hp' => (string) ($wali['no_hp'] ?? ''),
            'alamat' => (string) ($anak->alamat ?? ''),
            'status_hasil' => $pendaftaran->status_hasil?->label() ?? '',
            'status_murid' => $pendaftaran->status_murid?->label() ?? '',
            'tanggal_kirim' => $pendaftaran->dikirim_pada?->format('d-m-Y H:i') ?? '',
            'berkas_sesuai' => $pendaftaran->berkas->where('status', \App\Enums\StatusBerkas::Sesuai)->count()
                .'/'.$totalJenis,
            'status_pembayaran' => $this->statusPembayaranGabung($pembayaran),
            'total_disetujui' => (int) $pembayaran->where('status', StatusPembayaran::Disetujui)->sum('nominal'),
            'hadir' => $daftarUlang === null ? '' : ($daftarUlang->hadir ? 'Ya' : 'Tidak'),
            'selesai' => $daftarUlang === null ? '' : ($daftarUlang->selesai ? 'Ya' : 'Belum'),
            // Kolom sensitif (hanya admin dengan konfirmasi).
            'nik' => (string) ($anak->getAttribute('nik') ?? ''),
            'no_kk' => (string) ($anak->getAttribute('no_kk') ?? ''),
            'riwayat_penyakit' => (string) ($anak->riwayat_penyakit ?? ''),
            'alergi' => (string) ($anak->alergi ?? ''),
            'kebutuhan_khusus' => (string) ($anak->kebutuhan_khusus ?? ''),
        ];

        $baris = [];

        foreach ($kolom as $kunci => $definisi) {
            $isi = $nilai[$kunci] ?? '';

            // Nilai dikirim sebagai string; open spout menulis string sebagai
            // teks (bukan angka), sehingga NIK dan no. KK tidak berubah
            // menjadi notasi ilmiah di Excel.
            $baris[] = EksporSanitizer::sel($isi);
        }

        return $baris;
    }

    /**
     * Nama wali murid untuk ekspor: baris `wali` lebih dulu, lalu ayah, lalu
     * ibu (data kontak keluarga yang bisa dihubungi).
     *
     * @param  Collection<int, OrangTua>  $orangTua
     * @return array{nama: string, no_hp: string}|null
     */
    private function dataWali(Collection $orangTua): ?array
    {
        foreach (['wali', 'ayah', 'ibu'] as $hubungan) {
            $baris = $orangTua->first(fn (OrangTua $ortu) => $ortu->hubungan->value === $hubungan && filled($ortu->nama));

            if ($baris !== null) {
                return ['nama' => $baris->nama, 'no_hp' => (string) $baris->no_hp];
            }
        }

        return null;
    }

    /**
     * Status pembayaran gabungan satu pendaftar untuk ekspor: Disetujui bila
     * ada yang disetujui, lalu Menunggu, Ditolak, atau Belum ada.
     *
     * @param  Collection<int, Pembayaran>  $pembayaran
     */
    private function statusPembayaranGabung(Collection $pembayaran): string
    {
        if ($pembayaran->contains('status', StatusPembayaran::Disetujui)) {
            return 'Disetujui';
        }

        if ($pembayaran->contains('status', StatusPembayaran::Menunggu)) {
            return 'Menunggu';
        }

        if ($pembayaran->contains('status', StatusPembayaran::Ditolak)) {
            return 'Ditolak';
        }

        return 'Belum ada';
    }

    /**
     * Ekspor rekap agregat sekolah (tombol di /admin/rekap). Isi persis angka
     * agregat yang tampil di halaman; tidak ada nama orang.
     */
    public function rekapSekolah(User $pelaku): BinaryFileResponse
    {
        $rekap = $this->rekap->untukSekolah((int) $pelaku->sekolah_id);

        $pathLokal = $this->buatFileSementara('xlsx');
        $opsi = new \OpenSpout\Writer\XLSX\Options();
        $penulis = new XlsxWriter($opsi);
        $penulis->openToFile($pathLokal);

        try {
            $penulis->getCurrentSheet()->setName('Rekap');
            $jumlah = $this->tulisRekap($penulis, [$rekap]);
        } finally {
            $penulis->close();
        }

        $kode = $rekap['sekolah']->kode ?? 'sekolah';

        Catat::aksi('ekspor_rekap', null, [
            'jenis' => 'sekolah',
            'jumlah_baris' => $jumlah,
            'kolom' => ['Nama', 'Angka'],
        ], (int) $pelaku->sekolah_id);

        return $this->responsUnduhan($pathLokal, $this->namaFileRekap('rekap-'.Str::slug($kode), 'xlsx'), 'xlsx');
    }

    /**
     * Ekspor rekap agregat seluruh sekolah untuk yayasan (K-18: agregat saja).
     */
    public function rekapYayasan(User $pelaku): BinaryFileResponse
    {
        $rekap = $this->rekap->untukYayasan();

        $pathLokal = $this->buatFileSementara('xlsx');
        $opsi = new \OpenSpout\Writer\XLSX\Options();
        $penulis = new XlsxWriter($opsi);
        $penulis->openToFile($pathLokal);

        try {
            $penulis->getCurrentSheet()->setName('Rekap');
            $jumlah = $this->tulisRekap($penulis, $rekap['sekolah']);
        } finally {
            $penulis->close();
        }

        Catat::aksi('ekspor_rekap', null, [
            'jenis' => 'yayasan',
            'jumlah_baris' => $jumlah,
            'kolom' => ['Nama', 'Angka'],
        ], null);

        return $this->responsUnduhan($pathLokal, $this->namaFileRekap('rekap-yayasan', 'xlsx'), 'xlsx');
    }

    /**
     * Tulis isi rekap agregat; kembalikan jumlah baris data.
     *
     * @param  array<int, array<string, mixed>>  $daftarSekolah
     */
    private function tulisRekap(XlsxWriter $penulis, array $daftarSekolah): int
    {
        $labelStatus = array_column(StatusHasil::cases(), 'value');
        $labelPembayaran = array_column(StatusPembayaran::cases(), 'value');

        $jumlah = 0;

        foreach ($daftarSekolah as $i => $s) {
            $namaSekolah = $s['sekolah'] instanceof \App\Models\Sekolah
                ? (string) $s['sekolah']->nama
                : (string) ($s['sekolah']['nama'] ?? 'Sekolah');

            if ($i > 0) {
                $penulis->addRow(Row::fromValues(['']));
            }

            $penulis->addRow(Row::fromValues(['Sekolah', (string) $namaSekolah]));

            foreach ($s['gelombang'] as $g) {
                $penulis->addRow(Row::fromValues(['Gelombang', (string) $g['nama']]));
                $jumlah++;

                $barisStatus = [];

                foreach ($labelStatus as $kunci) {
                    $barisStatus[] = [$kunci, (int) $g['status'][$kunci]];
                }

                foreach ([
                    ['total', $g['total']],
                    ['lulusan_yayasan', $g['lulusan_yayasan']],
                    ['bukan_lulusan_yayasan', $g['bukan_lulusan_yayasan']],
                    ['daftar_ulang_selesai', $g['daftar_ulang_selesai']],
                    ['daftar_ulang_belum', $g['daftar_ulang_belum']],
                    ['mengundurkan_diri', $g['mengundurkan_diri']],
                    ['kuota', $g['kuota']],
                    ['terisi', $g['terisi']],
                    ['diumumkan', $g['diumumkan'] ? 'Ya' : 'Belum'],
                ] as [$kunci, $nilai]) {
                    $barisStatus[] = [$kunci, $nilai];
                }

                foreach ($barisStatus as [$kunci, $nilai]) {
                    $penulis->addRow(Row::fromValues(EksporSanitizer::baris([$kunci, $nilai])));
                    $jumlah++;
                }

                foreach ($labelPembayaran as $kunci) {
                    $penulis->addRow(Row::fromValues(['pembayaran_'.$kunci, (int) $g['pembayaran'][$kunci]]));
                    $jumlah++;
                }

                foreach ($g['program'] as $kunci => $nilai) {
                    $penulis->addRow(Row::fromValues(EksporSanitizer::baris(['program_'.$kunci, (int) $nilai])));
                    $jumlah++;
                }
            }

            $r = $s['ringkasan'];
            $penulis->addRow(Row::fromValues(['Ringkasan sekolah']));

            foreach ([
                ['total', $r['total']],
                ['lulusan_yayasan', $r['lulusan_yayasan']],
                ['bukan_lulusan_yayasan', $r['bukan_lulusan_yayasan']],
                ['daftar_ulang_selesai', $r['daftar_ulang_selesai']],
                ['daftar_ulang_belum', $r['daftar_ulang_belum']],
                ['mengundurkan_diri', $r['mengundurkan_diri']],
                ['kuota', $r['kuota']],
                ['terisi', $r['terisi']],
            ] as [$kunci, $nilai]) {
                $penulis->addRow(Row::fromValues([$kunci, $nilai]));
                $jumlah++;
            }

            foreach ($labelStatus as $kunci) {
                $penulis->addRow(Row::fromValues(['status_'.$kunci, (int) $r['status'][$kunci]]));
                $jumlah++;
            }

            foreach ($labelPembayaran as $kunci) {
                $penulis->addRow(Row::fromValues(['pembayaran_'.$kunci, (int) $r['pembayaran'][$kunci]]));
                $jumlah++;
            }

            foreach ($r['program'] as $kunci => $nilai) {
                $penulis->addRow(Row::fromValues(EksporSanitizer::baris(['program_'.$kunci, (int) $nilai])));
                $jumlah++;
            }
        }

        return $jumlah;
    }

    /**
     * Nama file ekspor dibuat dari data terkontrol, bukan dari input pengguna.
     *
     * @param  array<string, mixed>  $saring
     */
    private function namaFilePendaftar(User $pelaku, array $saring, string $format): string
    {
        $kode = strtolower((string) ($pelaku->sekolah?->kode ?? 'sekolah'));
        $gelombangId = (int) ($saring['gelombang'] ?? 0);

        $gelombang = $gelombangId
            ? Gelombang::query()->find($gelombangId)
            : null;

        $bagian = $gelombang?->nama ? Str::slug($gelombang->nama) : 'semua-gelombang';

        return 'spmb-'.$kode.'-'.$bagian.'-'.now()->format('Ymd').'.'.$format;
    }

    private function namaFileRekap(string $bagian, string $format): string
    {
        return 'spmb-'.$bagian.'-'.now()->format('Ymd').'.'.$format;
    }

    /**
     * Isi lembar Ringkasan ekspor data pendaftar (tanpa data pribadi).
     *
     * @param  array<string, mixed>  $saring
     * @return array<int, array<int, string|int>>
     */
    private function ringkasanEkspor(User $pelaku, array $saring, int $jumlahBaris, array $kolom): array
    {
        return [
            ['Ringkasan ekspor data pendaftar'],
            ['Tanggal ekspor', now()->format('d-m-Y H:i')],
            ['Pelaku', $pelaku->nama.' ('.$pelaku->peran?->label().')'],
            ['Sekolah', (string) ($pelaku->sekolah?->nama ?? '')],
            ['Penyaring', $this->ringkasSaring($saring) ?: 'Tanpa penyaring'],
            ['Jumlah baris', $jumlahBaris],
            ['Kolom', implode(', ', $kolom)],
        ];
    }

    /**
     * Ringkasan penyaring untuk catatan log dan lembar Ringkasan.
     *
     * @param  array<string, mixed>  $saring
     */
    private function ringkasSaring(array $saring): string
    {
        $pasangan = [];

        foreach (['cari', 'gelombang', 'status', 'jenis', 'pembayaran', 'program', 'kelengkapan'] as $kunci) {
            $nilai = trim((string) ($saring[$kunci] ?? ''));

            if ($nilai !== '') {
                $pasangan[] = $kunci.':'.$nilai;
            }
        }

        return implode(', ', $pasangan);
    }

    private function buatFileSementara(string $format): string
    {
        $folder = (string) config('ekspor.tmp_folder');
        $akar = Storage::disk('local');

        $akar->makeDirectory($folder);

        return $akar->path($folder.'/'.Str::uuid().'.'.$format);
    }

    /**
     * Respons unduhan dengan header K-20; file sementara dihapus setelah
     * dikirim, dan dihapus juga bila gagal sebelum terkirim.
     */
    private function responsUnduhan(string $pathLokal, string $namaFile, string $format): BinaryFileResponse
    {
        try {
            $mime = $format === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

            return response()
                ->download($pathLokal, $namaFile, [
                    'Content-Type' => $mime,
                    'X-Content-Type-Options' => 'nosniff',
                    'Cache-Control' => 'no-store, private',
                ])
                ->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            @unlink($pathLokal);

            throw $e;
        }
    }
}
