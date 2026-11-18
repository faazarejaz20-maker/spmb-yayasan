<?php

namespace App\Services;

use App\Enums\StatusHasil;
use App\Enums\StatusMurid;
use App\Enums\StatusPembayaran;
use App\Models\Pendaftaran;
use App\Models\Pengumuman;

class StatusTampilService
{
    /**
     * Sudah ada baris pengumuman untuk sekolah dan gelombang pendaftaran.
     */
    public function sudahDiumumkan(Pendaftaran $pendaftaran): bool
    {
        return app(PengumumanService::class)->sudahDiumumkan($pendaftaran);
    }

    /**
     * Kemajuan ringkas enam tahap untuk dasbor walisantri. Hanya membaca
     * kolom dan relasi yang sudah ada; tidak menambah atau mengubah data.
     *
     * @return array{langkah: array<int, array{kunci: string, label: string, selesai: bool, aktif: bool}>, aktif: int|null, tampil: bool}
     */
    public function kemajuan(Pendaftaran $pendaftaran, StatusTampil $status): array
    {
        $pendaftaran->loadMissing(['pembayaran', 'daftarUlang']);

        $terkirim = ! $pendaftaran->masihDraf();
        $resmi = $pendaftaran->status_murid === StatusMurid::Resmi;

        $diverifikasi = in_array($pendaftaran->status_hasil, [
            StatusHasil::Diverifikasi,
            StatusHasil::Diterima,
            StatusHasil::Ditolak,
            StatusHasil::Cadangan,
        ], true);

        $dibayar = $resmi || $pendaftaran->pembayaran->contains(
            fn ($pembayaran) => $pembayaran->status === StatusPembayaran::Disetujui
        );

        $daftarUlang = $resmi || (bool) $pendaftaran->daftarUlang?->selesai;

        $langkah = [
            ['kunci' => 'formulir', 'label' => 'Formulir', 'selesai' => $terkirim],
            ['kunci' => 'berkas', 'label' => 'Berkas', 'selesai' => $terkirim],
            ['kunci' => 'verifikasi', 'label' => 'Verifikasi', 'selesai' => $diverifikasi],
            ['kunci' => 'pembayaran', 'label' => 'Pembayaran', 'selesai' => $dibayar],
            ['kunci' => 'daftar-ulang', 'label' => 'Daftar ulang', 'selesai' => $daftarUlang],
            ['kunci' => 'hasil', 'label' => 'Hasil', 'selesai' => $status->sudahDiumumkan],
        ];

        return $this->selesaikanLangkah($langkah, $status, $pendaftaran);
    }

    /**
     * Garis waktu lima tahap untuk halaman detail pendaftaran.
     *
     * @return array{langkah: array<int, array{kunci: string, label: string, selesai: bool, aktif: bool}>, aktif: int|null, tampil: bool}
     */
    public function garisWaktu(Pendaftaran $pendaftaran, StatusTampil $status): array
    {
        $pendaftaran->loadMissing(['pembayaran', 'daftarUlang']);

        $resmi = $pendaftaran->status_murid === StatusMurid::Resmi;

        $diverifikasi = in_array($pendaftaran->status_hasil, [
            StatusHasil::Diverifikasi,
            StatusHasil::Diterima,
            StatusHasil::Ditolak,
            StatusHasil::Cadangan,
        ], true);

        $langkah = [
            ['kunci' => 'formulir', 'label' => 'Formulir terkirim', 'selesai' => ! $pendaftaran->masihDraf()],
            ['kunci' => 'berkas', 'label' => 'Berkas diverifikasi', 'selesai' => $diverifikasi],
            ['kunci' => 'pengumuman', 'label' => 'Pengumuman hasil', 'selesai' => $status->sudahDiumumkan],
            ['kunci' => 'daftar-ulang', 'label' => 'Daftar ulang di sekolah', 'selesai' => $resmi || (bool) $pendaftaran->daftarUlang?->selesai],
            ['kunci' => 'resmi', 'label' => 'Resmi sebagai murid', 'selesai' => $resmi],
        ];

        return $this->selesaikanLangkah($langkah, $status, $pendaftaran);
    }

    /**
     * @param  array<int, array{kunci: string, label: string, selesai: bool}>  $langkah
     * @return array{langkah: array<int, array{kunci: string, label: string, selesai: bool, aktif: bool}>, aktif: int|null, tampil: bool}
     */
    private function selesaikanLangkah(array $langkah, StatusTampil $status, Pendaftaran $pendaftaran): array
    {
        $aktif = null;

        foreach ($langkah as $nomor => $baris) {
            if (! $baris['selesai']) {
                $aktif = $nomor;
                break;
            }
        }

        foreach ($langkah as $nomor => &$baris) {
            $baris['aktif'] = $nomor === $aktif;
        }
        unset($baris);

        return [
            'langkah' => $langkah,
            'aktif' => $aktif,
            'tampil' => $status->hasil !== StatusHasil::Ditolak
                && $pendaftaran->status_murid !== StatusMurid::MengundurkanDiri,
        ];
    }

