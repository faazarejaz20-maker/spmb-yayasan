@extends($layout ?? 'layouts.walisantri')

@section('judul', 'Berkas Pendaftaran')

@section('isi')
    @php
        $wajib = $jenis->where('wajib', true);
        $wajibTerisi = $wajib->filter(fn ($j) => $berkas->has($j->id) && filled($berkas->get($j->id)?->path))->count();
        $wajibTotal = $wajib->count();
        $ruteLangkah = $ruteLangkahNama ?? 'walisantri.pendaftaran.langkah';
        $ruteBerkas = $ruteBerkasNama ?? 'walisantri.pendaftaran.berkas.index';
    @endphp

    <div class="space-y-4">
        @if (! empty($banner))
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">
                {{ $banner }}
            </div>
        @endif

        <x-kepala-halaman judul="Unggah berkas" :anak="$namaAnak" eyebrow="Langkah 6 dari 8" />

        <x-stepper :langkah="$langkah" />

        <div class="rounded-2xl border border-[var(--garis)] bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between text-sm">
                <span class="font-semibold text-[var(--teks)]">Berkas wajib terisi</span>
                <span class="font-bold text-[var(--primer)]">{{ $wajibTerisi }} / {{ $wajibTotal }}</span>
            </div>

            <progress class="kemajuan mt-2 w-full" value="{{ $wajibTerisi }}" max="{{ max(1, $wajibTotal) }}">
                {{ $wajibTerisi }} dari {{ $wajibTotal }} berkas wajib terisi
            </progress>

            <p class="mt-3 text-xs text-[var(--teks-sekunder)]">
                Berkas disimpan di tempat privat dan hanya dapat dilihat oleh Anda dan panitia sekolah.
                Format JPG, PNG, atau PDF, maksimal 2 MB per berkas.
            </p>
        </div>

        @if (! $bolehDiubah)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                Pendaftaran sudah dikirim, jadi berkas tidak dapat diubah. Bila panitia meminta perbaikan, Anda dapat mengunggah ulang.
            </div>
        @endif

        @if ($jenis->isEmpty())
            <x-kosong judul="Belum ada jenis berkas" keterangan="Sekolah belum menetapkan berkas yang perlu diunggah." />
        @else
            <div class="space-y-3">
                @foreach ($jenis as $j)
                    <x-unggah-berkas
                        :jenis="$j"
                        :berkas="$berkas->get($j->id)"
                        :boleh-diubah="$bolehDiubah"
                        :pendaftaran-uuid="$pendaftaranUuid"
                        :rute-tampil="$ruteTampilNama ?? 'walisantri.berkas.tampil'"
                        :rute-hapus="$ruteHapusNama ?? 'walisantri.pendaftaran.berkas.hapus'"
                        :rute-simpan="$ruteSimpanNama ?? 'walisantri.pendaftaran.berkas.simpan'"
                    />
                @endforeach
            </div>
        @endif

        <div class="flex flex-wrap gap-2">
            @php
                $lanjut = route($ruteLangkah, ['pendaftaran' => $pendaftaranUuid, 'n' => 7]);
            @endphp

            @if ($bolehDiubah && $wajibTerisi >= $wajibTotal)
                <a href="{{ $lanjut }}"
                   class="inline-flex min-h-12 items-center justify-center rounded-xl bg-[var(--primer)] px-5 text-sm font-semibold text-white hover:bg-[var(--primer-gelap)]">
                    Lanjut ke biaya
                </a>
            @else
                <span class="inline-flex min-h-12 items-center justify-center rounded-xl bg-gray-200 px-5 text-sm font-semibold text-gray-500"
                      aria-disabled="true">
                    Lanjut ke biaya
                </span>
            @endif

            <a href="{{ route($ruteLangkah, ['pendaftaran' => $pendaftaranUuid, 'n' => 5]) }}"
               class="inline-flex min-h-12 items-center justify-center rounded-xl px-4 text-sm font-semibold text-[var(--primer)] hover:underline">
                Kembali
            </a>
        </div>

        @if ($wajibTerisi < $wajibTotal)
            <p class="px-1 text-xs text-[var(--teks-sekunder)]">
                Lengkapi semua berkas wajib untuk melanjutkan. Berkas bertanda <span class="font-bold text-[var(--bahaya)]">*</span> wajib diunggah.
            </p>
        @endif
    </div>
@endsection
