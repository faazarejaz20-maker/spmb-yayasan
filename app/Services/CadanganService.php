<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Cadangan dan pemulihan terenkripsi (K-13).
 *
 * Satu arsip berisi dump basis data dan folder berkas privat. Arsip dienkripsi
 * dengan OpenSSL AES-256-CBC lalu diberi HMAC-SHA256 (encrypt-then-MAC) memakai
 * kunci dari `CADANGAN_KUNCI`. Tanpa kunci itu arsip tidak dapat dibuka.
 *
 * Catatan lingkungan: `openssl` CLI tidak tersedia di server uji, jadi
 * enkripsi dilakukan lewat ekstensi PHP OpenSSL. Untuk MySQL, `mysqldump`
 * dipanggil dengan berkas opsi sementara agar kata sandi tidak tampil di
 * daftar proses.
 */
class CadanganService
{
    private const MAGIC = 'SPMBCAD1';

    private const UKURAN_BLOK = 1048576;

    /**
     * Buat satu arsip cadangan terenkripsi. Mengembalikan path arsip.
     */
    public function buat(): string
    {
        $kunci = (string) config('spmb.cadangan.kunci');

        if ($kunci === '') {
            throw new RuntimeException('CADANGAN_KUNCI belum diisi. Cadangan tidak dapat dibuat tanpa kunci enkripsi.');
        }

        $folder = $this->folder();
        $this->pastikanFolder($folder);

        $kerja = $this->folderSementara();

        try {
            $dump = $this->dumpDatabase($kerja);
            $zip = $this->buatZip($kerja, $dump);

            $nama = 'spmb-'.now()->format('Ymd-His').'.enc';
            $tujuan = $folder.DIRECTORY_SEPARATOR.$nama;

            $this->enkripsiBerkas($zip, $tujuan, $kunci);

            @chmod($tujuan, 0600);

            return $tujuan;
        } finally {
            $this->hapusFolder($kerja);
        }
    }

    /**
     * Pulihkan arsip cadangan. Basis data harus kosong kecuali pemanggil
     * memang memaksa.
     */
    public function pulihkan(string $berkas): void
    {
        $kunci = (string) config('spmb.cadangan.kunci');

        if ($kunci === '') {
            throw new RuntimeException('CADANGAN_KUNCI belum diisi. Arsip tidak dapat dibuka.');
        }

        if (! is_file($berkas)) {
            throw new RuntimeException('Berkas cadangan tidak ditemukan: '.basename($berkas));
        }

        $kerja = $this->folderSementara();

        try {
            $zip = $kerja.DIRECTORY_SEPARATOR.'cadangan.zip';
            $this->dekripsiBerkas($berkas, $zip, $kunci);
            $isi = $this->bukaZip($zip, $kerja);

            if (! isset($isi['database'])) {
                throw new RuntimeException('Arsip cadangan tidak berisi dump basis data.');
            }

            $this->pulihkanDatabase($isi['database']);
            $this->pulihkanBerkas($kerja);
        } finally {
            $this->hapusFolder($kerja);
        }
    }

    /**
     * Terapkan retensi: 14 cadangan harian dan satu per bulan selama 6 bulan.
     *
     * @return array<int, string> Nama berkas yang dihapus.
     */
    public function retensi(): array
    {
        $folder = $this->folder();

        if (! is_dir($folder)) {
            return [];
        }

        $berkas = glob($folder.DIRECTORY_SEPARATOR.'*.enc');

        if ($berkas === false) {
            return [];
        }

        usort($berkas, fn (string $a, string $b) => filemtime($a) <=> filemtime($b));

        $batasHarian = now()->subDays((int) config('spmb.cadangan.retensi_harian', 14))->getTimestamp();
        $batasBulanan = now()->subMonths((int) config('spmb.cadangan.retensi_bulanan', 6))->getTimestamp();
        $bulanTersimpan = [];
        $dihapus = [];

        foreach ($berkas as $path) {
            $waktu = filemtime($path) ?: 0;

            if ($waktu >= $batasHarian) {
                continue;
            }

            $bulan = date('Y-m', $waktu);

            if ($waktu >= $batasBulanan && ! isset($bulanTersimpan[$bulan])) {
                $bulanTersimpan[$bulan] = true;

                continue;
            }

            if (@unlink($path)) {
                $dihapus[] = basename($path);
            }
        }

        return $dihapus;
    }

