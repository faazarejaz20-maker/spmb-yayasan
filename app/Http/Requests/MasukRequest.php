<?php

namespace App\Http\Requests;

use App\Support\NomorHp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class MasukRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identitas' => ['required', 'string'],
            'kata_sandi' => ['required', 'string'],
        ];
    }

    /**
     * Identitas yang sudah dinormalisasi (nomor HP berformat 62... atau email).
     */
    public function identitasTernormalisasi(): string
    {
        $identitas = trim((string) $this->input('identitas'));

        if (str_contains($identitas, '@')) {
            return strtolower($identitas);
        }

        try {
            return NomorHp::normalisasi($identitas);
        } catch (\InvalidArgumentException) {
            // Bila bukan nomor valid, biarkan mentah agar pesan galatnya seragam.
            return $identitas;
        }
    }

    public function pesanGalat(): string
    {
        return 'Nomor HP/email atau kata sandi tidak sesuai.';
    }

    public function throwGalat(): never
    {
        throw ValidationException::withMessages([
            'identitas' => [$this->pesanGalat()],
        ]);
    }
}
