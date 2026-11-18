<?php

namespace App\Http\Requests;

use App\Enums\PeranPengguna;
use App\Enums\StatusHasil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi ekspor data pendaftar: penyaring daftar putih, kolom daftar putih
 * (`config/ekspor.php`), format xlsx/csv, dan konfirmasi kolom sensitif.
 */
class EksporRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->peran, [PeranPengguna::Panitia, PeranPengguna::Admin], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $statusSah = array_column(StatusHasil::cases(), 'value');
        $programSah = (array) config('spmb.nomor_peserta.program_tki');

        $aturan = [
            'format' => ['required', 'in:xlsx,csv'],
            'saring.cari' => ['nullable', 'string', 'max:150'],
            'saring.gelombang' => ['nullable', 'integer'],
            'saring.status' => ['nullable', 'in:'.implode(',', $statusSah)],
            'saring.jenis' => ['nullable', 'in:baru,lulusan'],
            'saring.pembayaran' => ['nullable', 'in:belum,menunggu,disetujui,ditolak'],
            'saring.kelengkapan' => ['nullable', 'in:perlu_perbaikan,lengkap'],
            'kolom' => ['required', 'array', 'min:1'],

            'sensitif' => ['nullable', 'boolean'],
            'konfirmasi_sensitif' => ['nullable', 'boolean'],
        ];

        if (array_key_exists('program', $this->input('saring', [])) && $this->input('saring.program') !== null) {
            $aturan['saring.program'] = ['nullable', 'in:'.implode(',', array_map('strtoupper', $programSah))];
        }

        return $aturan;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'format.in' => 'Format unduhan harus Excel atau CSV.',
            'kolom.required' => 'Pilih minimal satu kolom untuk diekspor.',
        ];
    }

    /**
     * Penyaring yang sudah dibersihkan untuk EksporService.
     *
     * @return array<string, mixed>
     */
    public function saring(): array
    {
        $gelombang = (int) $this->input('saring.gelombang', 0);

        return [
            'cari' => trim((string) $this->input('saring.cari', '')),
            'gelombang' => $gelombang ?: null,
            'status' => (string) $this->input('saring.status', ''),
            'jenis' => (string) $this->input('saring.jenis', ''),
            'pembayaran' => (string) $this->input('saring.pembayaran', ''),
            'program' => trim((string) $this->input('saring.program', '')),
            'kelengkapan' => (string) $this->input('saring.kelengkapan', ''),
        ];
    }

    /**
     * Kolom dipilih (array `kolom[nama]`), dibersihkan di service lewat daftar
     * putih; di sini hanya diambil kuncinya.
     *
     * @return array<string, true>
     */
    public function kolom(): array
    {
        $dipilih = (array) $this->input('kolom', []);

        return array_fill_keys(array_map(
            fn ($kunci) => substr((string) $kunci, 0, 64),
            array_keys($dipilih),
        ), true);
    }

    /**
     * Format berkas yang dipilih. Tidak dinamai `format()` karena bentrok
     * dengan `Illuminate\Http\Request::format()`.
     */
    public function formatEkspor(): string
    {
        return (string) $this->input('format', 'xlsx');
    }

    public function ikutSensitif(): bool
    {
        if ($this->boolean('sensitif')) {
            return true;
        }

        // Kolom sensitif yang dicentang di formulir sudah cukup menjadi
        // penanda; konfirmasi tetap wajib (lihat `kolomTerpilih`).
        $sensitif = [];

        foreach (config('ekspor.sensitif') as $grup) {
            foreach (array_keys($grup['kolom']) as $kunci) {
                $sensitif[$kunci] = true;
            }
        }

        return array_intersect_key((array) $this->input('kolom', []), $sensitif) !== [];
    }

    public function konfirmasiSensitif(): bool
    {
        return (bool) $this->boolean('konfirmasi_sensitif');
    }

    /**
     * Gelombang yang dipilih harus milik sekolah pengguna (jika dipilih).
     */
    public function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            $gelombang = (int) $this->input('saring.gelombang', 0);

            if ($gelombang && ! \App\Models\Gelombang::query()
                ->where('id', $gelombang)
                ->where('sekolah_id', $this->user()?->sekolah_id)
                ->exists()) {
                $validator->errors()->add('saring.gelombang', 'Gelombang tidak ditemukan di sekolah Anda.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $saring = (array) $this->input('saring', []);

        if (isset($saring['cari'])) {
            $saring['cari'] = trim((string) $saring['cari']);
        }

        $this->merge(['saring' => $saring]);
    }
}
