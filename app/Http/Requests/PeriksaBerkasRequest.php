<?php

namespace App\Http\Requests;

use App\Enums\StatusBerkas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PeriksaBerkasRequest extends FormRequest
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
            'status' => ['required', Rule::in([StatusBerkas::Sesuai->value, StatusBerkas::PerluPerbaikan->value])],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Pilih status pemeriksaan berkas.',
            'status.in' => 'Status berkas tidak dikenal.',
        ];
    }
}
