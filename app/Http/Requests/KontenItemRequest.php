<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KontenItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bagian' => ['required', 'string', 'max:50'],
            'judul' => ['nullable', 'string', 'max:150'],
            'isi' => ['nullable', 'string', 'max:10000'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'hapus_gambar' => ['sometimes', 'boolean'],
            'gambar' => ['nullable', 'file', 'max:'.(int) config('spmb.konten.maks_kb')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['hapus_gambar' => $this->boolean('hapus_gambar')]);
    }

    public function messages(): array
    {
        $maksMb = number_format((int) config('spmb.konten.maks_kb') / 1024, 0, ',', '.');

        return [
            'bagian.required' => 'Bagian konten tidak dikenal.',
            'judul.max' => 'Judul maksimal 150 karakter.',
            'isi.max' => 'Teks maksimal 10.000 karakter.',
            'gambar.max' => "Ukuran gambar maksimal {$maksMb} MB.",
            'gambar.file' => 'Gambar gagal diunggah.',
        ];
    }
}
