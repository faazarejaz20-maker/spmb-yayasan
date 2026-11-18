@extends('layouts.panel')

@section('judul', 'Pengaturan Sekolah')

@section('isi')
    @php
        $maksSampulKb = (int) config('spmb.sampul.maks_kb');
        $maksSampulMb = number_format($maksSampulKb / 1024, 0, ',', '.');
    @endphp

    <div class="mx-auto max-w-2xl space-y-5">
        <x-kepala-halaman
            judul="Pengaturan sekolah"
            :sekolah="$sekolah?->nama"
            keterangan="Mode penerimaan mengatur urutan daftar di panel panitia dan petunjuk yang tampil. Foto sampul mengatur kartu sekolah di beranda publik."
        />

        <x-kartu>
            <form method="POST" action="{{ route('admin.pengaturan.simpan') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <x-pilihan name="mode_penerimaan" label="Mode penerimaan" wajib :nilai="$sekolah?->mode_penerimaan?->value" :opsi="[
                    'seleksi' => 'Seleksi — urutan pendaftaran tidak menentukan hasil; panitia memilih hasil.',
                    'tanpa_seleksi' => 'Tanpa seleksi — pendaftar diterima berurutan sampai kuota penuh.',
                ]" />

                <div class="rounded-xl bg-[var(--latar)] p-4 text-sm text-[var(--teks-sekunder)]">
                    <p class="font-semibold text-[var(--teks)]">Dampak</p>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        <li><strong>Seleksi:</strong> panitia memutuskan Diterima, Ditolak, atau Cadangan. Daftar diurutkan dari yang terbaru.</li>
                        <li><strong>Tanpa seleksi:</strong> panitia tetap mencatat hasil, tetapi daftar diurutkan dari pendaftar paling awal dan kuota menjadi batas.</li>
                    </ul>
                </div>

                <x-tombol>Simpan mode penerimaan</x-tombol>
            </form>
        </x-kartu>

        <x-kartu judul="Foto sampul beranda">
            <div class="space-y-4">
                <p class="text-sm text-[var(--teks-sekunder)]">
                    Foto ini tampil pada kartu {{ $sekolah?->nama ?? 'sekolah' }} di beranda publik. Gunakan foto mendatar
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

                <form method="POST" action="{{ route('admin.pengaturan.foto.simpan') }}" enctype="multipart/form-data" class="space-y-2">
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
                            :aksi="route('admin.pengaturan.foto.hapus')"
                            metode="DELETE"
                            labelTombol="Ya, hapus foto"
                        >
                            Hapus foto sampul
                        </x-modal-konfirmasi>
                    </div>
                @endif
            </div>
        </x-kartu>

        <x-kartu judul="Pengaturan lain">
            <ul class="divide-y divide-[var(--garis)] text-sm">
                <li class="flex items-center justify-between gap-3 py-2">
                    <span class="text-[var(--teks)]">Gelombang, kuota, dan tautan grup</span>
                    <a href="{{ route('admin.gelombang.index') }}" class="font-semibold text-[var(--primer)] hover:underline">Buka</a>
                </li>
                <li class="flex items-center justify-between gap-3 py-2">
                    <span class="text-[var(--teks)]">Jenis berkas dan petunjuk unggah</span>
                    <a href="{{ route('admin.berkas.index') }}" class="font-semibold text-[var(--primer)] hover:underline">Buka</a>
                </li>
                <li class="flex items-center justify-between gap-3 py-2">
                    <span class="text-[var(--teks)]">Rincian biaya pendidikan</span>
                    <a href="{{ route('admin.biaya.index') }}" class="font-semibold text-[var(--primer)] hover:underline">Buka</a>
                </li>
                <li class="flex items-center justify-between gap-3 py-2">
                    <span class="text-[var(--teks)]">Rekening sekolah</span>
                    <a href="{{ route('admin.rekening.index') }}" class="font-semibold text-[var(--primer)] hover:underline">Buka</a>
                </li>
                <li class="flex items-center justify-between gap-3 py-2">
                    <span class="text-[var(--teks)]">Isian formulir</span>
                    <a href="{{ route('admin.isian.index') }}" class="font-semibold text-[var(--primer)] hover:underline">Buka</a>
                </li>
                <li class="flex items-center justify-between gap-3 py-2">
                    <span class="text-[var(--teks)]">Kelola konten halaman sekolah</span>
                    <a href="{{ route('admin.konten.index') }}" class="font-semibold text-[var(--primer)] hover:underline">Buka</a>
                </li>
            </ul>
        </x-kartu>
    </div>
@endsection
