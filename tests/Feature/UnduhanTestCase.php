<?php

namespace Tests\Feature;

use App\Enums\StatusBerkas;
use App\Models\Berkas;
use App\Models\JenisBerkas;
use App\Models\Pendaftaran;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Dasar bersama tes Tahap 5 (ekspor, ZIP, cetak): membaca isi respons unduhan
 * tanpa meninggalkan file di disk, dan melampirkan berkas nyata ke disk privat.
 */
abstract class UnduhanTestCase extends PanitiaTestCase
{
    /**
     * Ambil isi respons unduhan. Untuk `BinaryFileResponse`, Laravel mengirim
     * isi lalu menghapus file sementara (deleteFileAfterSend).
     */
    protected function isiUnduhan(TestResponse $respons): string
    {
        return (string) $respons->streamedContent();
    }

    /**
     * Baca semua lembar XLSX dari isi mentah; kembalikan `[nama lembar => baris]`.
     *
     * @return array<string, array<int, array<int, mixed>>>
     */
    protected function bacaXlsx(string $isi): array
    {
        $path = tempnam(sys_get_temp_dir(), 'uji-xlsx-');
        file_put_contents($path, $isi);

        $lembar = [];
        $pembaca = new XlsxReader;

        try {
            $pembaca->open($path);

            foreach ($pembaca->getSheetIterator() as $sheet) {
                $baris = [];

                foreach ($sheet->getRowIterator() as $row) {
                    $baris[] = $row->toArray();
                }

                $lembar[$sheet->getName()] = $baris;
            }
        } finally {
            $pembaca->close();
            @unlink($path);
        }

        return $lembar;
    }

    /**
     * Baca seluruh baris CSV (BOM sudah dibuang pembaca).
     *
     * @return array<int, array<int, mixed>>
     */
    protected function bacaCsv(string $isi): array
    {
        $path = tempnam(sys_get_temp_dir(), 'uji-csv-');
        file_put_contents($path, $isi);

        $baris = [];
        $pembaca = new CsvReader;

        try {
            $pembaca->open($path);

            foreach ($pembaca->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $baris[] = $row->toArray();
                }
            }
        } finally {
            $pembaca->close();
            @unlink($path);
        }

        return $baris;
    }

    /**
     * Lampirkan berkas dengan isi nyata di disk privat `local`.
     */
    protected function berkasNyata(
        Pendaftaran $pendaftaran,
        string $ext = 'pdf',
        ?JenisBerkas $jenis = null,
        ?string $isi = null,
    ): Berkas {
        $jenis ??= JenisBerkas::factory()->create([
            'sekolah_id' => $pendaftaran->sekolah_id,
            'nama' => 'Kartu Keluarga',
            'wajib' => true,
        ]);

        $path = 'berkas/'.$pendaftaran->uuid.'/'.Str::uuid().'.'.$ext;

        $isi ??= $ext === 'pdf' ? '%PDF-1.4 contoh' : 'isi-gambar';

        Storage::disk('local')->put($path, $isi);

        return Berkas::create([
            'pendaftaran_id' => $pendaftaran->id,
            'jenis_berkas_id' => $jenis->id,
            'path' => $path,
            'status' => StatusBerkas::Menunggu,
        ]);
    }

    /**
     * Isi folder sementara ekspor/ZIP.
     *
     * @return array<int, string>
     */
    protected function fileTmp(): array
    {
        $disk = Storage::disk('local');
        $folder = (string) config('ekspor.tmp_folder');

        return $disk->exists($folder) ? $disk->files($folder) : [];
    }
}
