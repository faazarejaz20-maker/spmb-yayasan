<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RekeningSekolahRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank' => ['required', 'string', 'max:100'],
            'nomor_rekening' => ['required', 'string', 'regex:/\A[0-9][0-9\s\-]{4,29}\z/'],
            'atas_nama' => ['required', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'bank.required' => 'Nama bank wajib diisi.',
            'nomor_rekening.required' => 'Nomor rekening wajib diisi.',
            'nomor_rekening.regex' => 'Nomor rekening hanya boleh berisi angka, spasi, atau tanda hubung (5–30 karakter).',
            'atas_nama.required' => 'Nama pemilik rekening wajib diisi.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('nomor_rekening')) {
            $this->merge([
                'nomor_rekening' => trim((string) $this->input('nomor_rekening')),
            ]);
        }

        $this->merge(['aktif' => $this->boolean('aktif')]);
    }
}
