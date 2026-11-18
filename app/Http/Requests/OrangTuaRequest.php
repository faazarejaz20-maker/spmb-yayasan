<?php

namespace App\Http\Requests;

use App\Support\NomorHp;
use Illuminate\Foundation\Http\FormRequest;

class OrangTuaRequest extends FormRequest
{
    public const STATUS_HIDUP = ['Masih hidup', 'Sudah wafat'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $aturan = [];

        foreach (['ayah', 'ibu', 'wali'] as $hubungan) {
            $aturan[$hubungan] = ['nullable', 'array'];
            $aturan[$hubungan.'.nama'] = ['nullable', 'string', 'max:150'];
            $aturan[$hubungan.'.nik'] = ['nullable', 'digits:16'];
            $aturan[$hubungan.'.tempat_lahir'] = ['nullable', 'string', 'max:100'];
            $aturan[$hubungan.'.tanggal_lahir'] = ['nullable', 'date', 'before:today'];
            $aturan[$hubungan.'.status_hidup'] = ['nullable', 'in:'.implode(',', self::STATUS_HIDUP)];
            $aturan[$hubungan.'.pendidikan'] = ['nullable', 'string', 'max:50'];
            $aturan[$hubungan.'.pekerjaan'] = ['nullable', 'string', 'max:100'];
            $aturan[$hubungan.'.penghasilan'] = ['nullable', 'string', 'max:50'];
            $aturan[$hubungan.'.alamat'] = ['nullable', 'string', 'max:1000'];
            $aturan[$hubungan.'.no_hp'] = [
                'nullable',
                function (string $atribut, mixed $nilai, \Closure $gagal) {
                    if (blank($nilai)) {
                        return;
                    }

                    try {
                        NomorHp::normalisasi((string) $nilai);
                    } catch (\InvalidArgumentException) {
                        $gagal('Nomor HP tidak valid.');
                    }
                },
            ];
        }

        return $aturan;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach (['ayah', 'ibu', 'wali'] as $hubungan) {
                $data = $this->input($hubungan);

                if (! is_array($data)) {
                    continue;
                }

                $terisi = collect($data)
                    ->except('status_hidup')
                    ->contains(fn ($nilai) => filled($nilai));

                if ($terisi && blank($data['nama'] ?? null)) {
                    $validator->errors()->add($hubungan.'.nama', 'Nama wajib diisi bila data '.$hubungan.' diisi.');
                }
            }
        });
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function dataTernormalisasi(): array
    {
        $hasil = [];

        foreach (['ayah', 'ibu', 'wali'] as $hubungan) {
            $data = $this->input($hubungan);

            if (! is_array($data)) {
                continue;
            }

            if (filled($data['no_hp'] ?? null)) {
                $data['no_hp'] = NomorHp::normalisasi((string) $data['no_hp']);
            } else {
                $data['no_hp'] = null;
            }

            $hasil[$hubungan] = $data;
        }

        return $hasil;
    }
}
