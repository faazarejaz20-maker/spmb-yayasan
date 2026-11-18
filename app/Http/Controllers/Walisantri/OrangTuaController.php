<?php

namespace App\Http\Controllers\Walisantri;

use App\Enums\HubunganOrangTua;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrangTuaRequest;
use App\Models\OrangTua;
use App\Support\Catat;
use App\Support\DataOrangTua;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrangTuaController extends Controller
{
    public function form(Request $request): View
    {
        $data = $request->user()
            ->orangTua()
            ->get()
            ->keyBy(fn (OrangTua $orangTua) => $orangTua->hubungan->value);

        return view('walisantri.orang-tua', [
            'data' => $data,
            'hubungan' => HubunganOrangTua::cases(),
            'statusHidup' => OrangTuaRequest::STATUS_HIDUP,
        ]);
    }

    public function simpan(OrangTuaRequest $request): RedirectResponse
    {
        $pengguna = $request->user();
        $data = $request->dataTernormalisasi();

        DB::transaction(function () use ($pengguna, $data) {
            foreach (HubunganOrangTua::cases() as $hubungan) {
                DataOrangTua::simpan($pengguna->id, $hubungan, $data[$hubungan->value] ?? []);
            }
        });

        Catat::aksi('perbarui_orang_tua', null, [], null);

        return redirect()
            ->route('walisantri.orang-tua')
            ->with('sukses', 'Data orang tua berhasil disimpan.');
    }
}
