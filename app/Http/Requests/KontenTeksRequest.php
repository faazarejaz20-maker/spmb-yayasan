<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KontenTeksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bagian' => ['required', 'string', 'max:50'],
            'isi' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'bagian.required' => 'Bagian konten tidak dikenal.',
            'isi.max' => 'Teks terlalu panjang (maksimal 10.000 karakter).',
        ];
    }
}
