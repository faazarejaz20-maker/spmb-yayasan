@extends('layouts.walisantri')

@section('judul', 'Pendaftaran Terkirim')

@section('isi')
    <div class="space-y-4">
        <div class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-[var(--garis)]">
            <div class="pola-hero bg-[var(--primer)] px-6 py-8 text-center text-white">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-white/15 ring-1 ring-inset ring-white/30">
                    <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 4.5 4.5 10.5-10.5" />
                    </svg>
                </div>

                <h1 class="mt-4 text-2xl font-bold">Pendaftaran terkirim</h1>
                <p class="mt-1 text-sm text-white/85">{{ $namaAnak }} — {{ $sekolah }}</p>

                <div class="mx-auto mt-5 w-fit rounded-2xl bg-white/10 px-5 py-3 ring-1 ring-inset ring-white/25">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-white/70">Nomor peserta</p>
                    <p class="mt-1 font-mono text-2xl font-bold">{{ $nomorPeserta }}</p>
                </div>
            </div>

            <div class="p-5">
                <p class="text-sm leading-relaxed text-[var(--teks-sekunder)]">
                    Simpan nomor peserta ini. Panitia akan memverifikasi data dan berkas Anda.
                    Hasil seleksi hanya terlihat di akun ini setelah diumumkan oleh sekolah.
                </p>

                <div class="mt-4 grid gap-2 sm:grid-cols-3">
                    <div class="rounded-xl bg-[var(--latar)] p-3">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-[var(--teks-sekunder)]">Langkah 1</p>
                        <p class="mt-1 text-sm font-semibold text-[var(--teks)]">Panitia memeriksa data dan berkas</p>
                    </div>
                    <div class="rounded-xl bg-[var(--latar)] p-3">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-[var(--teks-sekunder)]">Langkah 2</p>
                        <p class="mt-1 text-sm font-semibold text-[var(--teks)]">Pantau status di akun</p>
                    </div>
                    <div class="rounded-xl bg-[var(--latar)] p-3">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-[var(--teks-sekunder)]">Langkah 3</p>
                        <p class="mt-1 text-sm font-semibold text-[var(--teks)]">Lihat pengumuman hasil</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('walisantri.pendaftaran.detail', $pendaftaranUuid) }}"
               class="inline-flex min-h-12 items-center justify-center rounded-xl bg-[var(--primer)] px-5 text-sm font-semibold text-white hover:bg-[var(--primer-gelap)]">
                Lihat status pendaftaran
            </a>
            <a href="{{ route('walisantri.pendaftaran.cetak.bukti', $pendaftaranUuid) }}" target="_blank" rel="noopener"
               class="inline-flex min-h-12 items-center justify-center rounded-xl border border-[var(--garis)] bg-white px-5 text-sm font-semibold text-[var(--teks)] hover:bg-gray-50">
                Cetak Bukti Pendaftaran
            </a>
            <a href="{{ route('walisantri.beranda') }}"
               class="inline-flex min-h-12 items-center justify-center rounded-xl border border-[var(--garis)] bg-white px-5 text-sm font-semibold text-[var(--teks)] hover:bg-gray-50">
                Kembali ke beranda
            </a>
        </div>
    </div>
@endsection
