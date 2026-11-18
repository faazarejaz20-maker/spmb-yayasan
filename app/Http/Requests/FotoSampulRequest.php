<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Unggah foto sampul kartu sekolah di beranda publik.
 *
 * Pemeriksaan isi berkas (MIME sebenarnya, gambar rusak, penulisan ulang)
 * dilakukan `BerkasService::prosesGambarKonten()`. Di sini hanya batas ukuran
 * dan ekstensi yang diterima agar pengguna mendapat pesan yang jelas.
 */
class FotoSampulRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'foto_sampul' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:'.(int) config('spmb.sampul.maks_kb'),
            ],
        ];
    }

    public function messages(): array
    {
        $maksMb = number_format((int) config('spmb.sampul.maks_kb') / 1024, 0, ',', '.');

        return [
            'foto_sampul.required' => 'Pilih foto yang ingin diunggah.',
            'foto_sampul.file' => 'Foto gagal diunggah. Silakan coba lagi.',
            'foto_sampul.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'foto_sampul.max' => "Ukuran foto maksimal {$maksMb} MB.",
            'foto_sampul.uploaded' => 'Foto gagal diunggah. Ukuran berkas mungkin melebihi batas server.',
        ];
    }
}
