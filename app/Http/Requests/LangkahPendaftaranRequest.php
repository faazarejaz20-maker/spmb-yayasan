<?php

namespace App\Http\Requests;

use App\Models\Pendaftaran;
use App\Services\AturanIsian;
use Illuminate\Foundation\Http\FormRequest;

class LangkahPendaftaranRequest extends FormRequest
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

        $bagian = AturanIsian::bagianDariLangkah((int) $this->route('n'));

        if ($bagian === null) {
            return [];
        }

        return app(AturanIsian::class)->aturanValidasi($pendaftaran->sekolah_id, $bagian);
    }
}
