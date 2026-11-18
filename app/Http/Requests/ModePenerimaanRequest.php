<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModePenerimaanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode_penerimaan' => ['required', 'string', Rule::in(['seleksi', 'tanpa_seleksi'])],
        ];
    }

    public function messages(): array
    {
        return [
            'mode_penerimaan.required' => 'Pilih mode penerimaan.',
            'mode_penerimaan.in' => 'Mode penerimaan tidak dikenal.',
        ];
    }
}
