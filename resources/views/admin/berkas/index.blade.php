@extends('layouts.panel')

@section('judul', 'Jenis Berkas')

@section('isi')
    <div class="space-y-5">
        <x-kepala-halaman
            judul="Jenis berkas"
            :sekolah="$sekolah?->nama"
            keterangan="Jenis berkas yang sudah punya unggahan tidak dapat dihapus; nonaktifkan saja."
        />

        <x-kartu judul="Tambah jenis berkas">
            <form method="POST" action="{{ route('admin.berkas.simpan') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @csrf
                <x-isian name="nama" label="Nama berkas" wajib />
                <x-isian name="jumlah_lembar" label="Jumlah lembar" tipe="angka" :nilai="1" wajib />
                <x-isian name="urutan" label="Urutan" tipe="angka" />
                <x-isian name="petunjuk" label="Petunjuk unggah" tipe="teks_panjang" class="sm:col-span-2 lg:col-span-3" />

                <label class="flex items-center gap-2 text-sm text-[var(--teks)]">
                    <input type="checkbox" name="wajib" value="1" class="h-4 w-4 rounded border-[var(--garis)]" checked>
                    Wajib diunggah
                </label>
                <label class="flex items-center gap-2 text-sm text-[var(--teks)]">
                    <input type="checkbox" name="aktif" value="1" class="h-4 w-4 rounded border-[var(--garis)]" checked>
                    Aktif
                </label>

                <div class="sm:col-span-2 lg:col-span-3">
                    <x-tombol>Tambah jenis berkas</x-tombol>
                </div>
            </form>
        </x-kartu>

        @error('berkas')
            <p class="text-sm text-[var(--bahaya)]">{{ $message }}</p>
        @enderror

        @if ($daftar->isEmpty())
            <x-kosong judul="Belum ada jenis berkas" keterangan="Tambahkan jenis berkas yang harus diunggah walisantri." />
        @else
            <div class="space-y-4">
                @foreach ($daftar as $jenis)
                    <x-kartu>
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="font-bold text-[var(--teks)]">{{ $jenis->nama }}</p>
                                <p class="text-xs text-[var(--teks-sekunder)]">
                                    {{ $jenis->wajib ? 'Wajib' : 'Opsional' }} &middot; {{ $jenis->jumlah_lembar }} lembar &middot;
                                    {{ $jenis->aktif ? 'Aktif' : 'Nonaktif' }}
                                </p>
                            </div>

                            <x-modal-konfirmasi
                                :aksi="route('admin.berkas.hapus', $jenis)"
                                metode="DELETE"
                                judul="Hapus jenis berkas?"
                                teks="Jenis berkas yang sudah punya unggahan tidak dapat dihapus. Tindakan ini tidak dapat dibatalkan."
                                labelTombol="Ya, hapus"
                            >
                                Hapus
                            </x-modal-konfirmasi>
                        </div>

                        <form method="POST" action="{{ route('admin.berkas.ubah', $jenis) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @csrf
                            @method('PUT')
                            <x-isian name="nama" label="Nama berkas" wajib :nilai="$jenis->nama" />
                            <x-isian name="jumlah_lembar" label="Jumlah lembar" tipe="angka" :nilai="$jenis->jumlah_lembar" wajib />
                            <x-isian name="urutan" label="Urutan" tipe="angka" :nilai="$jenis->urutan" />
                            <x-isian name="petunjuk" label="Petunjuk unggah" tipe="teks_panjang" :nilai="$jenis->petunjuk" class="sm:col-span-2 lg:col-span-3" />

                            <label class="flex items-center gap-2 text-sm text-[var(--teks)]">
                                <input type="checkbox" name="wajib" value="1" class="h-4 w-4 rounded border-[var(--garis)]" @checked($jenis->wajib)>
                                Wajib diunggah
                            </label>
                            <label class="flex items-center gap-2 text-sm text-[var(--teks)]">
                                <input type="checkbox" name="aktif" value="1" class="h-4 w-4 rounded border-[var(--garis)]" @checked($jenis->aktif)>
                                Aktif
                            </label>

                            <div class="sm:col-span-2 lg:col-span-3">
                                <x-tombol>Simpan perubahan</x-tombol>
                            </div>
                        </form>
                    </x-kartu>
                @endforeach
            </div>
        @endif
    </div>
@endsection