    /**
     * @return array<int, string>
     */
    public function daftar(): array
    {
        $folder = $this->folder();

        if (! is_dir($folder)) {
            return [];
        }

        $berkas = glob($folder.DIRECTORY_SEPARATOR.'*.enc') ?: [];
        rsort($berkas, SORT_STRING);

        return array_map('basename', $berkas);
    }

    public function folder(): string
    {
        return storage_path('app/'.trim((string) config('spmb.cadangan.folder', 'cadangan'), '/\\'));
    }

    private function pastikanFolder(string $folder): void
    {
        if (! is_dir($folder) && ! mkdir($folder, 0700, true) && ! is_dir($folder)) {
            throw new RuntimeException('Folder cadangan tidak dapat dibuat: '.$folder);
        }

        @chmod($folder, 0700);
    }

    private function folderSementara(): string
    {
        $folder = storage_path('app/tmp/cadangan-'.bin2hex(random_bytes(8)));

        if (! mkdir($folder, 0700, true) && ! is_dir($folder)) {
            throw new RuntimeException('Folder kerja cadangan tidak dapat dibuat.');
        }

        return $folder;
    }

    private function dumpDatabase(string $kerja): string
    {
        $driver = (string) config('database.default');
        $koneksi = (array) config('database.connections.'.$driver, []);

        if (($koneksi['driver'] ?? $driver) === 'sqlite') {
            return $this->dumpSqlite($kerja, (string) ($koneksi['database'] ?? ''));
        }

        return $this->dumpMysql($kerja, $koneksi);
    }

    private function dumpSqlite(string $kerja, string $basisData): string
    {
        if ($basisData === '' || $basisData === ':memory:') {
            throw new RuntimeException('Cadangan SQLite membutuhkan berkas basis data, bukan basis data di memori.');
        }

        if (! is_file($basisData)) {
            throw new RuntimeException('Berkas basis data SQLite tidak ditemukan.');
        }

        $tujuan = $kerja.DIRECTORY_SEPARATOR.'database.sqlite';

        $pdo = new \PDO('sqlite:'.$basisData);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('VACUUM INTO '.$pdo->quote($tujuan));
        $pdo = null;

        if (! is_file($tujuan)) {
            throw new RuntimeException('Dump basis data SQLite gagal dibuat.');
        }

        return $tujuan;
    }

    /**
     * @param  array<string, mixed>  $koneksi
     */
    private function dumpMysql(string $kerja, array $koneksi): string
    {
        $tujuan = $kerja.DIRECTORY_SEPARATOR.'database.sql';
        $opsi = $this->berkasOpsi($kerja, $koneksi);

        try {
            $perintah = [
                (string) config('spmb.cadangan.perintah_mysqldump', 'mysqldump'),
                '--defaults-extra-file='.$opsi,
                '--single-transaction',
                '--quick',
                '--routines',
                '--skip-comments',
                '--default-character-set=utf8mb4',
                (string) ($koneksi['database'] ?? ''),
            ];

            $proses = new Process($perintah);
            $proses->setTimeout(900);

            $keluar = fopen($tujuan, 'wb');

            if ($keluar === false) {
                throw new RuntimeException('Berkas dump tidak dapat dibuat.');
            }

            try {
                $proses->run(function (string $tipe, string $buffer) use ($keluar): void {
                    if ($tipe === Process::OUT) {
                        fwrite($keluar, $buffer);
                    }
                });
            } finally {
                fclose($keluar);
            }

            if (! $proses->isSuccessful()) {
                throw new RuntimeException('mysqldump gagal: '.trim($proses->getErrorOutput()));
            }
        } finally {
            @unlink($opsi);
        }

        return $tujuan;
    }

