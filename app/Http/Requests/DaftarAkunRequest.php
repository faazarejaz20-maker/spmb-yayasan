<?php

namespace App\Http\Requests;

use App\Support\NomorHp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class DaftarAkunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('no_hp')) {
            $this->merge(['no_hp' => NomorHp::normalisasi($this->input('no_hp'))]);
        }
    }

    public function rules(): array
    {
        $noHp = $this->input('no_hp');

        return [
            'nama' => ['required', 'string', 'max:150'],
            'no_hp' => ['required', 'string', Rule::unique('users', 'no_hp')],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')],
            'kata_sandi' => [
                'required',
                'confirmed',
                Password::min(8)->letters(),
                function ($attribute, $value, $fail) use ($noHp) {
                    if ($noHp && hash_equals($noHp, preg_replace('/\D+/', '', $value))) {
                        $fail('Kata sandi tidak boleh sama dengan nomor HP.');
                    }

                    if ($this->filled('email') && strtolower($this->input('email')) === strtolower($value)) {
                        $fail('Kata sandi tidak boleh sama dengan email.');
                    }
                },
            ],
            'setuju_data' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'setuju_data.required' => 'Anda harus menyetujui penggunaan data untuk melanjutkan.',
            'setuju_data.accepted' => 'Anda harus menyetujui penggunaan data untuk melanjutkan.',
            'kata_sandi.confirmed' => 'Ulangi kata sandi tidak sesuai.',
            'no_hp.unique' => 'Nomor HP sudah terdaftar.',
        ];
    }
}
