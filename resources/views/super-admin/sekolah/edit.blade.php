@extends('layouts.panel')

@section('judul', 'Ubah Sekolah')

@section('isi')
    @php
        $maksSampulKb = (int) config('spmb.sampul.maks_kb');
        $maksSampulMb = number_format($maksSampulKb / 1024, 0, ',', '.');
    @endphp

    <div class="mx-auto max-w-2xl space-y-5">
        <x-kepala-halaman
            judul="Ubah data sekolah"
            :sekolah="$sekolah->nama.' ('.$sekolah->kode.')'"
            :kembali="route('super.sekolah.index')"
            labelKembali="Daftar sekolah"
            keterangan="Kode sekolah dan jenjang tidak diubah dari panel ini karena dipakai pada nomor peserta dan pengelompokan program."
        />

        <x-kartu>
            <form method="POST" action="{{ route('super.sekolah.simpan', $sekolah) }}" class="grid gap-3 sm:grid-cols-2">
                @csrf
                @method('PUT')

                <x-isian name="nama" label="Nama sekolah" wajib :nilai="$sekolah->nama" class="sm:col-span-2" />
                <x-isian name="singkatan" label="Singkatan" :nilai="$sekolah->singkatan" />
                <x-isian name="kontak" label="Nomor WhatsApp" :nilai="$sekolah->kontak" bantuan="08... atau 62..." />
                <x-isian name="instagram" label="Instagram" :nilai="$sekolah->instagram" bantuan="Tanpa @, misalnya sditinsanmuli.wsb" />
                <x-pilihan name="mode_penerimaan" label="Mode penerimaan" wajib :nilai="$sekolah->mode_penerimaan?->value" :opsi="['seleksi' => 'Seleksi', 'tanpa_seleksi' => 'Tanpa seleksi']" />
                <x-isian name="alamat" label="Alamat" tipe="teks_panjang" :nilai="$sekolah->alamat" class="sm:col-span-2" />

                <label class="flex items-center gap-2 text-sm text-[var(--teks)] sm:col-span-2">
                    <input type="checkbox" name="aktif" value="1" class="h-4 w-4 rounded border-[var(--garis)]" @checked($sekolah->aktif)>
                    Sekolah aktif dan menerima pendaftaran
                </label>

                <div class="flex flex-wrap gap-2 sm:col-span-2">
                    <x-tombol>Simpan perubahan</x-tombol>
                    <x-tautan-tombol :href="route('super.sekolah.index')" tipe="sekunder">Batal</x-tautan-tombol>
                </div>
            </form>
        </x-kartu>
        <x-kartu judul="Foto sampul beranda">
            <div class="space-y-4">
                <p class="text-sm text-[var(--teks-sekunder)]">
                    Foto ini tampil pada kartu {{ $sekolah->nama }} di beranda publik. Gunakan foto mendatar
                    (landscape) berukuran lebar, karena kartu memotongnya dengan rasio 16:10 dan mengambil bagian tengah.
                </p>

                <div class="relative aspect-[16/10] w-full overflow-hidden rounded-xl border border-[var(--garis)] bg-gray-100">
                    @if ($sampul)
                        <img
                            src="{{ $sampul['url'] }}"
                            alt="{{ $sampul['alt'] }}"
                            class="h-full w-full object-cover object-center"
                            loading="lazy"
                        >
                    @else
                        <div class="flex h-full items-center justify-center text-sm text-gray-500">Foto menyusul</div>
                    @endif
                </div>

                @unless ($adaSampulUnggahan)
                    <p class="text-xs text-[var(--teks-sekunder)]">
                        Saat ini memakai foto bawaan. Unggah foto untuk menggantinya.
                    </p>
                @endunless

                <form method="POST" action="{{ route('super.sekolah.foto.simpan', $sekolah) }}" enctype="multipart/form-data" class="space-y-2">
                    @csrf
                    @method('PUT')

                    <label for="foto_sampul" class="block text-sm font-semibold text-[var(--teks)]">
                        Unggah foto baru (JPG, PNG, atau WebP, maks. {{ $maksSampulMb }} MB)
                    </label>
                    <input
                        id="foto_sampul"
                        type="file"
                        name="foto_sampul"
                        accept="image/jpeg,image/png,image/webp"
                        required
                        class="pilih-berkas"
                        data-maks-berkas="{{ $maksSampulKb * 1024 }}"
                        data-galat-berkas="galat-foto-sampul"
                        @if ($errors->has('foto_sampul')) aria-invalid="true" @endif
                    >
                    <p id="galat-foto-sampul" class="text-sm text-[var(--bahaya)]" hidden></p>
                    @error('foto_sampul')
                        <p class="text-sm text-[var(--bahaya)]">{{ $message }}</p>
                    @enderror

                    <x-tombol>Simpan foto sampul</x-tombol>
                </form>

                @if ($adaSampulUnggahan)
                    <div class="border-t border-[var(--garis)] pt-3">
                        <x-modal-konfirmasi
                            judul="Hapus foto sampul?"
                            teks="Kartu sekolah di beranda kembali memakai foto bawaan."
                            :aksi="route('super.sekolah.foto.hapus', $sekolah)"
                            metode="DELETE"
                            labelTombol="Ya, hapus foto"
                        >
                            Hapus foto sampul
                        </x-modal-konfirmasi>
                    </div>
                @endif
            </div>
        </x-kartu>
    </div>
@endsection
