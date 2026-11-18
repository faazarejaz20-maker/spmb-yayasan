@props([
    'name',
    'label' => null,
    'nilai' => null,
    'tipe' => 'text',
    'wajib' => false,
    'bantuan' => null,
    'id' => null,
    'abaikanLama' => false,
    'tampilkanGalat' => true,
])

@php
    $label = $label ?? ucwords(str_replace('_', ' ', $name));
    $idIsian = $id ?? $name;
    // Beberapa formulir bisa berbagi nama isian yang sama (misalnya banyak
    // baris konten). Pemanggil menandai formulir aktif agar isian lama dan
    // pesan galat tidak bocor ke formulir lain.
    $lama = $abaikanLama ? $nilai : old($name, $nilai);
    $tipe = $attributes->get('type', $tipe);
    $panjang = in_array($tipe, ['teks_panjang', 'textarea'], true);
    $jenis = match ($tipe) {
        'teks_panjang', 'textarea' => 'textarea',
        'angka' => 'number',
        'tanggal' => 'date',
        default => $tipe,
    };
    $kelas = 'min-h-12 w-full rounded-xl border border-[var(--garis)] bg-white px-4 py-2.5 text-[var(--teks)] placeholder:text-[var(--teks-sekunder)] focus:border-[var(--primer)] focus:outline-none focus:ring-2 focus:ring-[var(--primer)]/30';
@endphp

<div class="{{ trim('w-full '.$attributes->get('class', '')) }}">
    <label for="{{ $idIsian }}" class="mb-1 block text-sm font-semibold text-[var(--teks)]">
        {{ $label }}
        @if ($wajib)
            <span class="text-[var(--bahaya)]" aria-hidden="true">*</span>
        @endif
    </label>

    @if ($panjang)
        <textarea
            name="{{ $name }}"
            id="{{ $idIsian }}"
            rows="4"
            @if ($tampilkanGalat && $errors->has($name)) aria-invalid="true" @endif
            {{ $attributes->except(['class', 'name', 'id', 'type', 'value', 'label', 'nilai', 'wajib', 'bantuan', 'tipe', 'abaikanLama', 'tampilkanGalat']) }}
            class="{{ $kelas }}"
        >{{ $lama }}</textarea>
    @else
        <input
            type="{{ $jenis }}"
            name="{{ $name }}"
            id="{{ $idIsian }}"
            value="{{ $jenis !== 'password' ? $lama : '' }}"
            @if ($jenis === 'number') inputmode="numeric" @endif
            @if ($tampilkanGalat && $errors->has($name)) aria-invalid="true" @endif
            {{ $attributes->except(['class', 'name', 'id', 'type', 'value', 'label', 'nilai', 'wajib', 'bantuan', 'tipe', 'abaikanLama', 'tampilkanGalat']) }}
            class="{{ $kelas }}"
        >
    @endif

    @if ($bantuan)
        <p class="mt-1 text-xs text-[var(--teks-sekunder)]">{{ $bantuan }}</p>
    @endif

    @if ($tampilkanGalat)
        @error($name)
            <p class="mt-1 text-sm text-[var(--bahaya)]">{{ $message }}</p>
        @enderror
    @endif
</div>
