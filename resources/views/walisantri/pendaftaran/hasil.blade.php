@extends('layouts.walisantri')

@section('judul', 'Hasil Seleksi')

@section('isi')
    @php
        $diterima = $status->sudahDiumumkan && $status->hasil === \App\Enums\StatusHasil::Diterima;
        $cadangan = $status->sudahDiumumkan && $status->hasil === \App\Enums\StatusHasil::Cadangan;

        $kepala = match (true) {
            $diterima => 'bg-green-600',
            $cadangan => 'bg-amber-500',
            $status->sudahDiumumkan => 'bg-slate-600',
            default => 'bg-[var(--primer)]',
        };
    @endphp

    <div class="space-y-4">
        <div class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-[var(--garis)]">
            <div class="pola-hero px-6 py-8 text-center text-white {{ $kepala }}">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-bold uppercase tracking-wide ring-1 ring-inset ring-white/30">
                    {{ $status->label }}
                </span>

                @if ($diterima)
                    <h1 class="mt-4 text-2xl font-bold leading-tight">Alhamdulillah, {{ $namaAnak }} diterima</h1>
                    <p class="mt-1 text-sm text-white/85">{{ $sekolah }}</p>
                @elseif ($status->sudahDiumumkan)
                    <h1 class="mt-4 text-xl font-bold leading-tight">Hasil seleksi {{ $namaAnak }}</h1>
                    <p class="mt-1 text-sm text-white/85">{{ $sekolah }}</p>
                @else
                    <h1 class="mt-4 text-xl font-bold leading-tight">Hasil seleksi</h1>
                    <p class="mt-1 text-sm text-white/85">{{ $namaAnak }} · {{ $sekolah }}</p>
                @endif

                @if ($nomorPeserta)
                    <div class="mx-auto mt-5 w-fit rounded-2xl bg-white/10 px-4 py-2 ring-1 ring-inset ring-white/25">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-white/70">Nomor peserta</p>
                        <p class="mt-0.5 font-mono text-lg font-bold">{{ $nomorPeserta }}</p>
                    </div>
                @endif
            </div>

            @if ($status->langkahBerikutnya)
                <p class="px-6 py-4 text-center text-sm text-[var(--teks-sekunder)]">{{ $status->langkahBerikutnya }}</p>
            @endif
        </div>

        @if (! $status->sudahDiumumkan)
            <div class="rounded-2xl border border-[var(--garis)] bg-white p-5 shadow-sm">
                <div class="flex items-start gap-2">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-[var(--primer)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <div>
                        <p class="font-semibold text-[var(--teks)]">Hasil belum diumumkan</p>
                        <p class="mt-1 text-sm leading-relaxed text-[var(--teks-sekunder)]">
                            Hasil seleksi hanya akan tampil di halaman ini setelah sekolah mengumumkannya untuk gelombang Anda.
                            Anda akan melihat statusnya berubah tanpa perlu memuat ulang berkali-kali.
                        </p>
                    </div>
                </div>
            </div>
        @else
            @if ($diterima)
                <div class="rounded-2xl border border-green-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-bold text-[var(--teks)]">Langkah berikutnya</h2>
                    <ol class="mt-3 space-y-2 text-sm text-[var(--teks-sekunder)]">
                        <li class="flex gap-2">
                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-green-100 text-[10px] font-bold text-green-700">1</span>
                            Lakukan daftar ulang di sekolah sesuai jadwal.
                        </li>
                        <li class="flex gap-2">
                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-green-100 text-[10px] font-bold text-green-700">2</span>
                            Unggah bukti pembayaran daftar ulang melalui menu pembayaran.
                        </li>
                        <li class="flex gap-2">
                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-green-100 text-[10px] font-bold text-green-700">3</span>
                            Wawancara dilakukan bersamaan dengan daftar ulang.
                        </li>
                    </ol>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('walisantri.pendaftaran.pembayaran.index', $pendaftaranUuid) }}"
                           class="inline-flex min-h-11 items-center rounded-xl bg-[var(--primer)] px-4 text-sm font-semibold text-white hover:bg-[var(--primer-gelap)]">
                            Pembayaran daftar ulang
                        </a>
                        <a href="{{ route('walisantri.pendaftaran.tahap2', $pendaftaranUuid) }}"
                           class="inline-flex min-h-11 items-center rounded-xl border border-[var(--garis)] px-4 text-sm font-semibold text-[var(--teks)] hover:bg-gray-50">
                            Formulir Tahap 2
                        </a>
                    </div>
                </div>
            @endif

            @if ($tautanGrupWa)
                <div class="rounded-2xl border border-green-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-bold text-[var(--teks)]">Grup WhatsApp</h2>
                    <p class="mt-1 text-sm text-[var(--teks-sekunder)]">Bergabung untuk menerima informasi resmi sekolah.</p>
                    <a href="{{ $tautanGrupWa }}" target="_blank" rel="noopener noreferrer"
                       class="mt-3 inline-flex min-h-11 items-center rounded-xl bg-green-600 px-4 text-sm font-semibold text-white hover:bg-green-700">
                        Buka tautan grup
                    </a>
                </div>
            @endif
        @endif

        <div class="rounded-2xl border border-[var(--garis)] bg-white p-5 text-sm text-[var(--teks-sekunder)] shadow-sm">
            <p class="font-semibold text-[var(--teks)]">Butuh penjelasan?</p>
            <p class="mt-1">Hubungi panitia sekolah melalui menu bantuan di beranda akun.</p>
        </div>

        <a href="{{ route('walisantri.pendaftaran.detail', $pendaftaranUuid) }}"
           class="inline-flex min-h-11 items-center rounded-xl px-4 text-sm font-semibold text-[var(--primer)] hover:underline">
            Kembali ke detail pendaftaran
        </a>
    </div>
@endsection
