<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class GantiPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $wajib = (bool) auth()->user()->wajib_ganti_password;

        $aturan = [
            'kata_sandi_baru' => [
                'required',
                'confirmed',
                Password::min(8)->letters(),
                function ($attribute, $value, $fail) {
                    $user = auth()->user();

                    if ($user->no_hp && hash_equals($user->no_hp, preg_replace('/\D+/', '', $value))) {
                        $fail('Kata sandi tidak boleh sama dengan nomor HP.');
                    }

                    if ($user->email && strtolower($user->email) === strtolower($value)) {
                        $fail('Kata sandi tidak boleh sama dengan email.');
                    }
                },
            ],
        ];

        if (! $wajib) {
            $aturan['kata_sandi_lama'] = ['required', 'current_password'];
        }

        return $aturan;
    }

    public function messages(): array
    {
        return [
            'kata_sandi_lama.current_password' => 'Kata sandi lama tidak sesuai.',
            'kata_sandi_baru.confirmed' => 'Ulangi kata sandi baru tidak sesuai.',
        ];
    }
}
