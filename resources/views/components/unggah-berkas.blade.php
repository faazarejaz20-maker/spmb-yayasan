@props([
    'jenis',
    'berkas' => null,
    'bolehDiubah' => false,
    'pendaftaranUuid',
    'ruteTampil' => 'walisantri.berkas.tampil',
    'ruteHapus' => 'walisantri.pendaftaran.berkas.hapus',
    'ruteSimpan' => 'walisantri.pendaftaran.berkas.simpan',
])

@php
    $status = $berkas?->status;
    $label = $status?->label() ?? 'Belum diunggah';
    $warna = match (true) {
        $status?->value === 'sesuai' => 'hijau',
        $status?->value === 'perlu_perbaikan' => 'oranye',
        $status?->value === 'menunggu' => 'biru',
        default => 'netral',
    };
@endphp

<div class="rounded-2xl border border-[var(--garis)] bg-white p-4 shadow-sm">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h3 class="text-sm font-bold text-[var(--teks)]">
                {{ $jenis->nama }}
                @if ($jenis->wajib)
                    <span class="text-[var(--bahaya)]" aria-label="wajib">*</span>
                @endif
            </h3>
            <p class="mt-1 text-xs text-[var(--teks-sekunder)]">
                {{ $jenis->jumlah_lembar }} lembar · JPG, PNG, atau PDF · maksimal 2 MB
            </p>
        </div>
        <x-lencana-status :label="$label" :warna="$warna" />
    </div>

    @if ($status?->value === 'perlu_perbaikan' && filled($berkas?->catatan))
        <div class="mt-3 rounded-xl border border-orange-200 bg-orange-50 p-3 text-sm text-orange-900">
            <p class="font-semibold">Catatan panitia</p>
            <p class="mt-1">{{ $berkas->catatan }}</p>
        </div>
    @endif

    @if ($berkas)
        <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
            <a href="{{ route($ruteTampil, $berkas->uuid) }}" target="_blank" rel="noopener"
               class="font-semibold text-[var(--primer)] hover:underline">
                Lihat berkas
            </a>

            @if ($bolehDiubah)
                <form method="POST" action="{{ route($ruteHapus, ['pendaftaran' => $pendaftaranUuid, 'berkas' => $berkas->uuid]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="font-semibold text-[var(--bahaya)] hover:underline">Hapus</button>
                </form>
            @endif
        </div>
    @endif

    @if ($bolehDiubah)
        <form method="POST" action="{{ route($ruteSimpan, $pendaftaranUuid) }}" enctype="multipart/form-data" class="mt-4">
            @csrf
            <input type="hidden" name="jenis_berkas_id" value="{{ $jenis->id }}">

            {{-- Input tersembunyi: label di bawah berperan sebagai tombol dan
                 membuka pemilih sistem (galeri/pengelola berkas di HP). --}}
            <input id="berkas-{{ $jenis->id }}" type="file" name="berkas"
                   accept="image/jpeg,image/png,application/pdf"
                   class="sr-only" data-unggah-otomatis>

            <label for="berkas-{{ $jenis->id }}" data-tombol-unggah
                   class="inline-flex min-h-12 cursor-pointer items-center justify-center gap-2 rounded-xl bg-[var(--primer)] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[var(--primer-gelap)]">
                {{ $berkas ? 'Ganti berkas' : 'Unggah berkas' }}
            </label>
        </form>

        @error('berkas')
            <p class="mt-2 text-sm text-[var(--bahaya)]">{{ $message }}</p>
        @enderror
    @endif
</div>
