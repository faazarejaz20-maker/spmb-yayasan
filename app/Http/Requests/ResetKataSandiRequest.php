<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetKataSandiRequest extends FormRequest
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
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:150'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters(),
                function ($attribute, $value, $fail) {
                    if (strtolower((string) $value) === strtolower((string) $this->input('email'))) {
                        $fail('Kata sandi tidak boleh sama dengan email.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'Isi kata sandi baru.',
            'password.confirmed' => 'Ulangi kata sandi baru dengan tepat.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
        ];
    }
}
