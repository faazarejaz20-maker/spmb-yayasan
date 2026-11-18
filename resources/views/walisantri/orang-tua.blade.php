@extends($layout ?? 'layouts.walisantri')

@section('judul', 'Data Orang Tua')

@section('isi')
    @php
        $lamaSemua = old();
        $aksiSimpan = $aksiSimpan ?? route('walisantri.orang-tua.simpan');
        $tautanKembali = $tautanKembali ?? route('walisantri.beranda');
        $labelKembali = $labelKembali ?? 'Kembali';
    @endphp

    <div class="space-y-4">
        @if (! empty($banner))
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">
                {{ $banner }}
            </div>
        @endif

        <div class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-[var(--garis)]">
            <div class="pola-hero bg-[var(--primer)] px-5 py-6 text-white">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-white/75">Sekali isi, untuk semua anak</p>
                <h1 class="mt-1 text-xl font-bold">Data orang tua / wali</h1>
                <p class="mt-2 max-w-md text-sm leading-relaxed text-white/85">
                    Data ini dipakai ulang untuk semua anak di akun Anda. Isi minimal ayah atau ibu: nama dan nomor HP.
                </p>
            </div>
        </div>

        <form method="POST" action="{{ $aksiSimpan }}" class="space-y-4" novalidate>
            @csrf
            @method('PUT')

            @foreach ($hubungan as $h)
                @php
                    $baris = $data->get($h->value);
                    $lama = $lamaSemua[$h->value] ?? [];
                    $lama = is_array($lama) ? $lama : [];

                    $nilai = fn (string $kunci, $bawaan = null) => $lama[$kunci] ?? $baris?->getAttribute($kunci) ?? $bawaan;
                    $ikon = match ($h) {
                        \App\Enums\HubunganOrangTua::Ayah => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
                        \App\Enums\HubunganOrangTua::Ibu => 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z',
                        default => 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z',
                    };
                @endphp

                <section class="rounded-2xl border border-[var(--garis)] bg-white p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[var(--primer)]/10 text-[var(--primer)]">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $ikon }}" />
                            </svg>
                        </span>

                        <div class="min-w-0">
                            <h2 class="text-base font-bold text-[var(--teks)]">{{ $h->label() }}</h2>
                            <p class="mt-1 text-xs leading-relaxed text-[var(--teks-sekunder)]">
                                @if ($h === \App\Enums\HubunganOrangTua::Wali)
                                    Isi hanya bila anak tinggal bersama wali, bukan ayah atau ibu.
                                @else
                                    Wajib diisi untuk salah satu dari ayah atau ibu.
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        <x-isian :name="$h->value.'[nama]'" label="Nama lengkap" :nilai="$nilai('nama')" />
                        <x-isian :name="$h->value.'[nik]'" label="NIK" inputmode="numeric" maxlength="16" :nilai="$nilai('nik')" bantuan="16 angka, sesuai KTP." />
                        <x-isian :name="$h->value.'[tempat_lahir]'" label="Tempat lahir" :nilai="$nilai('tempat_lahir')" />
                        <x-tanggal :name="$h->value.'[tanggal_lahir]'" label="Tanggal lahir" :nilai="$nilai('tanggal_lahir')" />
                        <x-pilihan :name="$h->value.'[status_hidup]'" label="Status" :opsi="array_combine($statusHidup, $statusHidup)" :nilai="$nilai('status_hidup', 'Masih hidup')" />
                        <x-isian :name="$h->value.'[pendidikan]'" label="Pendidikan terakhir" :nilai="$nilai('pendidikan')" />
                        <x-isian :name="$h->value.'[pekerjaan]'" label="Pekerjaan" :nilai="$nilai('pekerjaan')" />
                        <x-isian :name="$h->value.'[penghasilan]'" label="Penghasilan per bulan" :nilai="$nilai('penghasilan')" bantuan="Contoh: Rp2.000.000. Boleh dikosongkan." />
                        <x-isian :name="$h->value.'[no_hp]'" label="Nomor HP" inputmode="tel" :nilai="$nilai('no_hp')" bantuan="Contoh: 0812xxxxxxx." />
                        <x-isian :name="$h->value.'[alamat]'" label="Alamat" tipe="teks_panjang" :nilai="$nilai('alamat')" />
                    </div>
                </section>
            @endforeach

            <div class="flex flex-wrap gap-2">
                <x-tombol>Simpan data orang tua</x-tombol>
                <a href="{{ $tautanKembali }}"
                   class="inline-flex min-h-12 items-center justify-center rounded-xl border border-[var(--garis)] bg-white px-4 text-sm font-semibold text-[var(--teks)] hover:bg-gray-50">
                    {{ $labelKembali }}
                </a>
            </div>
        </form>
    </div>
@endsection
