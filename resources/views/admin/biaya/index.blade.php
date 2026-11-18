@extends('layouts.panel')

@section('judul', 'Rincian Biaya')

@section('isi')
    <div class="space-y-5">
        <x-kepala-halaman
            judul="Rincian biaya pendidikan"
            :sekolah="$sekolah?->nama"
            keterangan="Biaya hanya tampil setelah walisantri masuk; tidak pernah di halaman publik. Rincian yang sudah dipakai tidak dapat dihapus; nonaktifkan saja."
        />

        <x-kartu judul="Tambah rincian biaya">
            <form method="POST" action="{{ route('admin.biaya.simpan') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @csrf
                <x-isian name="nama" label="Nama biaya" wajib />
                <x-isian name="kelompok" label="Kelompok" bantuan="Misalnya IAP, seragam, iuran" />
                <x-isian name="nominal" label="Nominal (rupiah)" tipe="angka" wajib />
                <x-pilihan name="tipe" label="Tipe" wajib :opsi="['tetap' => 'Tetap', 'pilihan' => 'Pilihan (dipilih walisantri)']" />
                <x-isian name="urutan" label="Urutan" tipe="angka" />
                <x-isian name="catatan" label="Catatan" tipe="teks_panjang" class="sm:col-span-2 lg:col-span-3" />

                <label class="flex items-center gap-2 text-sm text-[var(--teks)]">
                    <input type="checkbox" name="aktif" value="1" class="h-4 w-4 rounded border-[var(--garis)]" checked>
                    Aktif
                </label>

                <div class="sm:col-span-2 lg:col-span-3">
                    <x-tombol>Tambah rincian biaya</x-tombol>
                </div>
            </form>
        </x-kartu>

        @error('biaya')
            <p class="text-sm text-[var(--bahaya)]">{{ $message }}</p>
        @enderror

        @if ($daftar->isEmpty())
            <x-kosong judul="Belum ada rincian biaya" keterangan="Tambahkan komponen biaya pendidikan untuk sekolah ini." />
        @else
            <div class="space-y-4">
                @foreach ($daftar as $biaya)
                    <x-kartu>
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="font-bold text-[var(--teks)]">{{ $biaya->nama }}</p>
                                <p class="text-xs text-[var(--teks-sekunder)]">
                                    {{ $biaya->kelompok ?: 'Tanpa kelompok' }} &middot; {{ $biaya->tipe?->label() }} &middot;
                                    Rp {{ number_format((float) $biaya->nominal, 0, ',', '.') }} &middot;
                                    {{ $biaya->aktif ? 'Aktif' : 'Nonaktif' }}
                                </p>
                            </div>

                            <x-modal-konfirmasi
                                :aksi="route('admin.biaya.hapus', $biaya)"
                                metode="DELETE"
                                judul="Hapus rincian biaya?"
                                teks="Rincian yang sudah dipakai pendaftaran atau pembayaran tidak dapat dihapus. Tindakan ini tidak dapat dibatalkan."
                                labelTombol="Ya, hapus"
                            >
                                Hapus
                            </x-modal-konfirmasi>
                        </div>

                        <form method="POST" action="{{ route('admin.biaya.ubah', $biaya) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @csrf
                            @method('PUT')
                            <x-isian name="nama" label="Nama biaya" wajib :nilai="$biaya->nama" />
                            <x-isian name="kelompok" label="Kelompok" :nilai="$biaya->kelompok" />
                            <x-isian name="nominal" label="Nominal (rupiah)" tipe="angka" :nilai="$biaya->nominal" wajib />
                            <x-pilihan name="tipe" label="Tipe" wajib :nilai="$biaya->tipe?->value" :opsi="['tetap' => 'Tetap', 'pilihan' => 'Pilihan (dipilih walisantri)']" />
                            <x-isian name="urutan" label="Urutan" tipe="angka" :nilai="$biaya->urutan" />
                            <x-isian name="catatan" label="Catatan" tipe="teks_panjang" :nilai="$biaya->catatan" class="sm:col-span-2 lg:col-span-3" />

                            <label class="flex items-center gap-2 text-sm text-[var(--teks)]">
                                <input type="checkbox" name="aktif" value="1" class="h-4 w-4 rounded border-[var(--garis)]" @checked($biaya->aktif)>
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
