<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LupaKataSandiRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Isi alamat email akun Anda.',
            'email.email' => 'Format email tidak valid.',
        ];
    }
}
