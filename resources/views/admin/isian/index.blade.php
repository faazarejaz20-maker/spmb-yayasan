@extends('layouts.panel')

@section('judul', 'Isian Formulir')

@section('isi')
    @php
        $judulBagian = [
            'identitas' => 'Identitas anak',
            'alamat' => 'Alamat',
            'kesehatan' => 'Kesehatan',
            'asal_sekolah' => 'Asal sekolah',
            'tahap2' => 'Isian tambahan (tahap 2)',
        ];
    @endphp

    <div class="space-y-5">
        <x-kepala-halaman
            judul="Isian formulir"
            :sekolah="$sekolah?->nama"
            keterangan="Isian inti selalu tampil dan wajib. Isian lain dapat disembunyikan atau dijadikan opsional. Isian yang disembunyikan tidak pernah diterima dari formulir."
        />

        @error('isian')
            <p class="text-sm text-[var(--bahaya)]">{{ $message }}</p>
        @enderror

        <form method="POST" action="{{ route('admin.isian.simpan') }}" class="space-y-4">
            @csrf
            @method('PUT')

            @foreach ($katalog as $bagian => $isianBagian)
                <x-kartu :judul="$judulBagian[$bagian] ?? ucwords(str_replace('_', ' ', $bagian))">
                    <div class="space-y-2">
                        @foreach ($isianBagian as $kunci => $definisi)
                            @php
                                $inti = (bool) ($definisi['inti'] ?? false);
                                $atur = $pengaturan->get($kunci);
                                $tampil = $inti ? true : (bool) ($atur?->tampil ?? true);
                                $wajib = $inti ? true : (bool) ($atur?->wajib ?? false);
                            @endphp

                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[var(--garis)] px-3 py-2">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-[var(--teks)]">
                                        {{ $definisi['label'] ?? $kunci }}
                                        @if ($inti)
                                            <span class="ml-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800">Inti</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-[var(--teks-sekunder)]">{{ $definisi['tipe'] ?? 'teks' }}</p>
                                </div>

                                <div class="flex items-center gap-4">
                                    <label class="flex items-center gap-2 text-sm text-[var(--teks)]">
                                        <input type="hidden" name="isian[{{ $kunci }}][tampil]" value="0">
                                        <input
                                            type="checkbox"
                                            name="isian[{{ $kunci }}][tampil]"
                                            value="1"
                                            class="h-4 w-4 rounded border-[var(--garis)]"
                                            @checked($tampil)
                                            @disabled($inti)
                                        >
                                        Tampil
                                    </label>

                                    <label class="flex items-center gap-2 text-sm text-[var(--teks)]">
                                        <input type="hidden" name="isian[{{ $kunci }}][wajib]" value="0">
                                        <input
                                            type="checkbox"
                                            name="isian[{{ $kunci }}][wajib]"
                                            value="1"
                                            class="h-4 w-4 rounded border-[var(--garis)]"
                                            @checked($wajib)
                                            @disabled($inti)
                                        >
                                        Wajib
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-kartu>
            @endforeach

            <div class="flex justify-end">
                <x-tombol>Simpan pengaturan isian</x-tombol>
            </div>
        </form>
    </div>
@endsection
