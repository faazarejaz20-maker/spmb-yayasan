<?php

namespace App\Http\Requests;

use App\Models\Pendaftaran;
use App\Services\AturanIsian;
use Illuminate\Foundation\Http\FormRequest;

class DataPendaftarRequest extends FormRequest
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
        /** @var Pendaftaran|null $pendaftaran */
        $pendaftaran = $this->route('pendaftaran');

        if (! $pendaftaran instanceof Pendaftaran) {
            return [];
        }

        $aturan = [];

        foreach (['identitas', 'alamat', 'kesehatan', 'asal_sekolah'] as $bagian) {
            $aturan = array_merge(
                $aturan,
                app(AturanIsian::class)->aturanValidasi($pendaftaran->sekolah_id, $bagian),
            );
        }

        foreach (['ayah', 'ibu', 'wali'] as $hubungan) {
            $aturan[$hubungan] = ['nullable', 'array'];
            $aturan[$hubungan.'.nama'] = ['nullable', 'string', 'max:150'];
            $aturan[$hubungan.'.nik'] = ['nullable', 'digits:16'];
            $aturan[$hubungan.'.tempat_lahir'] = ['nullable', 'string', 'max:100'];
            $aturan[$hubungan.'.tanggal_lahir'] = ['nullable', 'date', 'before:today'];
            $aturan[$hubungan.'.status_hidup'] = ['nullable', 'in:'.implode(',', OrangTuaRequest::STATUS_HIDUP)];
            $aturan[$hubungan.'.pendidikan'] = ['nullable', 'string', 'max:50'];
            $aturan[$hubungan.'.pekerjaan'] = ['nullable', 'string', 'max:100'];
            $aturan[$hubungan.'.penghasilan'] = ['nullable', 'string', 'max:50'];
            $aturan[$hubungan.'.no_hp'] = ['nullable', 'string', 'max:20'];
            $aturan[$hubungan.'.alamat'] = ['nullable', 'string', 'max:500'];
        }

        return $aturan;
    }
}
