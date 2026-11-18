<?php

namespace App\Services;

use App\Enums\StatusHasil;
use App\Enums\StatusMurid;
use App\Enums\StatusPembayaran;
use App\Models\DaftarUlang;
use App\Models\Gelombang;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\Sekolah;

/**
 * Rekap agregat. Tidak pernah mengembalikan nama anak, NIK, nomor peserta,
 * atau data pembayaran per orang.
 */
class RekapService
{
    public function __construct(
        private readonly KuotaService $kuota,
        private readonly PengumumanService $pengumuman,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function untukSekolah(int $sekolahId): array
    {
        $sekolah = Sekolah::query()->findOrFail($sekolahId);
        $gelombang = Gelombang::query()
            ->tanpaSekolah()
            ->where('sekolah_id', $sekolahId)
            ->orderBy('id')
            ->get();

        $baris = $gelombang
            ->map(fn (Gelombang $g) => $this->untukGelombang($g, $sekolahId))
            ->values()
            ->all();

        return [
            'sekolah' => $sekolah,
            'gelombang' => $baris,
            'ringkasan' => $this->gabung($baris),
        ];
    }

    /**
     * Rekap seluruh sekolah untuk panel yayasan.
     *
     * @return array<string, mixed>
     */
    public function untukYayasan(): array
    {
        $sekolah = Sekolah::query()->orderBy('id')->get();

        $baris = $sekolah
            ->map(fn (Sekolah $s) => $this->untukSekolah($s->id))
            ->values()
            ->all();

        return [
            'sekolah' => $baris,
            'ringkasan' => $this->gabung(array_map(fn (array $s) => $s['ringkasan'], $baris)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function untukGelombang(Gelombang $gelombang, int $sekolahId): array
    {
        $query = Pendaftaran::query()
            ->tanpaSekolah()
            ->where('gelombang_id', $gelombang->id)
            ->whereNotNull('dikirim_pada');

        $statusMentah = (clone $query)
            ->selectRaw('status_hasil, count(*) as jumlah')
            ->groupBy('status_hasil')
            ->pluck('jumlah', 'status_hasil');

        $status = [];

        foreach (StatusHasil::cases() as $kasus) {
            $status[$kasus->value] = (int) ($statusMentah[$kasus->value] ?? 0);
        }

        $programMentah = (clone $query)
            ->selectRaw('program, count(*) as jumlah')
            ->groupBy('program')
            ->pluck('jumlah', 'program');

        $program = [];

        foreach ($programMentah as $kunci => $jumlah) {
            $program[$kunci ?: 'Tanpa program'] = (int) $jumlah;
        }

        $pembayaran = [];

        foreach (StatusPembayaran::cases() as $kasus) {
            $pembayaran[$kasus->value] = (int) Pembayaran::query()
                ->tanpaSekolah()
                ->whereHas('pendaftaran', fn ($q) => $q
                    ->where('gelombang_id', $gelombang->id)
                    ->whereNotNull('dikirim_pada'))
                ->where('status', $kasus)
                ->count();
        }

        $daftarUlangSelesai = (int) DaftarUlang::query()
            ->whereHas('pendaftaran', fn ($q) => $q
                ->where('gelombang_id', $gelombang->id)
                ->whereNotNull('dikirim_pada'))
            ->where('selesai', true)
            ->count();

        $daftarUlangBelum = (int) DaftarUlang::query()
            ->whereHas('pendaftaran', fn ($q) => $q
                ->where('gelombang_id', $gelombang->id)
                ->whereNotNull('dikirim_pada'))
            ->where('selesai', false)
            ->count();

        return [
            'gelombang_id' => $gelombang->id,
            'nama' => $gelombang->nama,
            'status' => $status,
            'total' => array_sum($status),
            'lulusan_yayasan' => (int) (clone $query)->where('lulusan_yayasan', true)->count(),
            'bukan_lulusan_yayasan' => (int) (clone $query)->where('lulusan_yayasan', false)->count(),
            'program' => $program,
            'pembayaran' => $pembayaran,
            'daftar_ulang_selesai' => $daftarUlangSelesai,
            'daftar_ulang_belum' => $daftarUlangBelum,
            'mengundurkan_diri' => (int) (clone $query)->where('status_murid', StatusMurid::MengundurkanDiri)->count(),
            'kuota' => $gelombang->kuota,
            'terisi' => $this->kuota->terisi($gelombang),
            'diumumkan' => $this->pengumuman->sudahDiumumkanGelombang($sekolahId, $gelombang->id),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $baris
     * @return array<string, mixed>
     */
    private function gabung(array $baris): array
    {
        $status = [];

        foreach (StatusHasil::cases() as $kasus) {
            $status[$kasus->value] = 0;
        }

        $pembayaran = [];

        foreach (StatusPembayaran::cases() as $kasus) {
            $pembayaran[$kasus->value] = 0;
        }

        $program = [];
        $total = 0;
        $lulusan = 0;
        $bukanLulusan = 0;
        $selesai = 0;
        $belum = 0;
        $mundur = 0;
        $kuota = 0;
        $terisi = 0;

        foreach ($baris as $g) {
            $total += (int) $g['total'];
            $lulusan += (int) $g['lulusan_yayasan'];
            $bukanLulusan += (int) $g['bukan_lulusan_yayasan'];
            $selesai += (int) $g['daftar_ulang_selesai'];
            $belum += (int) $g['daftar_ulang_belum'];
            $mundur += (int) $g['mengundurkan_diri'];
            $terisi += (int) $g['terisi'];

            if ($g['kuota'] !== null) {
                $kuota += (int) $g['kuota'];
            }

            foreach ($status as $kunci => $_) {
                $status[$kunci] += (int) ($g['status'][$kunci] ?? 0);
            }

            foreach ($pembayaran as $kunci => $_) {
                $pembayaran[$kunci] += (int) ($g['pembayaran'][$kunci] ?? 0);
            }

            foreach ($g['program'] as $kunci => $jumlah) {
                $program[$kunci] = ($program[$kunci] ?? 0) + (int) $jumlah;
            }
        }

        return [
            'total' => $total,
            'status' => $status,
            'lulusan_yayasan' => $lulusan,
            'bukan_lulusan_yayasan' => $bukanLulusan,
            'program' => $program,
            'pembayaran' => $pembayaran,
            'daftar_ulang_selesai' => $selesai,
            'daftar_ulang_belum' => $belum,
            'mengundurkan_diri' => $mundur,
            'kuota' => $kuota,
            'terisi' => $terisi,
        ];
    }
}
