@extends('layouts.panel')

@section('judul', 'Antrean Pembayaran')

@section('isi')
    <div class="space-y-4">
        <x-kepala-halaman
            judul="Bukti transfer menunggu"
            :sekolah="$sekolah?->nama.' · '.$antrean->count().' bukti menunggu pemeriksaan'"
        >
            <x-slot:aksi>
                <x-tautan-tombol :href="route('panitia.pendaftar.index')" tipe="sekunder">Daftar pendaftar</x-tautan-tombol>
            </x-slot:aksi>
        </x-kepala-halaman>

        @if ($antrean->isEmpty())
            <x-kosong judul="Tidak ada bukti menunggu" keterangan="Semua bukti transfer sudah diperiksa." />
        @else
            <div class="space-y-3">
                @foreach ($antrean as $bayar)
                    @php
                        $pendaftaran = $bayar->pendaftaran;
                    @endphp

                    <div class="rounded-2xl border border-[var(--garis)] bg-white p-4 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('panitia.pendaftar.detail', ['pendaftaran' => $pendaftaran->uuid, 'tab' => 'pembayaran']) }}"
                                   class="font-semibold text-[var(--primer)] hover:underline">
                                    {{ $pendaftaran->anak?->nama_lengkap ?? '—' }}
                                </a>
                                <p class="text-xs text-[var(--teks-sekunder)]">
                                    {{ $pendaftaran->nomor_peserta ?? '—' }} &middot; {{ $pendaftaran->gelombang?->nama }}
                                </p>
                            </div>

                            <div class="text-right">
                                <p class="text-base font-bold text-[var(--teks)]">Rp{{ number_format((int) $bayar->nominal, 0, ',', '.') }}</p>
                                <p class="text-xs text-[var(--teks-sekunder)]">
                                    Dibayar {{ $bayar->dibayar_pada?->format('d-m-Y') ?? '—' }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-3 border-t border-[var(--garis)] pt-3">
                            <x-tautan-tombol :href="route('panitia.pembayaran.tampil', $bayar->uuid)" target="_blank" rel="noopener" tipe="sekunder" ukuran="kecil">
                                Lihat bukti
                            </x-tautan-tombol>

                            <form method="POST" action="{{ route('panitia.pembayaran.setujui', $bayar->uuid) }}">
                                @csrf
                                <input type="hidden" name="dari" value="antrean">
                                <x-tombol ukuran="kecil">Setujui</x-tombol>
                            </form>

                            <form method="POST" action="{{ route('panitia.pembayaran.tolak', $bayar->uuid) }}" class="flex flex-1 flex-wrap items-end gap-2">
                                @csrf
                                <input type="hidden" name="dari" value="antrean">

                                <div class="min-w-0 flex-1">
                                    <label for="catatan-{{ $bayar->uuid }}" class="mb-1 block text-xs font-semibold text-[var(--teks)]">
                                        Alasan penolakan
                                    </label>
                                    <input type="text" id="catatan-{{ $bayar->uuid }}" name="catatan" required maxlength="1000"
                                           class="min-h-11 w-full rounded-xl border border-[var(--garis)] bg-white px-3 text-sm">
                                </div>

                                <x-tombol tipe="bahaya" ukuran="kecil">Tolak</x-tombol>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