    /**
     * @param  array<string, mixed>  $koneksi
     */
    private function berkasOpsi(string $kerja, array $koneksi): string
    {
        $opsi = $kerja.DIRECTORY_SEPARATOR.'mysql.cnf';

        $isi = "[client]\n"
            .'user='.(string) ($koneksi['username'] ?? '')."\n"
            .'password='.(string) ($koneksi['password'] ?? '')."\n"
            .'host='.(string) ($koneksi['host'] ?? '127.0.0.1')."\n"
            .'port='.(string) ($koneksi['port'] ?? '3306')."\n";

        if (file_put_contents($opsi, $isi) === false) {
            throw new RuntimeException('Berkas opsi MySQL tidak dapat dibuat.');
        }

        @chmod($opsi, 0600);

        return $opsi;
    }

    private function buatZip(string $kerja, string $dump): string
    {
        $zipPath = $kerja.DIRECTORY_SEPARATOR.'cadangan.zip';
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Arsip ZIP cadangan tidak dapat dibuat.');
        }

        try {
            $zip->addFile($dump, basename($dump));

            $privat = storage_path('app/private');

            if (is_dir($privat)) {
                $this->tambahFolder($zip, $privat, 'berkas');
            }

            $zip->addFromString('manifest.json', json_encode([
                'format' => 1,
                'aplikasi' => (string) config('app.name'),
                'dibuat_pada' => now()->toIso8601String(),
                'zona_waktu' => config('app.timezone'),
                'driver_basis_data' => (string) config('database.default'),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } finally {
            $zip->close();
        }

        return $zipPath;
    }

    private function tambahFolder(ZipArchive $zip, string $folder, string $awalan): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $berkas) {
            if (! $berkas instanceof \SplFileInfo || ! $berkas->isFile()) {
                continue;
            }

            $relatif = str_replace('\\', '/', substr($berkas->getPathname(), strlen($folder) + 1));
            $nama = $awalan.'/'.$relatif;

            // Nama entri ZIP tidak boleh keluar dari folder tujuan saat dipulihkan.
            if (str_contains($nama, '..')) {
                continue;
            }

            $zip->addFile($berkas->getPathname(), $nama);
        }
    }

    private function enkripsiBerkas(string $sumber, string $tujuan, string $sandi): void
    {
        $masuk = fopen($sumber, 'rb');

        if ($masuk === false) {
            throw new RuntimeException('Arsip sumber tidak dapat dibaca.');
        }

        $keluar = fopen($tujuan, 'wb');

        if ($keluar === false) {
            fclose($masuk);

            throw new RuntimeException('Arsip cadangan tidak dapat ditulis.');
        }

        try {
            $salt = random_bytes(16);
            $iv = random_bytes(16);
            [$enkripsi, $mac] = $this->turunanKunci($sandi, $salt);

            $header = self::MAGIC.$salt.$iv;
            fwrite($keluar, $header);

            $hmac = hash_init('sha256', HASH_HMAC, $mac);
            hash_update($hmac, $header);

            $sisa = '';

            while (! feof($masuk)) {
                $blok = fread($masuk, self::UKURAN_BLOK);

                if ($blok === false || $blok === '') {
                    break;
                }

                $sisa .= $blok;

                while (strlen($sisa) >= self::UKURAN_BLOK) {
                    $potong = substr($sisa, 0, self::UKURAN_BLOK);
                    $sisa = substr($sisa, self::UKURAN_BLOK);

                    $cipher = openssl_encrypt(
                        $potong,
                        'aes-256-cbc',
                        $enkripsi,
                        OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
                        $iv
                    );

                    if ($cipher === false) {
                        throw new RuntimeException('Enkripsi cadangan gagal.');
                    }

                    fwrite($keluar, $cipher);
                    hash_update($hmac, $cipher);
                }
            }

            $akhir = openssl_encrypt($sisa, 'aes-256-cbc', $enkripsi, OPENSSL_RAW_DATA, $iv);

            if ($akhir === false) {
                throw new RuntimeException('Enkripsi cadangan gagal.');
            }

            fwrite($keluar, $akhir);
            hash_update($hmac, $akhir);
            fwrite($keluar, hash_final($hmac, true));
        } finally {
            fclose($masuk);
            fclose($keluar);
        }
    }

