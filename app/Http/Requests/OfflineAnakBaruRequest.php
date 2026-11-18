<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OfflineAnakBaruRequest extends FormRequest
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
        $sesi = $this->session()->get('pendaftaran_baru');

        if (! is_array($sesi) || empty($sesi['sekolah_id'])) {
            return [];
        }

        return app(\App\Services\AturanIsian::class)->aturanValidasi((int) $sesi['sekolah_id'], 'identitas');
    }
}
