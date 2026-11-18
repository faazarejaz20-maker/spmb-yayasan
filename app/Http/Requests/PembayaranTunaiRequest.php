<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PembayaranTunaiRequest extends FormRequest
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
            'nominal' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'dibayar_pada' => ['required', 'date', 'before_or_equal:today'],
            'rincian_biaya_id' => ['nullable', 'integer'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'nominal.required' => 'Isi nominal pembayaran tunai.',
            'nominal.integer' => 'Nominal harus berupa angka bulat rupiah.',
            'nominal.min' => 'Nominal harus lebih dari nol.',
            'dibayar_pada.required' => 'Isi tanggal pembayaran.',
            'dibayar_pada.before_or_equal' => 'Tanggal pembayaran tidak boleh di masa depan.',
        ];
    }
}