    private function dekripsiBerkas(string $sumber, string $tujuan, string $sandi): void
    {
        $masuk = fopen($sumber, 'rb');

        if ($masuk === false) {
            throw new RuntimeException('Arsip cadangan tidak dapat dibaca.');
        }

        try {
            $ukuran = filesize($sumber) ?: 0;
            $header = fread($masuk, 40);

            if (strlen($header) !== 40 || substr($header, 0, 8) !== self::MAGIC) {
                throw new RuntimeException('Berkas ini bukan arsip cadangan SPMB yang sah.');
            }

            $salt = substr($header, 8, 16);
            $iv = substr($header, 24, 16);
            [$enkripsi, $mac] = $this->turunanKunci($sandi, $salt);

            $panjang = $ukuran - 40 - 32;

            if ($panjang <= 0 || $panjang % 16 !== 0) {
                throw new RuntimeException('Arsip cadangan rusak (ukuran tidak sesuai).');
            }

            $hmac = hash_init('sha256', HASH_HMAC, $mac);
            hash_update($hmac, $header);

            $sisaBaca = $panjang;

            while ($sisaBaca > 0) {
                $blok = fread($masuk, min(self::UKURAN_BLOK, $sisaBaca));

                if ($blok === false || $blok === '') {
                    throw new RuntimeException('Arsip cadangan terpotong.');
                }

                hash_update($hmac, $blok);
                $sisaBaca -= strlen($blok);
            }

            $tersimpan = fread($masuk, 32);

            if (strlen($tersimpan) !== 32 || ! hash_equals($tersimpan, hash_final($hmac, true))) {
                throw new RuntimeException('Kunci cadangan salah atau arsip telah diubah.');
            }

            rewind($masuk);
            fseek($masuk, 40);

            $keluar = fopen($tujuan, 'wb');

            if ($keluar === false) {
                throw new RuntimeException('Arsip hasil dekripsi tidak dapat ditulis.');
            }

            try {
                $sisa = '';
                $sisaBaca = $panjang;

                while ($sisaBaca > 0) {
                    $blok = fread($masuk, min(self::UKURAN_BLOK, $sisaBaca));

                    if ($blok === false || $blok === '') {
                        throw new RuntimeException('Arsip cadangan terpotong.');
                    }

                    $sisaBaca -= strlen($blok);
                    $sisa .= $blok;

                    while (strlen($sisa) > self::UKURAN_BLOK) {
                        $potong = substr($sisa, 0, self::UKURAN_BLOK);
                        $sisa = substr($sisa, self::UKURAN_BLOK);

                        $jelas = openssl_decrypt(
                            $potong,
                            'aes-256-cbc',
                            $enkripsi,
                            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
                            $iv
                        );

                        if ($jelas === false) {
                            throw new RuntimeException('Dekripsi cadangan gagal.');
                        }

                        fwrite($keluar, $jelas);
                    }
                }

                $jelas = openssl_decrypt($sisa, 'aes-256-cbc', $enkripsi, OPENSSL_RAW_DATA, $iv);

                if ($jelas === false) {
                    throw new RuntimeException('Dekripsi cadangan gagal.');
                }

                fwrite($keluar, $jelas);
            } finally {
                fclose($keluar);
            }
        } finally {
            fclose($masuk);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function turunanKunci(string $sandi, string $salt): array
    {
        $kunci = hash_pbkdf2('sha256', $sandi, $salt, 120000, 64, true);

        return [substr($kunci, 0, 32), substr($kunci, 32, 32)];
    }

    /**
     * @return array{database: string|null}
     */
    private function bukaZip(string $zipPath, string $kerja): array
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Arsip cadangan tidak dapat dibuka.');
        }

        $hasil = ['database' => null];
        $tujuanAman = realpath($kerja);

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nama = (string) $zip->getNameIndex($i);

                if ($nama === '' || str_starts_with($nama, '/') || str_contains($nama, '..') || str_contains($nama, "\0")) {
                    continue;
                }

                $bersih = str_replace('\\', '/', $nama);
                $tujuan = $kerja.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $bersih);
                $folderTujuan = dirname($tujuan);

                if ($tujuanAman === false || ! $this->diDalam($tujuan, $tujuanAman)) {
                    continue;
                }

                if (! is_dir($folderTujuan) && ! mkdir($folderTujuan, 0700, true) && ! is_dir($folderTujuan)) {
                    continue;
                }

