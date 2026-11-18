<?php

namespace App\Services;

use App\Models\Pendaftaran;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class NomorPesertaService
{
    /**
     * Buat nomor peserta `AWALAN-TAHUN-URUTAN4` di dalam kunci cache agar dua
     * pengiriman bersamaan tidak mendapat nomor yang sama.
     */
    public function buat(Pendaftaran $pendaftaran): string
    {
        $pendaftaran->loadMissing(['sekolah', 'gelombang.tahunAjaran']);

        $awalan = $this->awalan($pendaftaran);
        $tahun = $this->tahun($pendaftaran);
        $kunci = "nomor-peserta:{$awalan}-{$tahun}";

        return Cache::lock($kunci, 10)->block(5, function () use ($awalan, $tahun) {
            return $this->nomorBerikutnya($awalan, $tahun);
        });
    }

    private function awalan(Pendaftaran $pendaftaran): string
    {
        $kode = $pendaftaran->sekolah->kode;
        $programTki = config('spmb.nomor_peserta.program_tki');
        $kodeProgram = config('spmb.nomor_peserta.kode_program');

        if ($kode === $kodeProgram) {
            $program = $pendaftaran->program;

            if (! in_array($program, $programTki, true)) {
                throw new RuntimeException('Program KB, TK, atau TPA wajib dipilih.');
            }

            return $program;
        }

        if (! in_array($kode, config('spmb.nomor_peserta.awalan'), true)) {
            throw new RuntimeException('Kode sekolah tidak dikenal untuk nomor peserta.');
        }

        return $kode;
    }

    private function tahun(Pendaftaran $pendaftaran): string
    {
        $nama = (string) $pendaftaran->gelombang->tahunAjaran->nama;
        $tahun = substr($nama, 0, 4);

        if (! ctype_digit($tahun)) {
            throw new RuntimeException('Nama tahun ajaran harus diawali empat angka.');
        }

        return $tahun;
    }

    private function nomorBerikutnya(string $awalan, string $tahun): string
    {
        $terakhir = Pendaftaran::query()
            ->withoutGlobalScope('milik_sekolah')
            ->where('nomor_peserta', 'like', $awalan.'-'.$tahun.'-%')
            ->orderByDesc('nomor_peserta')
            ->value('nomor_peserta');

        $urutan = 1;

        if (is_string($terakhir) && strlen($terakhir) >= 4) {
            $urutan = ((int) substr($terakhir, -4)) + 1;
        }

        return sprintf('%s-%s-%04d', $awalan, $tahun, $urutan);
    }
}
