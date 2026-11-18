@props(['name', 'label' => null, 'opsi' => [], 'nilai' => null, 'wajib' => false, 'bantuan' => null, 'placeholder' => 'Pilih'])

@php
    $label = $label ?? ucwords(str_replace('_', ' ', $name));
    $lama = (string) old($name, $nilai);
@endphp

<div class="{{ trim('w-full '.$attributes->get('class', '')) }}">
    <label for="{{ $name }}" class="mb-1 block text-sm font-semibold text-[var(--teks)]">
        {{ $label }}
        @if ($wajib)
            <span class="text-[var(--bahaya)]" aria-hidden="true">*</span>
        @endif
    </label>

    <select
        name="{{ $name }}"
        id="{{ $name }}"
        class="min-h-12 w-full rounded-xl border border-[var(--garis)] bg-white px-4 py-2.5 text-[var(--teks)] focus:border-[var(--primer)] focus:outline-none focus:ring-2 focus:ring-[var(--primer)]/30"
    >
        <option value="">{{ $placeholder }}</option>
        @foreach ($opsi as $kunci => $teks)
            <option value="{{ $kunci }}" @selected($lama === (string) $kunci)>{{ $teks }}</option>
        @endforeach
    </select>

    @if ($bantuan)
        <p class="mt-1 text-xs text-[var(--teks-sekunder)]">{{ $bantuan }}</p>
    @endif

    @error($name)
        <p class="mt-1 text-sm text-[var(--bahaya)]">{{ $message }}</p>
    @enderror
</div>