                $aliran = $zip->getStream($nama);

                if ($aliran === false) {
                    continue;
                }

                $keluar = fopen($tujuan, 'wb');

                if ($keluar !== false) {
                    stream_copy_to_stream($aliran, $keluar);
                    fclose($keluar);
                }

                fclose($aliran);

                if ($bersih === 'database.sqlite' || $bersih === 'database.sql') {
                    $hasil['database'] = $tujuan;
                }
            }
        } finally {
            $zip->close();
        }

        return $hasil;
    }

    /**
     * Pastikan `$jalur` benar-benar berada di dalam `$akar`.
     *
     * Windows mencampur `\` dan `/` (hasil `storage_path()` vs `realpath()`),
     * jadi pembanding memakai bentuk normal yang sama: garis miring `/` dan
     * huruf kecil. Pemeriksaan ini mencegah entri ZIP menulis ke luar folder
     * kerja (zip slip), selain penyaringan `..` di atas.
     */
    private function diDalam(string $jalur, string $akar): bool
    {
        $normal = fn (string $teks): string => strtolower(str_replace('\\', '/', $teks));

        return str_starts_with($normal($jalur), rtrim($normal($akar), '/').'/');
    }

    private function pulihkanDatabase(string $dump): void
    {
        $driver = (string) config('database.default');
        $koneksi = (array) config('database.connections.'.$driver, []);

        if (($koneksi['driver'] ?? $driver) === 'sqlite') {
            $basisData = (string) ($koneksi['database'] ?? '');

            if ($basisData === '' || $basisData === ':memory:') {
                throw new RuntimeException('Pemulihan SQLite membutuhkan berkas basis data, bukan basis data di memori.');
            }

            DB::disconnect();
            DB::purge();

            if (! copy($dump, $basisData)) {
                throw new RuntimeException('Basis data SQLite tidak dapat ditimpa.');
            }

            return;
        }

        $kerja = dirname($dump);
        $opsi = $this->berkasOpsi($kerja, $koneksi);

        try {
            $perintah = [
                (string) config('spmb.cadangan.perintah_mysql', 'mysql'),
                '--defaults-extra-file='.$opsi,
                '--default-character-set=utf8mb4',
                (string) ($koneksi['database'] ?? ''),
            ];

            $proses = new Process($perintah);
            $proses->setTimeout(900);
            $masuk = fopen($dump, 'rb');

            if ($masuk === false) {
                throw new RuntimeException('Dump basis data tidak dapat dibaca.');
            }

            try {
                $proses->setInput($masuk);
                $proses->run();
            } finally {
                fclose($masuk);
            }

            if (! $proses->isSuccessful()) {
                throw new RuntimeException('mysql gagal memulihkan: '.trim($proses->getErrorOutput()));
            }
        } finally {
            @unlink($opsi);
        }
    }

    private function pulihkanBerkas(string $kerja): void
    {
        $sumber = $kerja.DIRECTORY_SEPARATOR.'berkas';

        if (! is_dir($sumber)) {
            return;
        }

        $tujuan = storage_path('app/private');

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sumber, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $berkas) {
            if (! $berkas instanceof \SplFileInfo || ! $berkas->isFile()) {
                continue;
            }

            $relatif = str_replace('\\', '/', substr($berkas->getPathname(), strlen($sumber) + 1));
            $target = $tujuan.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relatif);
            $folder = dirname($target);

            if (! is_dir($folder) && ! mkdir($folder, 0700, true) && ! is_dir($folder)) {
                throw new RuntimeException('Folder berkas hasil pemulihan tidak dapat dibuat.');
            }

            if (! copy($berkas->getPathname(), $target)) {
                throw new RuntimeException('Berkas hasil pemulihan tidak dapat disalin.');
            }
        }
    }

    private function hapusFolder(string $folder): void
    {
        if (! is_dir($folder)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $berkas) {
            if (! $berkas instanceof \SplFileInfo) {
                continue;
            }

            $berkas->isDir() ? @rmdir($berkas->getPathname()) : @unlink($berkas->getPathname());
        }

        @rmdir($folder);
    }
}
