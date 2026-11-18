<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class AkunAdminRequest extends AkunStafRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'sekolah_id' => ['required', 'integer', Rule::exists('sekolah', 'id')],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'sekolah_id.required' => 'Pilih sekolah untuk akun admin.',
            'sekolah_id.exists' => 'Sekolah tidak ditemukan.',
        ]);
    }
}
