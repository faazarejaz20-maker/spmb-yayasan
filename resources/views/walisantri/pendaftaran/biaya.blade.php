@extends($layout ?? 'layouts.walisantri')

@section('judul', 'Biaya Pendidikan')

@section('isi')
    @php
        $adaRincian = $rincian->isNotEmpty();
        $pilihan = $rincian->where('tipe', \App\Enums\TipeBiaya::Pilihan);
        $tetap = $rincian->where('tipe', \App\Enums\TipeBiaya::Tetap);
        $ruteLangkah = $ruteLangkahNama ?? 'walisantri.pendaftaran.langkah';
        $ruteBerkas = $ruteBerkasNama ?? 'walisantri.pendaftaran.berkas.index';
    @endphp

    <div class="space-y-4">
        @if (! empty($banner))
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">
                {{ $banner }}
            </div>
        @endif

        <x-kepala-halaman
            judul="Biaya pendidikan"
            eyebrow="Langkah 7 dari 8"
            keterangan="Rincian ini khusus untuk sekolah dan tahun ajaran yang Anda pilih. Tidak ditampilkan di halaman publik."
        />

        <x-stepper :langkah="$langkah" />

        @if (! $adaRincian)
            <x-kosong
                judul="Rincian biaya menyusul"
                keterangan="Sekolah belum menetapkan rincian biaya. Anda dapat melanjutkan dan menanyakan biaya kepada panitia."
            />

            <a href="{{ route($ruteLangkah, ['pendaftaran' => $pendaftaranUuid, 'n' => 8]) }}"
               class="inline-flex min-h-12 items-center justify-center rounded-xl bg-[var(--primer)] px-5 text-sm font-semibold text-white hover:bg-[var(--primer-gelap)]">
                Lanjut ke ringkasan
            </a>
        @else
            <form method="POST" action="{{ $aksiSimpan }}" class="space-y-4" novalidate>
                @csrf
                @method('PUT')

                <div class="space-y-3">
                    @foreach ($kelompok as $namaKelompok => $baris)
                        <div class="rounded-2xl border border-[var(--garis)] bg-white p-4">
                            <h2 class="text-sm font-bold text-[var(--teks)]">{{ $namaKelompok }}</h2>

                            <dl class="mt-2 divide-y divide-[var(--garis)]">
                                @foreach ($baris as $biaya)
                                    <div class="flex items-start justify-between gap-3 py-2 text-sm">
                                        <dt class="text-[var(--teks)]">
                                            {{ $biaya->nama }}
                                            @if ($biaya->tipe === \App\Enums\TipeBiaya::Pilihan)
                                                <span class="text-xs text-[var(--teks-sekunder)]">(pilih salah satu)</span>
                                            @endif
                                        </dt>
                                        <dd class="shrink-0 font-semibold text-[var(--teks)]">
                                            Rp{{ number_format((int) $biaya->nominal, 0, ',', '.') }}
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @endforeach
                </div>

                @if ($pilihan->isNotEmpty())
                    <fieldset class="rounded-2xl border border-[var(--garis)] bg-white p-4">
                        <legend class="text-sm font-bold text-[var(--teks)]">Pilihan IAP</legend>
                        <p class="mt-1 text-xs text-[var(--teks-sekunder)]">
                            Pilih satu nominal IAP. Pilihan ini memengaruhi total biaya pendidikan.
                        </p>

                        <div class="mt-3 space-y-2">
                            @foreach ($pilihan as $opsi)
                                <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-[var(--garis)] p-3 transition hover:bg-gray-50 has-[:checked]:border-[var(--primer)] has-[:checked]:bg-teal-50">
                                    <span class="flex items-center gap-3">
                                        <input type="radio" name="pilihan_iap" value="{{ (int) $opsi->nominal }}" required class="accent-[var(--primer)]"
                                               @checked((string) old('pilihan_iap', $pilihanIap) === (string) $opsi->nominal)>
                                        <span class="text-sm font-semibold text-[var(--teks)]">
                                            {{ $opsi->nama }}
                                        </span>
                                    </span>
                                    <span class="text-sm font-bold text-[var(--teks)]">
                                        Rp{{ number_format((int) $opsi->nominal, 0, ',', '.') }}
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        @error('pilihan_iap')
                            <p class="mt-2 text-sm text-[var(--bahaya)]">{{ $message }}</p>
                        @enderror
                    </fieldset>
                @endif

                <div class="rounded-2xl border border-[var(--garis)] bg-white p-4">
                    <h2 class="text-sm font-bold text-[var(--teks)]">Ringkasan biaya tetap</h2>
                    <dl class="mt-2 divide-y divide-[var(--garis)]">
                        @foreach ($tetap as $biaya)
                            <div class="flex items-center justify-between gap-3 py-2 text-sm">
                                <dt class="text-[var(--teks-sekunder)]">{{ $biaya->nama }}</dt>
                                <dd class="font-semibold text-[var(--teks)]">Rp{{ number_format((int) $biaya->nominal, 0, ',', '.') }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <label class="mt-4 flex cursor-pointer items-start gap-3 rounded-xl bg-gray-50 p-3">
                        <input type="checkbox" name="setuju_biaya" value="1" class="mt-1" required @checked(old('setuju_biaya', $setuju))>
                        <span class="text-sm text-[var(--teks)]">
                            Saya telah membaca dan menyetujui rincian biaya pendidikan di atas.
                        </span>
                    </label>

                    @error('setuju_biaya')
                        <p class="mt-2 text-sm text-[var(--bahaya)]">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-wrap gap-2">
                    <x-tombol>Simpan & lanjut</x-tombol>

                    <a href="{{ route($ruteBerkas, $pendaftaranUuid) }}"
                       class="inline-flex min-h-12 items-center justify-center rounded-xl px-4 text-sm font-semibold text-[var(--primer)] hover:underline">
                        Kembali
                    </a>
                </div>
            </form>
        @endif
    </div>
@endsection
