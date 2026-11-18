<?php

namespace App\Services;

use App\Exceptions\AturanBisnisException;
use App\Models\Berkas;
use App\Models\Pendaftaran;
use App\Models\User;
use App\Support\Catat;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Unduh seluruh berkas satu pendaftar sebagai ZIP.
 *
 * Keamanan (K-20): ZIP dibuat di folder sementara privat
 * (`storage/app/private/tmp/`) dengan nama acak, dialirkan ke pengguna,
 * lalu dihapus — setelah terkirim (deleteFileAfterSend) maupun bila gagal
 * (try/finally). Nama entri dibangun dari data terkontrol
 * `{nomor_peserta}_{kode_jenis_berkas}_{urutan}.{ekstensi}`, bukan dari nama
 * unggahan asli, dan tidak boleh mengandung `..`, `/`, atau `\`. Berkas yang
 * hilang dari disk dilewati dan dicatat, bukan membuat galat 500.
 */
class ZipBerkasService
{
    /**
     * Buat dan kembalikan respons unduhan ZIP untuk satu pendaftar.
     */
    public function unduh(User $pelaku, Pendaftaran $pendaftaran): BinaryFileResponse
    {
        $pendaftaran->loadMissing(['anak', 'gelombang']);

        $berkas = $pendaftaran->berkas()
            ->with('jenisBerkas')
            ->orderBy('id')
            ->get();

        if ($berkas->isEmpty()) {
            throw new AturanBisnisException('Pendaftaran ini belum punya berkas untuk diunduh.');
        }

        [$dipakai, $hilang] = $this->kumpulkan($berkas);

        if ($dipakai === []) {
            throw new AturanBisnisException('Tidak ada berkas yang dapat diunduh. Semua berkas tidak ditemukan di penyimpanan.');
        }

        $total = array_sum(array_column($dipakai, 'ukuran'));
        $maks = ((int) config('ekspor.zip_maks_mb')) * 1024 * 1024;

        if ($total > $maks) {
            $mb = number_format($maks / 1024 / 1024, 0, ',', '.');

            throw new AturanBisnisException(
                "Ukuran seluruh berkas lebih besar dari {$mb} MB sehingga tidak dapat diunduh sekaligus. Unduh berkas satu per satu."
            );
        }

        $folder = (string) config('ekspor.tmp_folder');
        $disk = Storage::disk('local');
        $disk->makeDirectory($folder);

        $namaTmp = $folder.'/'.Str::uuid().'.zip';
        $pathAbs = $disk->path($namaTmp);
        $berhasil = false;

        try {
            $zip = new ZipArchive;

            if ($zip->open($pathAbs, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new AturanBisnisException('ZIP gagal dibuat. Silakan coba lagi.');
            }

            $tertutup = false;

            try {
                $nomor = (string) ($pendaftaran->nomor_peserta ?? 'tanpa-nomor');
                $dipakaiEntri = [];

                foreach ($dipakai as $baris) {
                    /** @var Berkas $b */
                    $b = $baris['berkas'];

                    $namaEntri = $this->namaEntri($nomor, $b, $dipakaiEntri[$b->jenis_berkas_id] ?? 0);

                    if ($namaEntri === null) {
                        continue;
                    }

                    $isi = Storage::disk('local')->get((string) $b->path);

                    if ($isi === null || $isi === false) {
                        $hilang[] = $b->id;

                        continue;
                    }

                    $zip->addFromString($namaEntri, $isi);
                    $dipakaiEntri[$b->jenis_berkas_id] = ($dipakaiEntri[$b->jenis_berkas_id] ?? 0) + 1;
                }

                $zip->close();
                $tertutup = true;
                $berhasil = true;
            } finally {
                // ZipArchive ditutup bila belum (mis. lewat pengecualian),
                // supaya isi buffer tertulis dan file bisa dihapus bersih.
                if (! $tertutup) {
                    @$zip->close();
                }
            }

            if (! empty($hilang)) {
                Catat::aksi('unduh_zip_berkas_hilang', $pendaftaran, [
                    'berkas_id' => array_values($hilang),
                ], $pendaftaran->sekolah_id);
            }

            $namaZip = 'berkas-'.Str::slug($nomor).'-'.now()->format('Ymd-Hi').'.zip';

            Catat::aksi('unduh_zip', $pendaftaran, [
                'jumlah_berkas' => count($dipakai),
            ], $pendaftaran->sekolah_id);

            return response()
                ->download($pathAbs, $namaZip, [
                    'Content-Type' => 'application/zip',
                    'X-Content-Type-Options' => 'nosniff',
                    'Cache-Control' => 'no-store, private',
                ])
                ->deleteFileAfterSend(true);
        } finally {
            if (! $berhasil) {
                @unlink($pathAbs);
            }
        }
    }

    /**
     * Kumpulkan berkas yang benar-benar ada di disk privat dan ekstensinya
     * termasuk daftar putih. Berkas yang hilang atau bertipe tak dikenal
     * dilewati (dan dicatat pemanggil).
     *
     * @return array{0: list<array{berkas: Berkas, ukuran: int}>, 1: list<int>}
     */
    private function kumpulkan($berkas): array
    {
        $dipakai = [];
        $hilang = [];
        $disk = Storage::disk('local');

        foreach ($berkas as $b) {
            $path = (string) $b->path;

            if ($path === '') {
                $hilang[] = $b->id;

                continue;
            }

            // Ekstensi di luar daftar putih tidak pernah masuk ZIP.
            if ($this->ekstensiSah($path) === null) {
                $hilang[] = $b->id;

                continue;
            }

            if (! $disk->exists($path)) {
                $hilang[] = $b->id;

                continue;
            }

            $ukuran = (int) $disk->size($path);

            if ($ukuran <= 0) {
                $hilang[] = $b->id;

                continue;
            }

            $dipakai[] = ['berkas' => $b, 'ukuran' => $ukuran];
        }

        return [$dipakai, $hilang];
    }

    /**
     * Ekstensi yang sah (jpg, png, pdf), atau null bila tidak termasuk.
     */
    private function ekstensiSah(string $path): ?string
    {
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'png', 'pdf'], true) ? $ext : null;
    }

    /**
     * Nama entri ZIP dari data terkontrol. Ekstensi diperiksa daftar putih
     * (jpg, png, pdf); nama mengandung `..`, `/`, atau `\` ditolak.
     */
    private function namaEntri(string $nomor, Berkas $b, int $urutan): ?string
    {
        $ext = $this->ekstensiSah((string) $b->path);

        if ($ext === null) {
            return null;
        }

        $kodeJenis = Str::slug((string) ($b->jenisBerkas?->nama ?? 'berkas'));
        $kodeJenis = mb_substr($kodeJenis, 0, 40);

        if ($kodeJenis === '') {
            $kodeJenis = 'berkas';
        }

        // `{nomor_peserta}_{kode_jenis_berkas}_{urutan}.{ekstensi}`; urutan
        // selalu ikut agar nama unik dan dapat diprediksi.
        $nama = $nomor.'_'.$kodeJenis.'_'.($urutan + 1).'.'.$ext;

        // Pertahanan ganda terhadap zip-slip: nama entri harus polos.
        if ($nama === '' || str_contains($nama, '..') || str_contains($nama, '/') || str_contains($nama, '\\')) {
            return null;
        }

        return $nama;
    }
}
