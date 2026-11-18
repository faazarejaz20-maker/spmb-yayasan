@php
    $tampil = function ($nilai, array $definisi = []) {
        if ($nilai instanceof \DateTimeInterface) {
            return $nilai->format('d-m-Y');
        }

        if (is_bool($nilai)) {
            return $nilai ? 'Ya' : 'Tidak';
        }

        if ($nilai === null || $nilai === '') {
            return '—';
        }

        $pilihan = $definisi['pilihan'] ?? [];

        if (isset($pilihan[$nilai])) {
            return $pilihan[$nilai];
        }

        if (isset($pilihan[(string) $nilai])) {
            return $pilihan[(string) $nilai];
        }

        return (string) $nilai;
    };
@endphp

<div class="space-y-4">
    @foreach ($bagian as $kunciBagian => $dataBagian)
        <x-kartu :judul="$dataBagian['judul']">
            <dl class="divide-y divide-[var(--garis)]">
                @foreach ($dataBagian['isian'] as $kunci => $definisi)
                    <div class="flex items-start justify-between gap-3 py-1.5 text-sm">
                        <dt class="text-[var(--teks-sekunder)]">{{ $definisi['label'] ?? $kunci }}</dt>
                        <dd class="max-w-[60%] text-right font-medium text-[var(--teks)]">
                            {{ $tampil($dataBagian['nilai'][$kunci] ?? null, $definisi) }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </x-kartu>
    @endforeach

    <x-kartu judul="Orang tua / wali">
        <div class="divide-y divide-[var(--garis)]">
            @foreach (\App\Enums\HubunganOrangTua::cases() as $hubungan)
                @php
                    $baris = $orangTua->get($hubungan->value);
                @endphp

                <div class="py-2 text-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[var(--teks-sekunder)]">{{ $hubungan->label() }}</p>

                    @if ($baris)
                        <div class="mt-1 grid gap-1 sm:grid-cols-2">
                            <p class="text-[var(--teks)]"><span class="text-[var(--teks-sekunder)]">Nama:</span> {{ $baris->nama }}</p>
                            <p class="text-[var(--teks)]"><span class="text-[var(--teks-sekunder)]">NIK:</span> {{ $baris->nik ?: '—' }}</p>
                            <p class="text-[var(--teks)]"><span class="text-[var(--teks-sekunder)]">HP:</span> {{ $baris->no_hp ?: '—' }}</p>
                            <p class="text-[var(--teks)]"><span class="text-[var(--teks-sekunder)]">Pekerjaan:</span> {{ $baris->pekerjaan ?: '—' }}</p>
                        </div>
                    @else
                        <p class="mt-1 text-[var(--teks-sekunder)]">Belum diisi.</p>
                    @endif
                </div>
            @endforeach
        </div>
    </x-kartu>

    <details class="rounded-2xl border border-[var(--garis)] bg-white p-5">
        <summary class="cursor-pointer text-sm font-bold text-[var(--primer)]">Ubah data pendaftar</summary>

        <form method="POST" action="{{ route('panitia.pendaftar.data', $pendaftaran->uuid) }}" class="mt-4 space-y-5" novalidate>
            @csrf
            @method('PUT')

            @error('data')
                <p class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p>
            @enderror

            @foreach ($bagian as $kunciBagian => $dataBagian)
                <fieldset class="space-y-3">
                    <legend class="text-sm font-bold text-[var(--teks)]">{{ $dataBagian['judul'] }}</legend>

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($dataBagian['isian'] as $kunci => $definisi)
                            @php
                                $label = $definisi['label'] ?? ucwords(str_replace('_', ' ', $kunci));
                                $wajib = (bool) ($definisi['wajib'] ?? false);
                                $tipe = $definisi['tipe'] ?? 'teks';
                                $nilaiField = $dataBagian['nilai'][$kunci] ?? null;
                            @endphp

                            @if ($tipe === 'pilihan')
                                <x-pilihan :name="$kunci" :label="$label" :opsi="$definisi['pilihan'] ?? []" :nilai="$nilaiField" :wajib="$wajib" />
                            @elseif ($tipe === 'tanggal')
                                <x-tanggal :name="$kunci" :label="$label" :nilai="$nilaiField" :wajib="$wajib" />
                            @elseif ($tipe === 'angka')
                                <x-isian :name="$kunci" :label="$label" tipe="angka" inputmode="numeric" :nilai="$nilaiField" :wajib="$wajib" />
                            @elseif ($tipe === 'teks_panjang')
                                <x-isian :name="$kunci" :label="$label" tipe="teks_panjang" :nilai="$nilaiField" :wajib="$wajib" class="sm:col-span-2" />
                            @elseif ($kunci === 'nik' || $kunci === 'no_kk')
                                <x-isian :name="$kunci" :label="$label" inputmode="numeric" maxlength="16" :nilai="$nilaiField" :wajib="$wajib" />
                            @elseif ($kunci === 'nisn')
                                <x-isian :name="$kunci" :label="$label" inputmode="numeric" maxlength="10" :nilai="$nilaiField" :wajib="$wajib" />
                            @else
                                <x-isian :name="$kunci" :label="$label" :nilai="$nilaiField" :wajib="$wajib" />
                            @endif
                        @endforeach
                    </div>
                </fieldset>
            @endforeach

            <fieldset class="space-y-3">
                <legend class="text-sm font-bold text-[var(--teks)]">Orang tua / wali</legend>

                @foreach (\App\Enums\HubunganOrangTua::cases() as $hubungan)
                    @php
                        $baris = $orangTua->get($hubungan->value);
                        $awalan = $hubungan->value;
                    @endphp

                    <div class="rounded-xl border border-[var(--garis)] p-3">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[var(--teks-sekunder)]">{{ $hubungan->label() }}</p>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-isian :name="$awalan.'[nama]'" label="Nama" :nilai="$baris?->nama" />
                            <x-isian :name="$awalan.'[nik]'" label="NIK" inputmode="numeric" maxlength="16" :nilai="$baris?->nik" />
                            <x-isian :name="$awalan.'[tempat_lahir]'" label="Tempat lahir" :nilai="$baris?->tempat_lahir" />
                            <x-tanggal :name="$awalan.'[tanggal_lahir]'" label="Tanggal lahir" :nilai="$baris?->tanggal_lahir" />
                            <x-pilihan :name="$awalan.'[status_hidup]'" label="Status" :opsi="array_combine(\App\Http\Requests\OrangTuaRequest::STATUS_HIDUP, \App\Http\Requests\OrangTuaRequest::STATUS_HIDUP)" :nilai="$baris?->status_hidup" />
                            <x-isian :name="$awalan.'[pendidikan]'" label="Pendidikan" :nilai="$baris?->pendidikan" />
                            <x-isian :name="$awalan.'[pekerjaan]'" label="Pekerjaan" :nilai="$baris?->pekerjaan" />
                            <x-isian :name="$awalan.'[penghasilan]'" label="Penghasilan" :nilai="$baris?->penghasilan" />
                            <x-isian :name="$awalan.'[no_hp]'" label="Nomor HP" inputmode="tel" :nilai="$baris?->no_hp" />
                            <x-isian :name="$awalan.'[alamat]'" label="Alamat" :nilai="$baris?->alamat" />
                        </div>
                    </div>
                @endforeach
            </fieldset>

            <x-tombol>Simpan perubahan data</x-tombol>
        </form>
    </details>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-kartu judul="Catatan internal panitia">
            <p class="text-xs text-[var(--teks-sekunder)]">
                Hanya terlihat panitia dan admin sekolah. Tidak pernah tampil di akun walisantri.
            </p>

            <form method="POST" action="{{ route('panitia.pendaftar.catatan', $pendaftaran->uuid) }}" class="mt-3 space-y-2">
                @csrf
                @method('PUT')

                <textarea name="catatan_panitia" rows="4" maxlength="2000"
                          class="w-full rounded-xl border border-[var(--garis)] bg-white px-3 py-2 text-sm"
                          placeholder="Mis. berkas susulan, hasil wawancara, atau hal yang perlu diingat.">{{ old('catatan_panitia', $pendaftaran->catatan_panitia) }}</textarea>

                @error('catatan_panitia')
                    <p class="text-sm text-[var(--bahaya)]">{{ $message }}</p>
                @enderror

                <x-tombol tipe="sekunder" ukuran="kecil">Simpan catatan</x-tombol>
            </form>
        </x-kartu>

        <x-kartu judul="Akun walisantri">
            <p class="text-xs text-[var(--teks-sekunder)]">
                Kata sandi sementara hanya ditampilkan sekali. Sesi walisantri yang sedang aktif akan diakhiri.
            </p>

            <div class="mt-3">
                <x-modal-konfirmasi
                    :aksi="route('panitia.pendaftar.atur-ulang-password', $pendaftaran->uuid)"
                    metode="POST"
                    warna="primer"
                    judul="Atur ulang kata sandi?"
                    teks="Kata sandi sementara akan dibuat dan ditampilkan satu kali. Sampaikan langsung kepada walisantri."
                    labelTombol="Ya, buat kata sandi"
                >
                    Atur ulang kata sandi walisantri
                </x-modal-konfirmasi>
            </div>

            <form method="POST" action="{{ route('panitia.pendaftar.pindah-anak', $pendaftaran->uuid) }}" class="mt-5 space-y-2 border-t border-[var(--garis)] pt-4">
                @csrf

                <label for="no_hp" class="block text-sm font-semibold text-[var(--teks)]">Pindahkan anak ke akun lain</label>
                <p class="text-xs text-[var(--teks-sekunder)]">
                    Masukkan nomor HP akun tujuan. Hanya akun yang berada dalam kewenangan sekolah Anda yang dapat dipilih.
                </p>

                <input type="text" id="no_hp" name="no_hp" value="{{ old('no_hp') }}" inputmode="tel" maxlength="20"
                       placeholder="08xxxxxxxxxx"
                       class="min-h-11 w-full rounded-xl border border-[var(--garis)] bg-white px-3 text-sm">

                @error('no_hp')
                    <p class="text-sm text-[var(--bahaya)]">{{ $message }}</p>
                @enderror

                <x-tombol tipe="sekunder" ukuran="kecil">Pindahkan</x-tombol>
            </form>
        </x-kartu>
    </div>
</div>
