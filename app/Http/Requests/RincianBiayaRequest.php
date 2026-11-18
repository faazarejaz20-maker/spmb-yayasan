<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RincianBiayaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kelompok' => ['nullable', 'string', 'max:50'],
            'nama' => ['required', 'string', 'max:150'],
            'nominal' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'tipe' => ['required', 'string', Rule::in(['tetap', 'pilihan'])],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama biaya wajib diisi.',
            'nominal.required' => 'Nominal wajib diisi.',
            'nominal.integer' => 'Nominal harus bilangan bulat rupiah tanpa titik atau koma.',
            'nominal.min' => 'Nominal tidak boleh negatif.',
            'tipe.in' => 'Tipe biaya harus tetap atau pilihan.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('nominal')) {
            $nilai = trim((string) $this->input('nominal'));

            // Hanya buang pemisah ribuan. Nilai yang memuat tanda minus atau
            // huruf dibiarkan agar aturan validasi yang menolaknya.
            if ($nilai !== '' && preg_match('/\A[0-9.\s]+\z/', $nilai)) {
                $nilai = (string) preg_replace('/[^0-9]/', '', $nilai);
            }

            $this->merge(['nominal' => $nilai]);
        }
    }
}
