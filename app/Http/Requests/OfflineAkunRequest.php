<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OfflineAkunRequest extends FormRequest
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
            'nama' => ['required', 'string', 'max:150'],
            'no_hp' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'email', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Isi nama walisantri.',
            'no_hp.required' => 'Isi nomor HP walisantri.',
            'email.email' => 'Format email tidak valid.',
        ];
    }
}
