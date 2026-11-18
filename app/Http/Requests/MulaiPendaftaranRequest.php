<?php

namespace App\Http\Requests;

use App\Models\Gelombang;
use App\Models\Sekolah;
use App\Services\PendaftaranService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MulaiPendaftaranRequest extends FormRequest
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
            'sekolah_id' => ['required', 'integer', Rule::exists('sekolah', 'id')->where('aktif', true)],
            'gelombang_id' => ['required', 'integer', 'exists:gelombang,id'],
            'program' => ['nullable', 'string', Rule::in(config('spmb.nomor_peserta.program_tki'))],
            'anak_id' => ['nullable', 'integer', 'exists:anak,id'],
            'anak_baru' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $sekolah = Sekolah::query()->find($this->input('sekolah_id'));
            $gelombang = Gelombang::query()->find($this->input('gelombang_id'));

            if (! $sekolah || ! $gelombang) {
                return;
            }

            if ((int) $gelombang->sekolah_id !== (int) $sekolah->id) {
                $validator->errors()->add('gelombang_id', 'Gelombang tidak sesuai dengan sekolah yang dipilih.');

                return;
            }

            if (! app(PendaftaranService::class)->gelombangDibuka($gelombang)) {
                $validator->errors()->add('gelombang_id', 'Pendaftaran belum dibuka untuk gelombang ini.');
            }

            $kodeProgram = config('spmb.nomor_peserta.kode_program');

            if ($sekolah->kode === $kodeProgram) {
                if (! in_array($this->input('program'), config('spmb.nomor_peserta.program_tki'), true)) {
                    $validator->errors()->add('program', 'Pilih program KB, TK, atau TPA.');
                }
            }

            $anakId = $this->input('anak_id');

            if (filled($anakId)) {
                $milik = $this->user()
                    ->anak()
                    ->whereKey($anakId)
                    ->exists();

                if (! $milik) {
                    $validator->errors()->add('anak_id', 'Anak tidak ditemukan pada akun ini.');
                }

                return;
            }

            if (! $this->boolean('anak_baru')) {
                $validator->errors()->add('anak_id', 'Pilih anak yang sudah terdaftar atau pilih Anak baru.');
            }
        });
    }
}
