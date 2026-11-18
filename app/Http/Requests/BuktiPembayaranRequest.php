<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BuktiPembayaranRequest extends FormRequest
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
            'nominal' => ['required', 'numeric', 'min:1', 'max:1000000000'],
            'dibayar_pada' => ['required', 'date', 'before_or_equal:today'],
            'bukti' => ['required', 'file'],
        ];
    }

    public function messages(): array
    {
        return [
            'nominal.required' => 'Isi nominal yang ditransfer.',
            'dibayar_pada.required' => 'Isi tanggal transfer.',
            'bukti.required' => 'Unggah bukti transfer.',
        ];
    }
}
