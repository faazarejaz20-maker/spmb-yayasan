<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DaftarUlangRequest extends FormRequest
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
            'hadir' => ['nullable', 'boolean'],
            'kekurangan' => ['nullable', 'string', 'max:2000'],
            'catatan_wawancara' => ['nullable', 'string', 'max:5000'],
            'selesai' => ['nullable', 'boolean'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
