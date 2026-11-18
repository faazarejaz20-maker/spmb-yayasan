<?php

namespace App\Http\Requests;

use App\Enums\StatusHasil;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KeputusanRequest extends FormRequest
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
            'status' => ['required', Rule::enum(StatusHasil::class)],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'konfirmasi_berkas' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Pilih status tujuan.',
            'status.enum' => 'Status tujuan tidak dikenal.',
        ];
    }
}
