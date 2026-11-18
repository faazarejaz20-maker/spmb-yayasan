@extends($layout ?? 'layouts.walisantri')

@section('judul', $judulBagian.' — Langkah '.$langkah)

@section('isi')
    <div class="space-y-4">
        @if (! empty($banner))
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">
                {{ $banner }}
            </div>
        @endif

        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-[var(--primer)]">Langkah {{ $langkah }} dari 8</p>
                <h1 class="mt-0.5 text-xl font-bold text-[var(--teks)]">{{ $judulBagian }}</h1>
                <p class="mt-1 text-sm text-[var(--teks-sekunder)]">{{ $judulSekolah }}@if ($program) · Program {{ $program }}@endif</p>
            </div>

            @if ($pendaftaranUuid && ! empty($tautanDetail))
                <a href="{{ $tautanDetail }}"
                   class="inline-flex min-h-10 items-center rounded-xl border border-[var(--garis)] bg-white px-3 text-sm font-semibold text-[var(--primer)] hover:bg-gray-50">
                    Simpan & keluar
                </a>
            @endif
        </div>

        <x-stepper :langkah="$langkah" />

        <form method="POST" action="{{ $aksiSimpan }}" class="space-y-4 rounded-2xl border border-[var(--garis)] bg-white p-5" novalidate>
            @csrf
            @method('PUT')

            @error('data_ganda')
                <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ $message }}</div>
            @enderror

            @foreach ($isian as $kunci => $definisi)
                @php
                    $label = $definisi['label'] ?? ucwords(str_replace('_', ' ', $kunci));
                    $wajib = (bool) ($definisi['wajib'] ?? false);
                    $tipe = $definisi['tipe'] ?? 'teks';
                    $nilaiField = $nilai[$kunci] ?? null;
                @endphp

                @if ($tipe === 'pilihan')
                    <x-pilihan :name="$kunci" :label="$label" :opsi="$definisi['pilihan'] ?? []" :nilai="$nilaiField" :wajib="$wajib" />
                @elseif ($tipe === 'tanggal')
                    <x-tanggal :name="$kunci" :label="$label" :nilai="$nilaiField" :wajib="$wajib" />
                @elseif ($tipe === 'angka')
                    <x-isian :name="$kunci" :label="$label" tipe="angka" inputmode="numeric" :nilai="$nilaiField" :wajib="$wajib" />
                @elseif ($tipe === 'teks_panjang')
                    <x-isian :name="$kunci" :label="$label" tipe="teks_panjang" :nilai="$nilaiField" :wajib="$wajib" />
                @elseif ($kunci === 'nik' || $kunci === 'no_kk')
                    <x-isian :name="$kunci" :label="$label" inputmode="numeric" maxlength="16" :nilai="$nilaiField" :wajib="$wajib"
                             bantuan="16 angka. Data ini disimpan terenkripsi." />
                @elseif ($kunci === 'nisn')
                    <x-isian :name="$kunci" :label="$label" inputmode="numeric" maxlength="10" :nilai="$nilaiField" :wajib="$wajib" />
                @else
                    <x-isian :name="$kunci" :label="$label" :nilai="$nilaiField" :wajib="$wajib" />
                @endif
            @endforeach

            @if ($bagian === 'asal_sekolah' && $lulusanYayasan)
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">
                    Asal sekolah yang Anda isi adalah sekolah di bawah Yayasan Al-Ishlah Insan Mulia.
                </div>
            @endif

            <div class="flex flex-wrap gap-2">
                <x-tombol>Simpan & lanjut</x-tombol>

                @if ($pendaftaranUuid)
                    <button type="submit" name="aksi" value="draf"
                            class="inline-flex min-h-12 items-center justify-center rounded-xl border border-[var(--garis)] bg-white px-4 text-sm font-semibold text-[var(--teks)] hover:bg-gray-50">
                        Simpan draf
                    </button>
                @endif

                @if ($langkah > 2)
                    @php
                        $sebelum = route($ruteLangkahNama ?? 'walisantri.pendaftaran.langkah', ['pendaftaran' => $pendaftaranUuid, 'n' => $langkah - 1]);
                    @endphp
                    <a href="{{ $sebelum }}"
                       class="inline-flex min-h-12 items-center justify-center rounded-xl px-4 text-sm font-semibold text-[var(--primer)] hover:underline">
                        Kembali
                    </a>
                @endif
            </div>
        </form>

        <p class="px-1 text-xs text-[var(--teks-sekunder)]">
            Data yang bertanda <span class="font-bold text-[var(--bahaya)]">*</span> wajib diisi.
            Anda dapat menyimpan draf dan melanjutkan nanti.
        </p>
    </div>
@endsection
