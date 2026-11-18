@extends('layouts.walisantri')

@section('judul', 'Formulir Tahap 2')

@section('isi')
    <div class="space-y-4">
        <x-kepala-halaman
            judul="Formulir Tahap 2"
            :anak="$namaAnak"
            keterangan="Formulir ini diisi setelah anak dinyatakan diterima. Wawancara dilakukan bersamaan dengan daftar ulang di sekolah."
        />

        @if (empty($isian))
            <x-kosong
                judul="Belum ada isian Tahap 2"
                keterangan="Sekolah belum menetapkan isian tambahan untuk tahap ini."
            />
        @else
            <form method="POST" action="{{ $aksiSimpan }}" class="space-y-4 rounded-2xl border border-[var(--garis)] bg-white p-5" novalidate>
                @csrf
                @method('PUT')

                @foreach ($isian as $kunci => $definisi)
                    @php
                        $label = $definisi['label'] ?? ucwords(str_replace('_', ' ', $kunci));
                        $wajib = (bool) ($definisi['wajib'] ?? false);
                        $tipe = $definisi['tipe'] ?? 'teks';
                        $nilaiField = $nilai[$kunci] ?? null;
                    @endphp

                    @if ($tipe === 'centang')
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-[var(--garis)] p-3">
                            <input type="checkbox" name="{{ $kunci }}" value="1" class="mt-1"
                                   @checked((bool) old($kunci, $nilaiField)) @required($wajib)>
                            <span class="text-sm text-[var(--teks)]">{{ $label }}</span>
                        </label>
                        @error($kunci)
                            <p class="text-sm text-[var(--bahaya)]">{{ $message }}</p>
                        @enderror
                    @elseif ($tipe === 'teks_panjang')
                        <x-isian :name="$kunci" :label="$label" tipe="teks_panjang" :nilai="$nilaiField" :wajib="$wajib" />
                    @else
                        <x-isian :name="$kunci" :label="$label" :nilai="$nilaiField" :wajib="$wajib" />
                    @endif
                @endforeach

                <div class="flex flex-wrap gap-2">
                    <x-tombol>Simpan formulir</x-tombol>

                    <a href="{{ route('walisantri.pendaftaran.detail', $pendaftaranUuid) }}"
                       class="inline-flex min-h-12 items-center justify-center rounded-xl px-4 text-sm font-semibold text-[var(--primer)] hover:underline">
                        Kembali
                    </a>
                </div>
            </form>
        @endif
    </div>
@endsection
