<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PengaturanIsianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'isian' => ['required', 'array'],
            'isian.*' => ['array'],
            'isian.*.tampil' => ['sometimes', 'boolean'],
            'isian.*.wajib' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Hanya kunci yang benar-benar ada di katalog yang diproses.
     *
     * @return array<string, array{tampil: bool, wajib: bool}>
     */
    public function isianTersaring(): array
    {
        $sah = [];

        foreach (['identitas', 'alamat', 'kesehatan', 'asal_sekolah', 'tahap2'] as $bagian) {
            foreach (array_keys((array) config('isian.'.$bagian, [])) as $kunci) {
                $sah[$kunci] = $bagian;
            }
        }

        $hasil = [];

        foreach ((array) $this->input('isian', []) as $kunci => $nilai) {
            if (! isset($sah[$kunci]) || ! is_array($nilai)) {
                continue;
            }

            $hasil[$kunci] = [
                'tampil' => filter_var($nilai['tampil'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'wajib' => filter_var($nilai['wajib'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return $hasil;
    }

    public function messages(): array
    {
        return [
            'isian.required' => 'Tidak ada isian yang dikirim.',
        ];
    }
}
