<?php

namespace App\Http\Requests;

use App\Models\Pendaftaran;
use App\Services\AturanIsian;
use Illuminate\Foundation\Http\FormRequest;

class Tahap2Request extends FormRequest
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
        $pendaftaran = $this->route('pendaftaran');

        if (! $pendaftaran instanceof Pendaftaran) {
            return [];
        }

        return app(AturanIsian::class)->aturanValidasi($pendaftaran->sekolah_id, 'tahap2');
    }
}
