@php
    $pembacaGambar = app(\App\Support\GambarPublik::class);
    $maksKontenKb = (int) config('spmb.konten.maks_kb');
    $maksKontenMb = number_format($maksKontenKb / 1024, 0, ',', '.');
@endphp

<div class="space-y-4">
    @foreach ($bagian as $seksi)
        @php
            $kunciBagian = $seksi['bagian'];
            $meta = $seksi['meta'];
            $barisBagian = $seksi['baris'];
            $banyak = (bool) ($meta['banyak'] ?? false);
            $adaGambar = (bool) ($meta['gambar'] ?? false);
            $labelBagian = $meta['label'] ?? $kunciBagian;
        @endphp

        <x-kartu>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-base font-bold text-[var(--teks)]">{{ $labelBagian }}</h2>
                <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700">
                    {{ $banyak ? 'Daftar' : 'Satu teks' }}{{ $adaGambar ? ' · gambar' : '' }}
                </span>
            </div>

            @if (! $banyak)
                @php
                    $formTeks = 'teks-'.$kunciBagian;
                    $teksAktif = old('_form') === $formTeks;
                    $teksLama = $teksAktif ? old('isi') : $barisBagian->first()?->isi;
                @endphp

                <form method="POST" action="{{ route($rute['teks']) }}" class="space-y-3">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_form" value="{{ $formTeks }}">
                    <input type="hidden" name="bagian" value="{{ $kunciBagian }}">

                    <label for="isi-{{ $kunciBagian }}" class="mb-1 block text-sm font-semibold text-[var(--teks)]">Isi</label>
                    <textarea
                        id="isi-{{ $kunciBagian }}"
                        name="isi"
                        rows="{{ ($meta['panjang'] ?? false) ? 6 : 2 }}"
                        maxlength="10000"
                        @if ($teksAktif && $errors->has('isi')) aria-invalid="true" @endif
                        class="w-full rounded-xl border border-[var(--garis)] bg-white px-4 py-2.5 text-sm text-[var(--teks)] focus:border-[var(--primer)] focus:outline-none focus:ring-2 focus:ring-[var(--primer)]/30"
                    >{{ $teksLama }}</textarea>

                    @if ($meta['tautan'] ?? false)
                        <p class="text-xs text-[var(--teks-sekunder)]">Harus diawali <code>https://</code>. Contoh: https://www.instagram.com/namasekolah</p>
                    @endif

                    @if ($teksAktif)
                        @error('isi')
                            <p class="text-sm text-[var(--bahaya)]">{{ $message }}</p>
                        @enderror
                    @endif

                    <x-tombol>Simpan teks</x-tombol>
                </form>
            @else
                @if ($barisBagian->isNotEmpty())
                    <div class="space-y-3">
                        @foreach ($barisBagian as $baris)
                            @php
                                $formBaris = 'baris-'.$baris->id;
                                $barisAktif = old('_form') === $formBaris;
                            @endphp

                            <div class="rounded-xl border border-[var(--garis)] p-3">
                                <form method="POST" action="{{ route($rute['ubah'], $baris) }}" enctype="multipart/form-data" class="grid gap-3 sm:grid-cols-2">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="_form" value="{{ $formBaris }}">
                                    <input type="hidden" name="bagian" value="{{ $kunciBagian }}">

                                    <x-isian name="judul" :id="'judul-'.$baris->id" label="Judul / teks alternatif gambar" :nilai="$baris->judul"
                                             :abaikan-lama="! $barisAktif" :tampilkan-galat="$barisAktif" />
                                    <x-isian name="urutan" :id="'urutan-'.$baris->id" label="Urutan" tipe="angka" :nilai="$baris->urutan"
                                             :abaikan-lama="! $barisAktif" :tampilkan-galat="$barisAktif" />
                                    <div class="sm:col-span-2">
                                        <x-isian name="isi" :id="'isi-'.$baris->id" label="Isi" tipe="teks_panjang" :nilai="$baris->isi"
                                                 :abaikan-lama="! $barisAktif" :tampilkan-galat="$barisAktif" />
                                    </div>

                                    @if ($adaGambar)
                                        <div class="sm:col-span-2">
                                            @php
                                                $gambarBaris = $pembacaGambar->dariKonten(
                                                    $baris->gambar_path,
                                                    $baris->judul ?? $labelBagian,
                                                );
                                            @endphp
                                            @if ($gambarBaris)
                                                <img
                                                    src="{{ $gambarBaris['url'] }}"
                                                    alt="{{ $gambarBaris['alt'] }}"
                                                    class="h-28 w-auto rounded-lg border border-[var(--garis)] object-cover"
                                                    loading="lazy"
                                                >
                                                <label class="mt-2 flex items-center gap-2 text-sm text-[var(--bahaya)]">
                                                    <input type="checkbox" name="hapus_gambar" value="1" @checked($barisAktif && old('hapus_gambar')) class="h-4 w-4 rounded border-[var(--garis)]">
                                                    Hapus gambar ini
                                                </label>
                                            @else
                                                <p class="text-xs text-[var(--teks-sekunder)]">Belum ada gambar.</p>
                                            @endif

                                            <label for="gambar-{{ $baris->id }}" class="mt-2 block text-sm font-semibold text-[var(--teks)]">Ganti gambar (JPG, PNG, atau WebP, maks. {{ $maksKontenMb }} MB)</label>
                                            <input id="gambar-{{ $baris->id }}" type="file" name="gambar" accept="image/jpeg,image/png,image/webp"
                                                   class="pilih-berkas mt-1"
                                                   data-maks-berkas="{{ $maksKontenKb * 1024 }}" data-galat-berkas="galat-gambar-{{ $baris->id }}"
                                                   @if ($barisAktif && $errors->has('gambar')) aria-invalid="true" @endif>
                                            <p id="galat-gambar-{{ $baris->id }}" class="mt-1 text-sm text-[var(--bahaya)]" hidden></p>

                                            @if ($barisAktif)
                                                @error('gambar')
                                                    <p class="mt-1 text-sm text-[var(--bahaya)]">{{ $message }}</p>
                                                @enderror
                                            @endif
                                        </div>
                                    @endif

                                    <div class="flex flex-wrap items-center gap-2 sm:col-span-2">
                                        <x-tombol>Simpan baris</x-tombol>
                                    </div>
                                </form>

                                <div class="mt-2">
                                    <x-modal-konfirmasi
                                        :aksi="route($rute['hapus'], $baris)"
                                        metode="DELETE"
                                        judul="Hapus baris konten?"
                                        teks="Baris ini akan dihapus beserta gambarnya. Tindakan ini tidak dapat dibatalkan."
                                        labelTombol="Ya, hapus"
                                    >
                                        Hapus baris
                                    </x-modal-konfirmasi>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-kosong judul="Belum ada baris" keterangan="Tambahkan baris pertama untuk bagian ini." />
                @endif

                <form method="POST" action="{{ route($rute['tambah']) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 rounded-xl bg-[var(--latar)] p-3 sm:grid-cols-2">
                    @csrf
                    @php
                        $formTambah = 'tambah-'.$kunciBagian;
                        $tambahAktif = old('_form') === $formTambah;
                    @endphp
                    <input type="hidden" name="_form" value="{{ $formTambah }}">
                    <input type="hidden" name="bagian" value="{{ $kunciBagian }}">

                    <p class="text-sm font-semibold text-[var(--teks)] sm:col-span-2">Tambah baris {{ strtolower($labelBagian) }}</p>

                    <x-isian name="judul" id="judul-baru-{{ $kunciBagian }}" label="Judul / teks alternatif gambar"
                             :abaikan-lama="! $tambahAktif" :tampilkan-galat="$tambahAktif" />
                    <x-isian name="urutan" id="urutan-baru-{{ $kunciBagian }}" label="Urutan" tipe="angka"
                             :abaikan-lama="! $tambahAktif" :tampilkan-galat="$tambahAktif" />
                    <div class="sm:col-span-2">
                        <x-isian name="isi" id="isi-baru-{{ $kunciBagian }}" label="Isi" tipe="teks_panjang"
                                 :abaikan-lama="! $tambahAktif" :tampilkan-galat="$tambahAktif" />
                    </div>

                    @if ($adaGambar)
                        <div class="sm:col-span-2">
                            <label for="gambar-baru-{{ $kunciBagian }}" class="mb-1 block text-sm font-semibold text-[var(--teks)]">Gambar (JPG, PNG, atau WebP, maks. {{ $maksKontenMb }} MB)</label>
                            <input id="gambar-baru-{{ $kunciBagian }}" type="file" name="gambar" accept="image/jpeg,image/png,image/webp"
                                   class="pilih-berkas"
                                   data-maks-berkas="{{ $maksKontenKb * 1024 }}" data-galat-berkas="galat-gambar-baru-{{ $kunciBagian }}"
                                   @if ($tambahAktif && $errors->has('gambar')) aria-invalid="true" @endif>
                            <p id="galat-gambar-baru-{{ $kunciBagian }}" class="mt-1 text-sm text-[var(--bahaya)]" hidden></p>

                            @if ($tambahAktif)
                                @error('gambar')
                                    <p class="mt-1 text-sm text-[var(--bahaya)]">{{ $message }}</p>
                                @enderror
                            @endif
                        </div>
                    @endif

                    <div class="sm:col-span-2">
                        <x-tombol tipe="sekunder">Tambah baris</x-tombol>
                    </div>
                </form>
            @endif
        </x-kartu>
    @endforeach
</div>
