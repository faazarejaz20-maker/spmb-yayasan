<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JenisBerkasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:150'],
            'wajib' => ['sometimes', 'boolean'],
            'jumlah_lembar' => ['required', 'integer', 'min:1', 'max:10'],
            'petunjuk' => ['nullable', 'string', 'max:1000'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
            'aktif' => ['sometimes', Rule::in(['0', '1', 0, 1, true, false, 'on'])],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama jenis berkas wajib diisi.',
            'jumlah_lembar.required' => 'Jumlah lembar wajib diisi.',
            'jumlah_lembar.integer' => 'Jumlah lembar harus bilangan bulat.',
            'jumlah_lembar.min' => 'Jumlah lembar minimal 1.',
            'jumlah_lembar.max' => 'Jumlah lembar maksimal 10.',
            'petunjuk.max' => 'Petunjuk maksimal 1000 karakter.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'wajib' => $this->boolean('wajib'),
            'aktif' => $this->boolean('aktif'),
        ]);
    }
}
