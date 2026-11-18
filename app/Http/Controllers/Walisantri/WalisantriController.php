<?php

namespace App\Http\Controllers\Walisantri;

use App\Enums\StatusHasil;
use App\Http\Controllers\Controller;
use App\Models\Pendaftaran;
use App\Models\Sekolah;
use App\Services\StatusTampil;
use App\Services\StatusTampilService;
use App\Support\GambarPublik;
use App\Support\NomorHp;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalisantriController extends Controller
{
    public function beranda(Request $request, StatusTampilService $statusTampil, GambarPublik $gambar): View
    {
        $pengguna = $request->user();

        $daftar = Pendaftaran::query()
            ->whereHas('anak', fn ($query) => $query->where('user_id', $pengguna->id))
            ->with(['anak', 'sekolah', 'gelombang', 'pembayaran', 'daftarUlang'])
            ->orderByDesc('created_at')
            ->get();

        $item = $daftar->map(function (Pendaftaran $pendaftaran) use ($statusTampil) {
            $status = $statusTampil->untuk($pendaftaran);

            return [
                'uuid' => $pendaftaran->uuid,
                'nama_anak' => $pendaftaran->anak->nama_lengkap,
                'sekolah' => $pendaftaran->sekolah->nama,
                'kode_sekolah' => $pendaftaran->sekolah->kode,
                'program' => $pendaftaran->program,
                'nomor_peserta' => $pendaftaran->nomor_peserta,
                'status' => $status,
                'boleh_diubah' => $pendaftaran->bolehDiubah(),
                'kemajuan' => $statusTampil->kemajuan($pendaftaran, $status),
                'aksi_utama' => $this->aksiUtama($pendaftaran, $status),
                'perlu_tindakan' => $status->draf || $status->perluPerbaikan,
            ];
        });

        $ringkasan = [
            'anak' => $daftar->pluck('anak_id')->unique()->count(),
            'pendaftaran' => $daftar->count(),
            'tindakan' => $item->where('perlu_tindakan', true)->count(),
        ];

        $sekolahKontak = Sekolah::query()
            ->where('aktif', true)
            ->orderBy('id')
            ->get()
            ->map(fn (Sekolah $sekolah) => [
                'kode' => $sekolah->kode,
                'nama' => $sekolah->nama,
                'jenjang' => $sekolah->jenjang,
                'alamat' => $sekolah->alamat,
                'kontak_tampil' => NomorHp::tampil($sekolah->kontak),
                'tautan_wa' => $sekolah->kontak
                    ? 'https://wa.me/'.preg_replace('/\D+/', '', $sekolah->kontak)
                    : null,
                'instagram' => $sekolah->instagram,
                'tautan_instagram' => $sekolah->instagram
                    ? 'https://instagram.com/'.$sekolah->instagram
                    : null,
                'prestasi' => $gambar->prestasiSekolah($sekolah->kode),
            ]);

        return view('walisantri.beranda', [
            'item' => $item,
            'ringkasan' => $ringkasan,
            'sekolahKontak' => $sekolahKontak,
        ]);
    }

    /**
     * Satu tindakan utama per kartu anak, agar walisantri tidak bingung
     * memilih langkah berikutnya.
     *
     * @return array{label: string, url: string}
     */
    private function aksiUtama(Pendaftaran $pendaftaran, StatusTampil $status): array
    {
        if ($status->draf || $status->perluPerbaikan) {
            return [
                'label' => $status->draf ? 'Lanjutkan formulir' : 'Perbaiki sekarang',
                'url' => route('walisantri.pendaftaran.langkah', [
                    'pendaftaran' => $pendaftaran->uuid,
                    'n' => 2,
                ]),
            ];
        }

        if ($status->sudahDiumumkan && $status->hasil === StatusHasil::Diterima) {
            return [
                'label' => 'Pembayaran daftar ulang',
                'url' => route('walisantri.pendaftaran.pembayaran.index', $pendaftaran->uuid),
            ];
        }

        return [
            'label' => 'Lihat detail',
            'url' => route('walisantri.pendaftaran.detail', $pendaftaran->uuid),
        ];
    }
}
