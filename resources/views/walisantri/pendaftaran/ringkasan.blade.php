@extends($layout ?? 'layouts.walisantri')

@section('judul', 'Ringkasan & Kirim')

@section('isi')
    @php
        $ruteLangkah = $ruteLangkahNama ?? 'walisantri.pendaftaran.langkah';
        $ruteBerkas = $ruteBerkasNama ?? 'walisantri.pendaftaran.berkas.index';
        $tautanOrangTua = $tautanOrangTua ?? route('walisantri.orang-tua');
        $aksiKirim = $aksiKirim ?? route('walisantri.pendaftaran.kirim', $pendaftaranUuid);
    @endphp

    <div class="space-y-4">
        @if (! empty($banner))
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">
                {{ $banner }}
            </div>
        @endif

        <x-kepala-halaman
            judul="Periksa & kirim"
            eyebrow="Langkah 8 dari 8"
            keterangan="Periksa kembali seluruh data. Setelah dikirim, data tidak dapat diubah kecuali panitia meminta perbaikan."
        />

        <x-stepper :langkah="$langkah" />

        @if (! empty($kekurangan))
            <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4">
                <h2 class="text-sm font-bold text-amber-900">Masih ada yang perlu dilengkapi</h2>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-amber-900">
                    @foreach ($kekurangan as $kurang)
                        <li>
                            {{ $kurang['pesan'] }}
                            @if (! empty($kurang['tautan']))
                                <a href="{{ $kurang['tautan'] }}" class="font-semibold underline">Perbaiki</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="space-y-3">
            @foreach ($ringkasan['bagian'] as $judulBagian => $isian)
                <div class="rounded-2xl border border-[var(--garis)] bg-white p-4">
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="text-sm font-bold text-[var(--teks)]">{{ $judulBagian }}</h2>
                        @if ($judulBagian === 'Data anak')
                            <a href="{{ route($ruteLangkah, ['pendaftaran' => $pendaftaranUuid, 'n' => 2]) }}"
                               class="text-xs font-semibold text-[var(--primer)] hover:underline">Ubah</a>
                        @endif
                    </div>

                    <dl class="mt-2 divide-y divide-[var(--garis)]">
                        @foreach ($isian as $baris)
                            <div class="flex items-start justify-between gap-3 py-1.5 text-sm">
                                <dt class="text-[var(--teks-sekunder)]">{{ $baris['label'] }}</dt>
                                <dd class="max-w-[60%] text-right font-medium text-[var(--teks)]">{{ $baris['nilai'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endforeach
        </div>

        <div class="rounded-2xl border border-[var(--garis)] bg-white p-4">
            <h2 class="text-sm font-bold text-[var(--teks)]">Orang tua / wali</h2>

            <dl class="mt-2 divide-y divide-[var(--garis)]">
                @forelse ($ringkasan['orang_tua'] as $hubungan => $ortu)
                    <div class="flex items-start justify-between gap-3 py-1.5 text-sm">
                        <dt class="capitalize text-[var(--teks-sekunder)]">{{ $hubungan }}</dt>
                        <dd class="text-right font-medium text-[var(--teks)]">
                            {{ $ortu['nama'] ?: '—' }}
                            @if ($ortu['no_hp'])
                                <span class="block text-xs text-[var(--teks-sekunder)]">{{ $ortu['no_hp'] }}</span>
                            @endif
                        </dd>
                    </div>
                @empty
                    <div class="py-2 text-sm text-[var(--teks-sekunder)]">Belum ada data orang tua.</div>
                @endforelse
            </dl>

            <a href="{{ $tautanOrangTua }}" class="mt-2 inline-block text-xs font-semibold text-[var(--primer)] hover:underline">
                Ubah data orang tua
            </a>
        </div>

        <div class="rounded-2xl border border-[var(--garis)] bg-white p-4">
            <h2 class="text-sm font-bold text-[var(--teks)]">Berkas</h2>

            <ul class="mt-2 space-y-1 text-sm">
                @forelse ($ringkasan['berkas'] as $berkasItem)
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-[var(--teks)]">{{ $berkasItem['nama'] }}</span>
                        <span class="text-xs text-[var(--teks-sekunder)]">{{ $berkasItem['status'] }}</span>
                    </li>
                @empty
                    <li class="text-[var(--teks-sekunder)]">Belum ada berkas diunggah.</li>
                @endforelse
            </ul>

            <a href="{{ route($ruteBerkas, $pendaftaranUuid) }}"
               class="mt-2 inline-block text-xs font-semibold text-[var(--primer)] hover:underline">
                Kelola berkas
            </a>
        </div>

        <div class="rounded-2xl border border-[var(--garis)] bg-white p-4">
            <h2 class="text-sm font-bold text-[var(--teks)]">Biaya pendidikan</h2>

            <div class="mt-2 space-y-1 text-sm">
                @foreach ($ringkasan['rincian'] as $biaya)
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-[var(--teks-sekunder)]">{{ $biaya->nama }}</span>
                        <span class="font-medium text-[var(--teks)]">Rp{{ number_format((int) $biaya->nominal, 0, ',', '.') }}</span>
                    </div>
                @endforeach

                @if ($ringkasan['rincian']->isEmpty())
                    <p class="text-[var(--teks-sekunder)]">Rincian biaya menyusul dari sekolah.</p>
                @endif
            </div>

            @if ($ringkasan['terpilih'])
                <p class="mt-3 text-sm text-[var(--teks-sekunder)]">
                    IAP dipilih: <span class="font-semibold text-[var(--teks)]">Rp{{ number_format((int) $ringkasan['terpilih'], 0, ',', '.') }}</span>
                </p>
            @endif

            <div class="mt-3 flex items-center justify-between border-t border-[var(--garis)] pt-3 text-sm">
                <span class="font-semibold text-[var(--teks)]">Perkiraan total</span>
                <span class="font-bold text-[var(--primer)]">Rp{{ number_format((int) $ringkasan['total'], 0, ',', '.') }}</span>
            </div>

            <p class="mt-2 text-xs text-[var(--teks-sekunder)]">Biaya daftar ulang ditagihkan terpisah setelah hasil diumumkan.</p>
        </div>

        <form method="POST" action="{{ $aksiKirim }}"
              class="space-y-3 rounded-2xl border border-[var(--garis)] bg-white p-4" novalidate>
            @csrf

            @error('kirim')
                <p class="text-sm text-[var(--bahaya)]">{{ $message }}</p>
            @enderror

            <label class="flex cursor-pointer items-start gap-3">
                <input type="checkbox" name="pernyataan_data" value="1" class="mt-1" required @checked(old('pernyataan_data'))>
                <span class="text-sm text-[var(--teks)]">
                    Saya menyatakan bahwa data yang saya isi benar dan dapat dipertanggungjawabkan.
                </span>
            </label>

            <label class="flex cursor-pointer items-start gap-3">
                <input type="checkbox" name="pernyataan_privasi" value="1" class="mt-1" required @checked(old('pernyataan_privasi'))>
                <span class="text-sm text-[var(--teks)]">
                    Saya menyetujui penggunaan data sesuai
                    <a href="{{ route('kebijakan-privasi') }}" target="_blank" rel="noopener" class="font-semibold text-[var(--primer)] underline">Kebijakan Privasi</a>.
                </span>
            </label>

            <x-tombol>Kirim pendaftaran</x-tombol>
        </form>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route($ruteLangkah, ['pendaftaran' => $pendaftaranUuid, 'n' => 7]) }}"
               class="inline-flex min-h-12 items-center justify-center rounded-xl px-4 text-sm font-semibold text-[var(--primer)] hover:underline">
                Kembali ke biaya
            </a>
            @if (! empty($tautanDetail))
                <a href="{{ $tautanDetail }}"
                   class="inline-flex min-h-12 items-center justify-center rounded-xl px-4 text-sm font-semibold text-[var(--teks-sekunder)] hover:underline">
                    Simpan sebagai draf
                </a>
            @endif
        </div>
    </div>
@endsection
