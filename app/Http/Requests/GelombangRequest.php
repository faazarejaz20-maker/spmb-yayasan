<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GelombangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:100'],
            'tanggal_buka' => ['nullable', 'date', 'required_if:status,dibuka'],
            'tanggal_tutup' => ['nullable', 'date', 'after_or_equal:tanggal_buka', 'required_if:status,dibuka'],
            'kuota' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'tautan_grup_wa' => [
                'nullable',
                'string',
                'max:255',
                'regex:/\Ahttps:\/\/chat\.whatsapp\.com\/[A-Za-z0-9_\-]+\z/',
            ],
            'status' => ['required', 'string', Rule::in(['draf', 'dibuka', 'ditutup'])],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama gelombang wajib diisi.',
            'tanggal_buka.required_if' => 'Gelombang yang dibuka wajib punya tanggal buka.',
            'tanggal_tutup.required_if' => 'Gelombang yang dibuka wajib punya tanggal tutup.',
            'tanggal_tutup.after_or_equal' => 'Tanggal tutup tidak boleh sebelum tanggal buka.',
            'kuota.integer' => 'Kuota harus bilangan bulat.',
            'kuota.min' => 'Kuota tidak boleh negatif.',
            'tautan_grup_wa.regex' => 'Tautan grup harus diawali https://chat.whatsapp.com/ dan hanya berisi kode undangan.',
            'status.in' => 'Status gelombang tidak dikenal.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('tautan_grup_wa')) {
            $tautan = trim((string) $this->input('tautan_grup_wa'));

            $this->merge(['tautan_grup_wa' => $tautan === '' ? null : $tautan]);
        }

        if ($this->has('kuota') && trim((string) $this->input('kuota')) === '') {
            $this->merge(['kuota' => null]);
        }
    }
}
