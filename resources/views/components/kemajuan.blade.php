@props(['langkah' => [], 'aktif' => null])

@php
    $total = count($langkah);
    $selesai = 0;
    foreach ($langkah as $baris) {
        if (! empty($baris['selesai'])) {
            $selesai++;
        }
    }
    $labelAktif = $aktif !== null && isset($langkah[$aktif]) ? $langkah[$aktif]['label'] : null;
@endphp

@if ($total > 0)
    <div>
        <div class="flex items-center justify-between gap-3 text-xs">
            <span class="font-semibold text-[var(--teks-sekunder)]">Kemajuan pendaftaran</span>
            <span class="font-bold text-[var(--primer)]">{{ $selesai }}/{{ $total }}</span>
        </div>

        <div class="mt-2 flex gap-1" role="img" aria-label="Kemajuan {{ $selesai }} dari {{ $total }} tahap">
            @foreach ($langkah as $baris)
                <span @class([
                    'h-1.5 flex-1 rounded-full',
                    'bg-[var(--primer)]' => ! empty($baris['selesai']),
                    'bg-[var(--aksen)]' => empty($baris['selesai']) && ! empty($baris['aktif']),
                    'bg-gray-200' => empty($baris['selesai']) && empty($baris['aktif']),
                ])></span>
            @endforeach
        </div>

        <p class="mt-2 text-xs text-[var(--teks-sekunder)]">
            @if ($labelAktif === null)
                Semua tahap selesai.
            @else
                Tahap berikutnya: <span class="font-semibold text-[var(--teks)]">{{ $labelAktif }}</span>
            @endif
        </p>
    </div>
@endif
