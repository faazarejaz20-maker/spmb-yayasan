<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnggahBerkasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Pemeriksaan isi berkas dilakukan di BerkasService (finfo, GD, PDF).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'jenis_berkas_id' => ['required', 'integer', 'exists:jenis_berkas,id'],
            'berkas' => ['required', 'file'],
        ];
    }

    public function messages(): array
    {
        return [
            'berkas.required' => 'Pilih berkas atau ambil foto terlebih dahulu.',
            'berkas.file' => 'Berkas gagal diunggah. Silakan coba lagi.',
        ];
    }
}
