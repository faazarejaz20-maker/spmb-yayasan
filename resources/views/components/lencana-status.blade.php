@props(['status' => null, 'label' => null, 'warna' => null])

@php
    $label = $label ?? (is_string($status) ? $status : '');

    if ($warna === null) {
        $kunci = strtolower((string) $label);

        $warna = match (true) {
            str_contains($kunci, 'draf') => 'netral',
            str_contains($kunci, 'perlu perbaikan') => 'oranye',
            str_contains($kunci, 'baru'), str_contains($kunci, 'menunggu verifikasi') => 'biru',
            str_contains($kunci, 'diterima'), str_contains($kunci, 'sesuai'), str_contains($kunci, 'disetujui'),
            str_contains($kunci, 'resmi'), str_contains($kunci, 'lunas'), str_contains($kunci, 'valid') => 'hijau',
            str_contains($kunci, 'ditolak') => 'merah',
            str_contains($kunci, 'cadangan') => 'kuning',
            str_contains($kunci, 'menunggu') => 'kuning',
            default => 'netral',
        };
    }

    $kelas = match ($warna) {
        'biru' => 'bg-blue-100 text-blue-800',
        'oranye' => 'bg-orange-100 text-orange-800',
        'kuning' => 'bg-amber-100 text-amber-900',
        'hijau' => 'bg-green-100 text-green-800',
        'merah' => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-700',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold '.$kelas]) }}>
    {{ $label }}
</span>
