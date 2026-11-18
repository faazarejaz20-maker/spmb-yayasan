<?php

namespace App\Http\Requests;

use App\Support\NomorHp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SekolahRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('kontak')) {
            try {
                $this->merge(['kontak' => NomorHp::normalisasi($this->input('kontak'))]);
            } catch (\InvalidArgumentException) {
                // Biarkan nilai asli supaya validasi menolaknya.
            }
        }

        if ($this->has('instagram')) {
            $instagram = ltrim(trim((string) $this->input('instagram')), '@');
            $this->merge(['instagram' => $instagram === '' ? null : $instagram]);
        }

        $this->merge(['aktif' => $this->boolean('aktif')]);
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:150'],
            'singkatan' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'kontak' => ['nullable', 'string', 'regex:/\A62[0-9]{8,13}\z/'],
            'instagram' => ['nullable', 'string', 'max:100', 'regex:/\A[A-Za-z0-9._]+\z/'],
            'mode_penerimaan' => ['required', 'string', Rule::in(['seleksi', 'tanpa_seleksi'])],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama sekolah wajib diisi.',
            'kontak.regex' => 'Nomor WhatsApp tidak valid. Gunakan format 08... atau 62....',
            'instagram.regex' => 'Nama pengguna Instagram hanya boleh huruf, angka, titik, dan garis bawah.',
            'mode_penerimaan.in' => 'Mode penerimaan tidak dikenal.',
        ];
    }
}
