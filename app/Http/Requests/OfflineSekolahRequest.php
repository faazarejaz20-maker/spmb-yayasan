<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OfflineSekolahRequest extends FormRequest
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
            'sekolah_id' => ['nullable', 'integer'],
            'gelombang_id' => ['required', 'integer'],
            'program' => ['nullable', 'string', Rule::in((array) config('spmb.nomor_peserta.program_tki'))],
            'anak_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'sekolah_id.required' => 'Pilih sekolah.',
            'gelombang_id.required' => 'Pilih gelombang.',
            'program.in' => 'Program harus KB, TK, atau TPA.',
        ];
    }
}
