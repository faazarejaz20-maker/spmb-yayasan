<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BiayaPendaftaranRequest extends FormRequest
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
            'setuju_biaya' => ['nullable', 'boolean'],
            'pilihan_iap' => ['nullable', 'numeric'],
        ];
    }
}
