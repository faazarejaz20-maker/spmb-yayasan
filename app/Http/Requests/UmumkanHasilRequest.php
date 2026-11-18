<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UmumkanHasilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'konfirmasi' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'konfirmasi.accepted' => 'Centang pernyataan konfirmasi sebelum mengumumkan hasil.',
        ];
    }
}
