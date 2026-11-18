<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LogFilterRequest;
use App\Models\LogAktivitas;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class LogController extends Controller
{
    /**
     * Kunci data yang tidak boleh tampil di panel super admin karena dapat
     * menunjuk ke orang tertentu.
     */
    private const KUNCI_RAHASIA = [
        'nomor_peserta',
        'nama',
        'nama_lengkap',
        'nik',
        'no_kk',
        'nisn',
        'no_hp',
        'email',
        'alamat',
        'akun_asal',
        'akun_tujuan',
    ];

    public function index(LogFilterRequest $request): View
    {
        $data = $request->validated();

        $dasar = LogAktivitas::query()
            ->tanpaSekolah()
            ->when(filled($data['sekolah'] ?? null), fn ($q) => $q->where('sekolah_id', (int) $data['sekolah']));

        $paginator = (clone $dasar)
            ->with('user')
            ->when(filled($data['dari'] ?? null), fn ($q) => $q->whereDate('dibuat_pada', '>=', $data['dari']))
            ->when(filled($data['sampai'] ?? null), fn ($q) => $q->whereDate('dibuat_pada', '<=', $data['sampai']))
            ->when(filled($data['pelaku'] ?? null), fn ($q) => $q->where('user_id', (int) $data['pelaku']))
            ->when(filled($data['aksi'] ?? null), fn ($q) => $q->where('aksi', $data['aksi']))
            ->orderByDesc('dibuat_pada')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $log = new LengthAwarePaginator(
            collect($paginator->items())->map(fn (LogAktivitas $baris) => [
                'id' => $baris->id,
                'user' => $baris->user?->nama,
                'sekolah' => $baris->sekolah_id,
                'aksi' => $baris->aksi,
                'objek' => $baris->objek,
                'data' => $this->dataAman($baris->data),
                'dibuat_pada' => $baris->dibuat_pada,
            ]),
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            ['path' => $paginator->path(), 'query' => $request->query()],
        );

        return view('super-admin.log.index', [
            'log' => $log,
            'sekolah' => Sekolah::query()->orderBy('nama')->get(['id', 'nama']),
            'pelaku' => User::query()
                ->whereIn('id', (clone $dasar)->whereNotNull('user_id')->distinct()->pluck('user_id'))
                ->orderBy('nama')
                ->get(['id', 'nama', 'sekolah_id']),
            'daftarAksi' => (clone $dasar)->distinct()->orderBy('aksi')->pluck('aksi'),
            'filter' => $data,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dataAman(?string $data): array
    {
        if (! is_string($data) || $data === '') {
            return [];
        }

        $terurai = json_decode($data, true);

        if (! is_array($terurai)) {
            return [];
        }

        return $this->saring($terurai, true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function saring(array $data, bool $akar = false): array
    {
        $hasil = [];

        foreach ($data as $kunci => $nilai) {
            $kunci = (string) $kunci;

            if (! $akar && in_array(strtolower($kunci), self::KUNCI_RAHASIA, true)) {
                continue;
            }

            if ($akar && in_array(strtolower($kunci), self::KUNCI_RAHASIA, true)) {
                continue;
            }

            if (is_array($nilai)) {
                $hasil[$kunci] = $this->saring($nilai);
                continue;
            }

            $hasil[$kunci] = $nilai;
        }

        return $hasil;
    }
}
