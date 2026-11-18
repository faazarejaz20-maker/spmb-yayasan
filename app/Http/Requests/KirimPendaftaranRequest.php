<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KirimPendaftaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pernyataan_data' => ['accepted'],
            'pernyataan_privasi' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'pernyataan_data.accepted' => 'Centang pernyataan bahwa data yang diisi benar.',
            'pernyataan_privasi.accepted' => 'Centang persetujuan penggunaan data sesuai Kebijakan Privasi.',
        ];
    }
}
