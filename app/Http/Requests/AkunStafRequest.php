<?php

namespace App\Http\Requests;

use App\Support\NomorHp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AkunStafRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('no_hp')) {
            try {
                $this->merge(['no_hp' => NomorHp::normalisasi($this->input('no_hp'))]);
            } catch (\InvalidArgumentException) {
                // Biarkan nilai asli agar pesan galat validasi yang muncul.
            }
        }

        if ($this->has('email')) {
            $email = trim((string) $this->input('email'));
            $this->merge(['email' => $email === '' ? null : strtolower($email)]);
        }
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:150'],
            'no_hp' => [
                'required',
                'string',
                'regex:/\A62[0-9]{8,13}\z/',
                Rule::unique('users', 'no_hp'),
            ],
            'email' => ['nullable', 'string', 'email', 'max:150', Rule::unique('users', 'email')],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama akun wajib diisi.',
            'no_hp.required' => 'Nomor HP wajib diisi.',
            'no_hp.regex' => 'Nomor HP tidak valid. Gunakan format 08... atau 62....',
            'no_hp.unique' => 'Nomor HP sudah dipakai akun lain.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah dipakai akun lain.',
        ];
    }
}
