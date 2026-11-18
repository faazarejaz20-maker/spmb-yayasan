@extends('layouts.walisantri')

@section('judul', 'Pembayaran Daftar Ulang')

@section('isi')
    @php
        $total = $rincian->sum(fn ($b) => (int) $b->nominal);
    @endphp

    <div class="space-y-4">
        <x-kepala-halaman
            judul="Pembayaran daftar ulang"
            :anak="$namaAnak"
            :sekolah="$sekolah"
            eyebrow="Daftar ulang"
        />

        <div class="rounded-2xl border border-[var(--garis)] bg-white p-4">
            <h2 class="text-sm font-bold text-[var(--teks)]">Rincian biaya</h2>

            <div class="mt-2 space-y-1 text-sm">
                @forelse ($rincian as $biaya)
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-[var(--teks-sekunder)]">{{ $biaya->nama }}</span>
                        <span class="font-medium text-[var(--teks)]">Rp{{ number_format((int) $biaya->nominal, 0, ',', '.') }}</span>
                    </div>
                @empty
                    <p class="text-[var(--teks-sekunder)]">Rincian biaya akan diinformasikan panitia.</p>
                @endforelse
            </div>

            @if ($rincian->isNotEmpty())
                <div class="mt-3 flex items-center justify-between border-t border-[var(--garis)] pt-3 text-sm">
                    <span class="font-semibold text-[var(--teks)]">Total</span>
                    <span class="font-bold text-[var(--primer)]">Rp{{ number_format($total, 0, ',', '.') }}</span>
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-[var(--garis)] bg-white p-4">
            <h2 class="text-sm font-bold text-[var(--teks)]">Rekening tujuan transfer</h2>

            @if ($rekening->isEmpty())
                <p class="mt-2 text-sm text-[var(--teks-sekunder)]">
                    Rekening belum tersedia. Silakan hubungi panitia sekolah untuk cara pembayaran.
                </p>
            @else
                <ul class="mt-2 space-y-2 text-sm">
                    @foreach ($rekening as $bank)
                        <li class="rounded-xl bg-gray-50 p-3">
                            <p class="font-semibold text-[var(--teks)]">{{ $bank->bank }}</p>
                            <p class="font-mono text-base text-[var(--teks)]">{{ $bank->nomor_rekening }}</p>
                            <p class="text-xs text-[var(--teks-sekunder)]">a.n. {{ $bank->atas_nama }}</p>
                        </li>
                    @endforeach
                </ul>

                <p class="mt-3 text-xs text-[var(--teks-sekunder)]">
                    Transfer sesuai total, lalu unggah bukti di bawah. Pembayaran tunai dicatat oleh panitia di sekolah.
                </p>
            @endif
        </div>

        @if ($rekening->isNotEmpty())
            <form method="POST" action="{{ route('walisantri.pendaftaran.pembayaran.simpan', $pendaftaranUuid) }}"
                  enctype="multipart/form-data" class="space-y-3 rounded-2xl border border-[var(--garis)] bg-white p-4" novalidate>
                @csrf

                <h2 class="text-sm font-bold text-[var(--teks)]">Unggah bukti transfer</h2>

                <x-isian name="nominal" label="Nominal yang ditransfer" tipe="angka" inputmode="numeric"
                         :nilai="$total > 0 ? $total : null" wajib bantuan="Isi angka saja, tanpa titik atau Rp." />

                <x-tanggal name="dibayar_pada" label="Tanggal transfer" :nilai="now()->toDateString()" wajib />

                <div>
                    <label for="bukti" class="mb-1 block text-sm font-semibold text-[var(--teks)]">
                        Bukti transfer <span class="text-[var(--bahaya)]">*</span>
                    </label>
                    <input id="bukti" type="file" name="bukti" accept="image/jpeg,image/png,application/pdf"
                           class="block w-full text-sm" required>
                    <p class="mt-1 text-xs text-[var(--teks-sekunder)]">JPG, PNG, atau PDF, maksimal 2 MB.</p>
                    @error('bukti')
                        <p class="mt-1 text-sm text-[var(--bahaya)]">{{ $message }}</p>
                    @enderror
                </div>

                <x-tombol>Kirim bukti pembayaran</x-tombol>
            </form>
        @endif

        <div class="rounded-2xl border border-[var(--garis)] bg-white p-4">
            <h2 class="text-sm font-bold text-[var(--teks)]">Riwayat pembayaran</h2>

            @if ($pembayaran->isEmpty())
                <p class="mt-2 text-sm text-[var(--teks-sekunder)]">Belum ada bukti pembayaran yang dikirim.</p>
            @else
                <ul class="mt-2 space-y-2">
                    @foreach ($pembayaran as $bayar)
                        @php
                            $warna = match ($bayar['status']) {
                                'Disetujui' => 'hijau',
                                'Ditolak' => 'merah',
                                default => 'kuning',
                            };
                        @endphp

                        <li class="rounded-xl border border-[var(--garis)] p-3 text-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-[var(--teks)]">
                                        Rp{{ number_format((int) $bayar['nominal'], 0, ',', '.') }}
                                    </p>
                                    <p class="text-xs text-[var(--teks-sekunder)]">
                                        {{ $bayar['metode'] }}
                                        @if ($bayar['dibayar_pada'])
                                            · {{ \Illuminate\Support\Carbon::parse($bayar['dibayar_pada'])->format('d-m-Y') }}
                                        @endif
                                    </p>
                                </div>
                                <x-lencana-status :label="$bayar['status']" :warna="$warna" />
                            </div>

                            @if ($bayar['catatan_penolakan'])
                                <p class="mt-2 rounded-lg bg-red-50 p-2 text-xs text-red-800">
                                    {{ $bayar['catatan_penolakan'] }}
                                </p>
                            @endif

                            @if ($bayar['punya_bukti'])
                                <a href="{{ route('walisantri.pembayaran.bukti', $bayar['uuid']) }}" target="_blank" rel="noopener"
                                   class="mt-2 inline-block text-xs font-semibold text-[var(--primer)] hover:underline">
                                    Lihat bukti
                                </a>
                            @endif

                            @if ($bayar['status'] === 'Disetujui')
                                <a href="{{ route('walisantri.pembayaran.cetak.kuitansi', $bayar['uuid']) }}" target="_blank" rel="noopener"
                                   class="mt-2 inline-block text-xs font-semibold text-[var(--primer)] hover:underline">
                                    Cetak kuitansi
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <a href="{{ route('walisantri.pendaftaran.detail', $pendaftaranUuid) }}"
           class="inline-flex min-h-11 items-center rounded-xl px-4 text-sm font-semibold text-[var(--primer)] hover:underline">
            Kembali ke detail pendaftaran
        </a>
    </div>
@endsection
