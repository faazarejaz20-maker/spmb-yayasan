<?php

namespace App\Services;

use App\Enums\MetodePembayaran;
use App\Enums\PeranPengguna;
use App\Enums\StatusHasil;
use App\Enums\StatusPembayaran;
use App\Exceptions\AturanBisnisException;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\RincianBiaya;
use App\Models\User;
use App\Support\Catat;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PembayaranService
{
    public function __construct(private readonly BerkasService $berkasService) {}

    public function unggahBukti(
        User $pelaku,
        Pendaftaran $pendaftaran,
        int $nominal,
        string $dibayarPada,
        UploadedFile $bukti,
    ): Pembayaran {
        if (! $this->bolehBayar($pelaku, $pendaftaran)) {
            throw new AuthorizationException('Pembayaran belum tersedia untuk pendaftaran ini.');
        }

        $hasil = $this->berkasService->proses(
            $bukti,
            config('spmb.berkas.folder_pembayaran').'/'.$pendaftaran->uuid,
        );

        $pembayaran = Pembayaran::create([
            'pendaftaran_id' => $pendaftaran->id,
            'metode' => MetodePembayaran::Transfer,
            'nominal' => $nominal,
            'bukti_path' => $hasil['path'],
            'status' => StatusPembayaran::Menunggu,
            'dibayar_pada' => $dibayarPada,
        ]);

        Catat::aksi('unggah_bukti_pembayaran', $pembayaran, [
            'nominal' => $nominal,
        ], $pendaftaran->sekolah_id);

        return $pembayaran;
    }

    /**
     * Setujui atau tolak bukti transfer.
     */
    public function setujui(User $pelaku, Pembayaran $pembayaran, ?string $catatan = null): Pembayaran
    {
        return $this->proses($pelaku, $pembayaran, StatusPembayaran::Disetujui, null, $catatan);
    }

    public function tolak(User $pelaku, Pembayaran $pembayaran, string $alasan): Pembayaran
    {
        $alasan = trim($alasan);

        if ($alasan === '') {
            throw new AturanBisnisException('Tuliskan alasan penolakan agar walisantri tahu apa yang harus diperbaiki.');
        }

        $pembayaran->loadMissing('pendaftaran');

        return $this->proses(
            $pelaku,
            $pembayaran,
            StatusPembayaran::Ditolak,
            $alasan,
            $pembayaran->status === StatusPembayaran::Menunggu ? null : $alasan,
        );
    }

    /**
     * Catat pembayaran tunai. Langsung disetujui.
     */
    public function catatTunai(
        User $pelaku,
        Pendaftaran $pendaftaran,
        int $nominal,
        string $dibayarPada,
        ?int $rincianBiayaId = null,
        ?string $catatan = null,
    ): Pembayaran {
        $this->pastikanStafSekolah($pelaku, $pendaftaran);

        if ($nominal <= 0) {
            throw new AturanBisnisException('Nominal pembayaran harus lebih dari nol.');
        }

        if ($rincianBiayaId !== null) {
            $rincian = RincianBiaya::query()
                ->tanpaSekolah()
                ->whereKey($rincianBiayaId)
                ->where('sekolah_id', $pendaftaran->sekolah_id)
                ->first();

            if (! $rincian) {
                throw new AturanBisnisException('Komponen biaya tidak sesuai dengan sekolah pendaftaran.');
            }
        }

        return DB::transaction(function () use ($pelaku, $pendaftaran, $nominal, $dibayarPada, $rincianBiayaId) {
            $pembayaran = Pembayaran::create([
                'pendaftaran_id' => $pendaftaran->id,
                'rincian_biaya_id' => $rincianBiayaId,
                'metode' => MetodePembayaran::Tunai,
                'nominal' => $nominal,
                'status' => StatusPembayaran::Disetujui,
                'diproses_oleh' => $pelaku->id,
                'dibayar_pada' => $dibayarPada,
            ]);

            Catat::aksi('catat_pembayaran_tunai', $pembayaran, [
                'nominal' => $nominal,
            ], $pendaftaran->sekolah_id);

            return $pembayaran;
        });
    }

    /**
     * Antrean bukti transfer yang menunggu untuk satu sekolah.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Pembayaran>
     */
    public function antrean(int $sekolahId)
    {
        return Pembayaran::query()
            ->whereHas('pendaftaran', fn ($query) => $query->where('sekolah_id', $sekolahId)->whereNotNull('dikirim_pada'))
            ->where('metode', MetodePembayaran::Transfer)
            ->where('status', StatusPembayaran::Menunggu)
            ->with(['pendaftaran.anak', 'pendaftaran.sekolah'])
            ->orderBy('dibayar_pada')
            ->orderBy('id')
            ->get();
    }

    public function sajikanBukti(Pembayaran $pembayaran): StreamedResponse
    {
        return $this->berkasService->sajikanBukti(
            (string) $pembayaran->bukti_path,
            'bukti-'.$pembayaran->uuid,
        );
    }

    public function bolehBayar(User $pelaku, Pendaftaran $pendaftaran): bool
    {
        $milik = $pendaftaran->anak()->where('user_id', $pelaku->id)->exists();

        return $milik
            && $pendaftaran->status_hasil === StatusHasil::Diterima
            && app(StatusTampilService::class)->sudahDiumumkan($pendaftaran);
    }

    private function proses(
        User $pelaku,
        Pembayaran $pembayaran,
        StatusPembayaran $tujuan,
        ?string $catatanPenolakan,
        ?string $catatanAdmin,
    ): Pembayaran {
        $pembayaran->loadMissing('pendaftaran');

        /** @var Pendaftaran $pendaftaran */
        $pendaftaran = $pembayaran->pendaftaran;

        $this->pastikanStafSekolah($pelaku, $pendaftaran);

        if ($pembayaran->status !== StatusPembayaran::Menunggu) {
            if ($pelaku->peran !== PeranPengguna::Admin) {
                throw new AturanBisnisException('Pembayaran ini sudah diproses dan tidak dapat diproses ulang.');
            }

            if (trim((string) $catatanAdmin) === '') {
                throw new AturanBisnisException('Pembayaran sudah diproses. Admin wajib menuliskan alasan perubahan.');
            }
        }

        return DB::transaction(function () use ($pelaku, $pembayaran, $tujuan, $catatanPenolakan, $catatanAdmin) {
            $pembayaran->fill([
                'status' => $tujuan,
                'catatan_penolakan' => $catatanPenolakan,
                'diproses_oleh' => $pelaku->id,
            ])->save();

            Catat::aksi(
                $tujuan === StatusPembayaran::Disetujui ? 'setujui_pembayaran' : 'tolak_pembayaran',
                $pembayaran,
                array_filter([
                    'status' => $tujuan->value,
                    'catatan' => $catatanAdmin,
                ], fn ($nilai) => $nilai !== null && $nilai !== ''),
                $pembayaran->pendaftaran->sekolah_id,
            );

            return $pembayaran->refresh();
        });
    }

    private function pastikanStafSekolah(User $pelaku, Pendaftaran $pendaftaran): void
    {
        if (! in_array($pelaku->peran, [PeranPengguna::Panitia, PeranPengguna::Admin], true)
            || ! $pelaku->sekolah_id
            || (int) $pelaku->sekolah_id !== (int) $pendaftaran->sekolah_id) {
            throw (new ModelNotFoundException)->setModel(Pembayaran::class);
        }
    }
}
