@extends('layouts.cetak')

@section('judul', 'Formulir Pendaftaran')

@section('isi')
    <div class="space-y-5 text-[13px] leading-snug text-gray-900">
        <div class="grid grid-cols-2 gap-x-6 gap-y-1 border-b border-gray-300 pb-3">
            <div>
                <p class="text-xs text-gray-500">Nomor peserta</p>
                <p class="font-mono text-base font-bold">{{ $pendaftaran->nomor_peserta ?? '—' }}</p>
            </div>
            <div class="text-right">
                <p class="text-xs text-gray-500">Gelombang</p>
                <p class="font-semibold">{{ $pendaftaran->gelombang?->nama ?? '—' }}</p>
                <p class="text-xs text-gray-500">Dikirim {{ $pendaftaran->dikirim_pada?->format('d-m-Y H:i') ?? '—' }}</p>
            </div>
        </div>

        @foreach ($bagian as $kunci => $b)
            <section>
                <h2 class="mb-2 border-b border-gray-300 pb-1 text-sm font-bold uppercase tracking-wide text-gray-800">
                    {{ $b['judul'] }}
                </h2>

                <dl class="grid grid-cols-2 gap-x-6 gap-y-1">
                    @foreach ($b['isian'] as $isian)
                        <div class="flex gap-2">
                            <dt class="w-40 shrink-0 text-gray-600">{{ $isian['label'] }}</dt>
                            <dd class="min-w-0 flex-1 font-medium break-words">: {{ $isian['nilai'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endforeach

        <section>
            <h2 class="mb-2 border-b border-gray-300 pb-1 text-sm font-bold uppercase tracking-wide text-gray-800">
                Orang tua / wali
            </h2>

            @forelse ($orangTua as $ortu)
                <div class="mb-3 rounded border border-gray-300 p-2">
                    <p class="mb-1 text-xs font-bold uppercase tracking-wide text-gray-700">{{ $ortu['hubungan'] }}</p>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-0.5">
                        @foreach ([
                            'Nama' => $ortu['nama'],
                            'NIK' => $ortu['nik'],
                            'Tempat lahir' => $ortu['tempat_lahir'],
                            'Tanggal lahir' => $ortu['tanggal_lahir'],
                            'Status' => $ortu['status_hidup'],
                            'Pendidikan' => $ortu['pendidikan'],
                            'Pekerjaan' => $ortu['pekerjaan'],
                            'Penghasilan' => $ortu['penghasilan'],
                            'No. HP' => $ortu['no_hp'],
                            'Alamat' => $ortu['alamat'],
                        ] as $label => $nilai)
                            <div class="flex gap-2">
                                <dt class="w-32 shrink-0 text-gray-600">{{ $label }}</dt>
                                <dd class="min-w-0 flex-1 break-words">: {{ filled($nilai) ? $nilai : '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @empty
                <p class="text-gray-600">Tidak ada data orang tua / wali.</p>
            @endforelse
        </section>

        <section>
            <h2 class="mb-2 border-b border-gray-300 pb-1 text-sm font-bold uppercase tracking-wide text-gray-800">
                Biaya pendidikan
            </h2>

            @if ($rincian->isEmpty())
                <p class="text-gray-600">Belum ada rincian biaya.</p>
            @else
                <table class="w-full border-collapse text-[12px]">
                    <thead>
                        <tr class="border-b border-gray-400 text-left">
                            <th class="py-1">Komponen</th>
                            <th class="py-1 text-right">Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rincian as $biaya)
                            <tr class="border-b border-gray-200">
                                <td class="py-1">{{ $biaya->nama }}</td>
                                <td class="py-1 text-right">Rp {{ number_format((int) $biaya->nominal, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if ($pendaftaran->pilihan_iap !== null)
                    <p class="mt-1 text-xs text-gray-600">
                        Pilihan IAP: Rp {{ number_format((int) $pendaftaran->pilihan_iap, 0, ',', '.') }}
                    </p>
                @endif
            @endif
        </section>

        <section>
            <h2 class="mb-2 border-b border-gray-300 pb-1 text-sm font-bold uppercase tracking-wide text-gray-800">
                Berkas ({{ $berkasRingkas }})
            </h2>

            @if (empty($berkas))
                <p class="text-gray-600">Belum ada berkas diunggah.</p>
            @else
                <table class="w-full border-collapse text-[12px]">
                    <thead>
                        <tr class="border-b border-gray-400 text-left">
                            <th class="py-1">Jenis berkas</th>
                            <th class="py-1">Status</th>
                            <th class="py-1">Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($berkas as $b)
                            <tr class="border-b border-gray-200">
                                <td class="py-1">{{ $b['nama'] }}</td>
                                <td class="py-1">{{ $b['status'] }}</td>
                                <td class="py-1">{{ $b['catatan'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        @if ($tahapDua !== null)
            <section>
                <h2 class="mb-2 border-b border-gray-300 pb-1 text-sm font-bold uppercase tracking-wide text-gray-800">
                    Daftar ulang
                </h2>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-1">
                    @foreach ($tahapDua as $label => $nilai)
                        <div class="flex gap-2">
                            <dt class="w-40 shrink-0 capitalize text-gray-600">{{ $label }}</dt>
                            <dd class="min-w-0 flex-1 break-words">: {{ $nilai }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif

        <div class="mt-10 grid grid-cols-2 gap-10 text-center text-xs">
            <div>
                <p class="text-gray-600">Tanda tangan wali murid</p>
                <div class="mt-16 border-t border-gray-400 pt-1">Nama dan tanda tangan</div>
            </div>
            <div>
                <p class="text-gray-600">Panitia SPMB</p>
                <div class="mt-16 border-t border-gray-400 pt-1">Nama dan tanda tangan</div>
            </div>
        </div>
    </div>
@endsection