    /**
     * Terjemahkan pendaftaran menjadi tampilan yang aman untuk walisantri.
     *
     * Penting: hasil Diterima, Ditolak, dan Cadangan tidak pernah dikembalikan
     * sebelum pengumuman ada.
     */
    public function untuk(Pendaftaran $pendaftaran): StatusTampil
    {
        $pendaftaran->loadMissing(['daftarUlang']);

        $diumumkan = $this->sudahDiumumkan($pendaftaran);
        $status = $pendaftaran->status_hasil;
        $murid = $pendaftaran->status_murid;

        if ($pendaftaran->masihDraf()) {
            return new StatusTampil(
                label: 'Draf, belum dikirim',
                warna: 'netral',
                langkahBerikutnya: 'Lengkapi formulir lalu kirim pendaftaran.',
                sudahDiumumkan: false,
                hasil: null,
                tahap: 0,
                draf: true,
            );
        }

        // Status murid hanya bermakna setelah hasil diumumkan.
        if ($diumumkan && $murid === StatusMurid::Resmi) {
            return new StatusTampil(
                label: 'Murid resmi',
                warna: 'hijau',
                langkahBerikutnya: 'Ikuti informasi dari grup WhatsApp sekolah.',
                sudahDiumumkan: true,
                hasil: $status,
                tahap: 6,
                tampilkanGrupWa: true,
            );
        }

        if ($diumumkan && $murid === StatusMurid::MengundurkanDiri) {
            return new StatusTampil(
                label: 'Mengundurkan diri',
                warna: 'netral',
                langkahBerikutnya: 'Hubungi sekolah bila membutuhkan keterangan.',
                sudahDiumumkan: true,
                hasil: $status,
                tahap: 6,
            );
        }

        if ($status === StatusHasil::PerluPerbaikan) {
            return new StatusTampil(
                label: 'Perlu perbaikan',
                warna: 'oranye',
                langkahBerikutnya: 'Perbaiki data atau berkas yang ditandai, lalu kirim perbaikan.',
                sudahDiumumkan: $diumumkan,
                hasil: null,
                tahap: 2,
                perluPerbaikan: true,
            );
        }

        if ($status === StatusHasil::Baru) {
            return new StatusTampil(
                label: 'Menunggu verifikasi',
                warna: 'biru',
                langkahBerikutnya: 'Pantau status berkas dan pengumuman dari sekolah.',
                sudahDiumumkan: $diumumkan,
                hasil: null,
                tahap: 1,
            );
        }

        if ($status === StatusHasil::Diverifikasi) {
            return new StatusTampil(
                label: $diumumkan ? 'Terverifikasi, menunggu keputusan' : 'Terverifikasi, menunggu pengumuman',
                warna: 'biru',
                langkahBerikutnya: 'Pantau pengumuman hasil dari sekolah.',
                sudahDiumumkan: $diumumkan,
                hasil: null,
                tahap: 2,
            );
        }

        // Diterima, Ditolak, Cadangan: jangan bocorkan sebelum pengumuman.
        if (! $diumumkan) {
            return new StatusTampil(
                label: 'Terverifikasi, menunggu pengumuman',
                warna: 'biru',
                langkahBerikutnya: 'Pantau pengumuman hasil dari sekolah.',
                sudahDiumumkan: false,
                hasil: null,
                tahap: 2,
            );
        }

        return match ($status) {
            StatusHasil::Diterima => new StatusTampil(
                label: 'Diterima',
                warna: 'hijau',
                langkahBerikutnya: 'Lakukan daftar ulang di sekolah.',
                sudahDiumumkan: true,
                hasil: StatusHasil::Diterima,
                tahap: 4,
                tampilkanGrupWa: true,
            ),
            StatusHasil::Ditolak => new StatusTampil(
                label: 'Belum diterima',
                warna: 'netral',
                langkahBerikutnya: 'Hubungi sekolah bila membutuhkan penjelasan.',
                sudahDiumumkan: true,
                hasil: StatusHasil::Ditolak,
                tahap: 4,
            ),
            StatusHasil::Cadangan => new StatusTampil(
                label: 'Daftar cadangan',
                warna: 'kuning',
                langkahBerikutnya: 'Tunggu informasi lanjutan dari sekolah.',
                sudahDiumumkan: true,
                hasil: StatusHasil::Cadangan,
                tahap: 4,
            ),
            default => new StatusTampil(
                label: 'Menunggu verifikasi',
                warna: 'biru',
                langkahBerikutnya: 'Pantau status dari sekolah.',
                sudahDiumumkan: $diumumkan,
                hasil: null,
                tahap: 1,
            ),
        };
    }
}
