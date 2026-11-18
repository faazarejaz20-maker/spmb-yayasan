<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PindahAnakRequest extends FormRequest
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
            'no_hp' => ['required', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'no_hp.required' => 'Isi nomor HP akun tujuan.',
        ];
    }
}
