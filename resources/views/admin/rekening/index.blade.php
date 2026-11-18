@extends('layouts.panel')

@section('judul', 'Rekening Sekolah')

@section('isi')
    <div class="space-y-5">
        <x-kepala-halaman
            judul="Rekening sekolah"
            :sekolah="$sekolah?->nama"
            keterangan="Rekening hanya tampil setelah walisantri masuk. Nomor rekening tidak dicatat utuh di log aktivitas."
        />

        <x-kartu judul="Tambah rekening">
            <form method="POST" action="{{ route('admin.rekening.simpan') }}" class="grid gap-3 sm:grid-cols-2">
                @csrf
                <x-isian name="bank" label="Bank" wajib />
                <x-isian name="nomor_rekening" label="Nomor rekening" wajib bantuan="Angka, spasi, atau tanda hubung" />
                <x-isian name="atas_nama" label="Atas nama" wajib />
                <label class="flex items-center gap-2 self-end text-sm text-[var(--teks)]">
                    <input type="checkbox" name="aktif" value="1" class="h-4 w-4 rounded border-[var(--garis)]" checked>
                    Aktif
                </label>

                <div class="sm:col-span-2">
                    <x-tombol>Tambah rekening</x-tombol>
                </div>
            </form>
        </x-kartu>

        @error('rekening')
            <p class="text-sm text-[var(--bahaya)]">{{ $message }}</p>
        @enderror

        @if ($daftar->isEmpty())
            <x-kosong judul="Belum ada rekening" keterangan="Tambahkan rekening tujuan transfer pembayaran daftar ulang." />
        @else
            <div class="space-y-4">
                @foreach ($daftar as $rekening)
                    <x-kartu>
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="font-bold text-[var(--teks)]">{{ $rekening->bank }}</p>
                                <p class="text-xs text-[var(--teks-sekunder)]">
                                    {{ $rekening->nomor_rekening }} &middot; {{ $rekening->atas_nama }} &middot;
                                    {{ $rekening->aktif ? 'Aktif' : 'Nonaktif' }}
                                </p>
                            </div>

                            <x-modal-konfirmasi
                                :aksi="route('admin.rekening.hapus', $rekening)"
                                metode="DELETE"
                                judul="Hapus rekening?"
                                teks="Rekening tidak dapat dihapus bila sudah ada pembayaran transfer. Tindakan ini tidak dapat dibatalkan."
                                labelTombol="Ya, hapus"
                            >
                                Hapus
                            </x-modal-konfirmasi>
                        </div>

                        <form method="POST" action="{{ route('admin.rekening.ubah', $rekening) }}" class="grid gap-3 sm:grid-cols-2">
                            @csrf
                            @method('PUT')
                            <x-isian name="bank" label="Bank" wajib :nilai="$rekening->bank" />
                            <x-isian name="nomor_rekening" label="Nomor rekening" wajib :nilai="$rekening->nomor_rekening" />
                            <x-isian name="atas_nama" label="Atas nama" wajib :nilai="$rekening->atas_nama" />
                            <label class="flex items-center gap-2 self-end text-sm text-[var(--teks)]">
                                <input type="checkbox" name="aktif" value="1" class="h-4 w-4 rounded border-[var(--garis)]" @checked($rekening->aktif)>
                                Aktif
                            </label>

                            <div class="sm:col-span-2">
                                <x-tombol>Simpan perubahan</x-tombol>
                            </div>
                        </form>
                    </x-kartu>
                @endforeach
            </div>
        @endif
    </div>
@endsection
