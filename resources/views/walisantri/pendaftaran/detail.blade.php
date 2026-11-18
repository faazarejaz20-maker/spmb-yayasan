@extends('layouts.walisantri')

@section('judul', 'Detail Pendaftaran')

@section('isi')
    @php
        $diterima = $status->sudahDiumumkan && $status->hasil === \App\Enums\StatusHasil::Diterima;
        $adaKemajuan = $kemajuan['tampil'] && ! $status->draf;
    @endphp

    <div class="space-y-4">
        <div class="overflow-hidden rounded-3xl border border-[var(--garis)] bg-white shadow-sm">
            <div class="h-1 bg-gradient-to-r from-[var(--primer)] via-[var(--primer-gelap)] to-[var(--aksen)]" aria-hidden="true"></div>

            <div class="p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex min-w-0 items-start gap-3">
                        <x-logo-sekolah :kode="$kodeSekolah" :nama="$sekolah" kelas="h-12 w-12" />

                        <div class="min-w-0">
                            <h1 class="truncate text-xl font-bold text-[var(--teks)]">{{ $namaAnak }}</h1>
                            <p class="mt-0.5 text-sm text-[var(--teks-sekunder)]">
                                {{ $sekolah }}@if ($program) · Program {{ $program }}@endif
                            </p>
                        </div>
                    </div>

                    <x-lencana-status :label="$status->label" :warna="$status->warna" />
                </div>

                @if ($nomorPeserta)
                    <p class="mt-4 flex flex-wrap items-center gap-2 text-sm text-[var(--teks-sekunder)]">
                        Nomor peserta
                        <span class="inline-flex rounded-lg bg-[var(--latar)] px-2.5 py-1 font-mono text-sm font-bold text-[var(--teks)] ring-1 ring-inset ring-[var(--garis)]">{{ $nomorPeserta }}</span>
                    </p>
                @endif

                @if ($status->langkahBerikutnya)
                    <p class="mt-3 rounded-xl bg-[var(--latar)] p-3 text-sm text-[var(--teks-sekunder)]">{{ $status->langkahBerikutnya }}</p>
                @endif
            </div>
        </div>

        @if ($adaKemajuan)
            <div class="rounded-2xl border border-[var(--garis)] bg-white p-5 shadow-sm">
                <h2 class="text-sm font-bold text-[var(--teks)]">Perjalanan pendaftaran</h2>

                <ol class="mt-4">
                    @foreach ($kemajuan['langkah'] as $lingkaran)
                        <li class="flex gap-3">
                            <div class="flex flex-col items-center">
                                <span @class([
                                    'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[11px] font-bold ring-1 ring-inset',
                                    'bg-[var(--primer)] text-white ring-[var(--primer)]' => ! empty($lingkaran['selesai']),
                                    'bg-white text-[var(--aksen)] ring-[var(--aksen)]' => empty($lingkaran['selesai']) && ! empty($lingkaran['aktif']),
                                    'bg-white text-[var(--teks-sekunder)] ring-[var(--garis)]' => empty($lingkaran['selesai']) && empty($lingkaran['aktif']),
                                ])>
                                    @if (! empty($lingkaran['selesai']))
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 4.5 4.5 10.5-10.5" />
                                        </svg>
                                    @else
                                        {{ $loop->iteration }}
                                    @endif
                                </span>

                                @unless ($loop->last)
                                    <span class="my-1 h-full min-h-5 w-px bg-[var(--garis)]" aria-hidden="true"></span>
                                @endunless
                            </div>

                            <p class="pb-4 text-sm leading-7 {{ ! empty($lingkaran['selesai']) ? 'font-semibold text-[var(--teks)]' : 'text-[var(--teks-sekunder)]' }}">
                                {{ $lingkaran['label'] }}
                            </p>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif

        @if ($status->draf)
            <div class="rounded-2xl border border-[var(--garis)] bg-white p-5 shadow-sm">
                <h2 class="text-sm font-bold text-[var(--teks)]">Lengkapi pendaftaran</h2>
                <p class="mt-1 text-sm text-[var(--teks-sekunder)]">
                    Pendaftaran ini belum dikirim. Lengkapi formulir, berkas, dan biaya, lalu kirim.
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('walisantri.pendaftaran.langkah', ['pendaftaran' => $pendaftaranUuid, 'n' => 2]) }}"
                       class="inline-flex min-h-11 items-center rounded-xl bg-[var(--primer)] px-4 text-sm font-semibold text-white hover:bg-[var(--primer-gelap)]">
                        Lanjutkan formulir
                    </a>
                    <a href="{{ route('walisantri.pendaftaran.berkas.index', $pendaftaranUuid) }}"
                       class="inline-flex min-h-11 items-center rounded-xl border border-[var(--garis)] px-4 text-sm font-semibold text-[var(--teks)] hover:bg-gray-50">
                        Unggah berkas
                    </a>
                </div>
            </div>
        @endif

        @if ($status->perluPerbaikan)
            <div class="rounded-2xl border border-orange-200 bg-orange-50 p-5">
                <div class="flex items-start gap-2">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-orange-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                    <div>
                        <h2 class="text-sm font-bold text-orange-900">Perbaikan diminta panitia</h2>
                        <p class="mt-1 text-sm text-orange-900">
                            Perbaiki data atau berkas yang ditandai, lalu kirim perbaikan.
                        </p>
                    </div>
                </div>

                @if (! empty($catatanPerbaikan))
                    <div class="mt-3 rounded-xl bg-white/80 p-3 text-sm text-orange-900">
                        <p class="font-semibold">Catatan dari panitia</p>
                        <p class="mt-1 whitespace-pre-line">{{ $catatanPerbaikan }}</p>
                    </div>
                @endif

                @if ($berkasPerbaikan->isNotEmpty())
                    <ul class="mt-3 space-y-2 text-sm text-orange-900">
                        @foreach ($berkasPerbaikan as $berkasItem)
                            <li class="rounded-xl bg-white/70 p-3">
                                <p class="font-semibold">{{ $berkasItem['nama'] }}</p>
                                @if ($berkasItem['catatan'])
                                    <p class="mt-1">{{ $berkasItem['catatan'] }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('walisantri.pendaftaran.langkah', ['pendaftaran' => $pendaftaranUuid, 'n' => 2]) }}"
                       class="inline-flex min-h-11 items-center rounded-xl bg-[var(--primer)] px-4 text-sm font-semibold text-white hover:bg-[var(--primer-gelap)]">
                        Perbaiki data
                    </a>
                    <a href="{{ route('walisantri.pendaftaran.berkas.index', $pendaftaranUuid) }}"
                       class="inline-flex min-h-11 items-center rounded-xl border border-orange-200 bg-white px-4 text-sm font-semibold text-orange-900 hover:bg-orange-100">
                        Perbaiki berkas
                    </a>
                </div>

                <form method="POST" action="{{ route('walisantri.pendaftaran.kirim-perbaikan', $pendaftaranUuid) }}" class="mt-3">
                    @csrf
                    <x-tombol tipe="sekunder">Kirim perbaikan</x-tombol>
                </form>
            </div>
        @endif

        @if ($status->sudahDiumumkan)
            <div class="rounded-2xl border border-[var(--garis)] bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-[var(--teks)]">Hasil seleksi</h2>
                        <p class="mt-1 text-sm text-[var(--teks-sekunder)]">
                            Hasil sudah diumumkan untuk gelombang ini.
                        </p>
                    </div>

                    <svg class="h-9 w-9 text-[var(--aksen)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
                    </svg>
                </div>

                <a href="{{ route('walisantri.pendaftaran.hasil', $pendaftaranUuid) }}"
                   class="mt-4 inline-flex min-h-11 items-center rounded-xl bg-[var(--primer)] px-4 text-sm font-semibold text-white hover:bg-[var(--primer-gelap)]">
                    Lihat hasil
                </a>
            </div>
        @endif

        @if ($diterima)
            <div class="rounded-2xl border border-green-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-bold text-[var(--teks)]">Daftar ulang</h2>
                <p class="mt-1 text-sm text-[var(--teks-sekunder)]">
                    Lakukan daftar ulang dan unggah bukti pembayaran. Wawancara dilakukan bersamaan dengan daftar ulang di sekolah.
                </p>

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
                <p class="mt-1 text-sm text-[var(--teks-sekunder)]">
                    Bergabunglah ke grup WhatsApp gelombang ini untuk menerima informasi resmi sekolah.
                </p>
                <a href="{{ $tautanGrupWa }}" target="_blank" rel="noopener noreferrer"
                   class="mt-3 inline-flex min-h-11 items-center rounded-xl bg-green-600 px-4 text-sm font-semibold text-white hover:bg-green-700">
                    Buka tautan grup
                </a>
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('walisantri.beranda') }}"
               class="inline-flex min-h-11 items-center rounded-xl px-4 text-sm font-semibold text-[var(--primer)] hover:underline">
                Kembali ke beranda
            </a>

            @unless ($status->draf)
                <a href="{{ route('walisantri.pendaftaran.cetak.bukti', $pendaftaranUuid) }}"
                   target="_blank" rel="noopener"
                   class="inline-flex min-h-11 items-center rounded-xl border border-[var(--garis)] bg-white px-4 text-sm font-semibold text-[var(--teks)] hover:bg-gray-50">
                    Cetak Bukti Pendaftaran
                </a>
            @endunless
        </div>
    </div>
@endsection
