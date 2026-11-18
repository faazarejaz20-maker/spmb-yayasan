@php
    $totalTagihan = $rincian->sum(fn ($b) => (int) $b->nominal);
    $sudahDisetujui = $pendaftaran->pembayaran->where('status', \App\Enums\StatusPembayaran::Disetujui)->sum('nominal');
@endphp

<div class="space-y-4">
    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-[var(--garis)] bg-white p-4">
            <p class="text-xs text-[var(--teks-sekunder)]">Total rincian biaya</p>
            <p class="mt-1 text-lg font-bold text-[var(--teks)]">Rp{{ number_format((int) $totalTagihan, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-[var(--garis)] bg-white p-4">
            <p class="text-xs text-[var(--teks-sekunder)]">Disetujui</p>
            <p class="mt-1 text-lg font-bold text-[var(--primer)]">Rp{{ number_format((int) $sudahDisetujui, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-[var(--garis)] bg-white p-4">
            <p class="text-xs text-[var(--teks-sekunder)]">Status hasil</p>
            <p class="mt-1 text-lg font-bold text-[var(--teks)]">{{ $pendaftaran->status_hasil?->label() ?? '—' }}</p>
        </div>
    </div>

    @if ($rincian->isNotEmpty())
        <x-kartu judul="Rincian biaya sekolah">
            <div class="space-y-1 text-sm">
                @foreach ($rincian as $biaya)
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-[var(--teks-sekunder)]">{{ $biaya->nama }}</span>
                        <span class="font-medium text-[var(--teks)]">Rp{{ number_format((int) $biaya->nominal, 0, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>
        </x-kartu>
    @endif

    <x-kartu judul="Riwayat pembayaran">
        @if ($pendaftaran->pembayaran->isEmpty())
            <x-kosong judul="Belum ada pembayaran" keterangan="Walisantri belum mengunggah bukti transfer dan panitia belum mencatat pembayaran tunai." />
        @else
            <div class="space-y-3">
                @foreach ($pendaftaran->pembayaran->sortByDesc('id') as $bayar)
                    <div class="rounded-xl border border-[var(--garis)] p-3">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold text-[var(--teks)]">
                                    Rp{{ number_format((int) $bayar->nominal, 0, ',', '.') }}
                                    <span class="text-xs font-normal text-[var(--teks-sekunder)]">&middot; {{ $bayar->metode?->label() }}</span>
                                </p>
                                <p class="text-xs text-[var(--teks-sekunder)]">
                                    Dibayar {{ $bayar->dibayar_pada?->format('d-m-Y') ?? '—' }}
                                    @if ($bayar->rincianBiaya)
                                        &middot; {{ $bayar->rincianBiaya->nama }}
                                    @endif
                                </p>
                            </div>
                            <x-lencana-status :label="$bayar->status?->label() ?? '—'" />
                        </div>

                        @if ($bayar->catatan_penolakan)
                            <p class="mt-2 rounded-lg border border-red-200 bg-red-50 p-2 text-sm text-red-800">
                                Alasan penolakan: {{ $bayar->catatan_penolakan }}
                            </p>
                        @endif

                        <div class="mt-2 flex flex-wrap items-center gap-3 text-sm">
                            @if ($bayar->bukti_path)
                                <a href="{{ route('panitia.pembayaran.tampil', $bayar->uuid) }}" target="_blank" rel="noopener"
                                   class="font-semibold text-[var(--primer)] hover:underline">
                                    Lihat bukti
                                </a>
                            @endif

                            @if ($bayar->status === \App\Enums\StatusPembayaran::Disetujui)
                                <a href="{{ route('panitia.pembayaran.cetak.kuitansi', $bayar->uuid) }}" target="_blank" rel="noopener"
                                   class="font-semibold text-[var(--primer)] hover:underline">
                                    Cetak kuitansi
                                </a>
                            @endif

                            <span class="text-xs text-[var(--teks-sekunder)]">Diperbarui {{ $bayar->updated_at?->format('d-m-Y H:i') }}</span>
                        </div>

                        @if ($bayar->status === \App\Enums\StatusPembayaran::Menunggu)
                            <div class="mt-3 flex flex-wrap items-start gap-2 border-t border-[var(--garis)] pt-3">
                                <form method="POST" action="{{ route('panitia.pembayaran.setujui', $bayar->uuid) }}">
                                    @csrf
                                    <input type="hidden" name="dari" value="detail">
                                    <x-tombol ukuran="kecil">Setujui</x-tombol>
                                </form>

                                <form method="POST" action="{{ route('panitia.pembayaran.tolak', $bayar->uuid) }}" class="flex flex-1 flex-wrap items-end gap-2">
                                    @csrf
                                    <input type="hidden" name="dari" value="detail">

                                    <div class="min-w-0 flex-1">
                                        <label for="tolak-{{ $bayar->uuid }}" class="mb-1 block text-xs font-semibold text-[var(--teks)]">Alasan penolakan</label>
                                        <input type="text" id="tolak-{{ $bayar->uuid }}" name="catatan" required maxlength="1000"
                                               class="min-h-11 w-full rounded-xl border border-[var(--garis)] bg-white px-3 text-sm">
                                    </div>

                                    <x-tombol tipe="bahaya" ukuran="kecil">Tolak</x-tombol>
                                </form>
                            </div>
                        @elseif (auth()->user()->peran === \App\Enums\PeranPengguna::Admin)
                            <details class="mt-3 border-t border-[var(--garis)] pt-3">
                                <summary class="cursor-pointer text-xs font-semibold text-[var(--primer)]">Ubah hasil pemeriksaan (admin)</summary>

                                <form method="POST" action="{{ route('panitia.pembayaran.setujui', $bayar->uuid) }}" class="mt-2 flex flex-wrap items-end gap-2">
                                    @csrf
                                    <input type="hidden" name="dari" value="detail">

                                    <div class="min-w-0 flex-1">
                                        <label for="ubah-{{ $bayar->uuid }}" class="mb-1 block text-xs font-semibold text-[var(--teks)]">Alasan perubahan (wajib)</label>
                                        <input type="text" id="ubah-{{ $bayar->uuid }}" name="catatan" required maxlength="1000"
                                               class="min-h-11 w-full rounded-xl border border-[var(--garis)] bg-white px-3 text-sm">
                                    </div>

                                    <x-tombol tipe="sekunder" ukuran="kecil">Tandai disetujui</x-tombol>
                                </form>

                                <form method="POST" action="{{ route('panitia.pembayaran.tolak', $bayar->uuid) }}" class="mt-2 flex flex-wrap items-end gap-2">
                                    @csrf
                                    <input type="hidden" name="dari" value="detail">

                                    <div class="min-w-0 flex-1">
                                        <label for="ubah-tolak-{{ $bayar->uuid }}" class="mb-1 block text-xs font-semibold text-[var(--teks)]">Alasan penolakan (wajib)</label>
                                        <input type="text" id="ubah-tolak-{{ $bayar->uuid }}" name="catatan" required maxlength="1000"
                                               class="min-h-11 w-full rounded-xl border border-[var(--garis)] bg-white px-3 text-sm">
                                    </div>

                                    <x-tombol tipe="bahaya" ukuran="kecil">Tandai ditolak</x-tombol>
                                </form>
                            </details>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-kartu>

    <x-kartu judul="Catat pembayaran tunai">
        <p class="text-xs text-[var(--teks-sekunder)]">
            Gunakan bila walisantri membayar tunai di sekolah. Pembayaran tunai langsung berstatus disetujui.
        </p>

        <form method="POST" action="{{ route('panitia.pendaftar.pembayaran-tunai', $pendaftaran->uuid) }}" class="mt-3 grid gap-3 sm:grid-cols-3">
            @csrf

            @error('pembayaran')
                <p class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800 sm:col-span-3">{{ $message }}</p>
            @enderror

            <x-isian name="nominal" label="Nominal (Rp)" tipe="angka" inputmode="numeric" :nilai="old('nominal')" wajib />
            <x-tanggal name="dibayar_pada" label="Tanggal bayar" :nilai="old('dibayar_pada', now())" wajib />
            <x-pilihan name="rincian_biaya_id" label="Komponen biaya (opsional)"
                       :opsi="$rincian->mapWithKeys(fn ($b) => [$b->id => $b->nama.' — Rp'.number_format((int) $b->nominal, 0, ',', '.')])->all()"
                       :nilai="old('rincian_biaya_id')" />

            <div class="sm:col-span-3">
                <label for="catatan-tunai" class="mb-1 block text-sm font-semibold text-[var(--teks)]">Catatan (opsional)</label>
                <input type="text" id="catatan-tunai" name="catatan" maxlength="1000" value="{{ old('catatan') }}"
                       class="min-h-11 w-full rounded-xl border border-[var(--garis)] bg-white px-3 text-sm">
            </div>

            <div class="sm:col-span-3">
                <x-tombol>Catat pembayaran tunai</x-tombol>
            </div>
        </form>
    </x-kartu>
</div>
