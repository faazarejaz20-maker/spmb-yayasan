@extends('layouts.panel')

@section('judul', 'Kartu Akun Walisantri')

@section('isi')
    @php
        $kataSandi = (string) ($kartu['kata_sandi'] ?? '');
    @endphp

    <div class="mx-auto max-w-lg space-y-4">
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 print:hidden">
            Kata sandi ini hanya tampil sekali dan tidak disimpan dalam bentuk terbaca.
            Salin atau serahkan sekarang. Saat masuk pertama, walisantri wajib mengganti kata sandi.
        </div>

        <div class="rounded-2xl border border-[var(--garis)] bg-white p-6">
            <div class="flex items-center justify-between gap-3 border-b border-[var(--garis)] pb-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-[var(--teks-sekunder)]">SPMB Al-Ishlah Insan Mulia</p>
                    <h1 class="mt-1 text-lg font-bold text-[var(--teks)]">Kartu akun walisantri</h1>
                </div>
                <x-logo :tinggi="40" />
            </div>

            <dl class="mt-4 space-y-3 text-sm">
                <div>
                    <dt class="text-xs text-[var(--teks-sekunder)]">Nama</dt>
                    <dd class="font-semibold text-[var(--teks)]">{{ $kartu['nama'] ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-[var(--teks-sekunder)]">Masuk dengan nomor HP / email</dt>
                    <dd class="font-mono text-base font-semibold text-[var(--teks)]">{{ $kartu['identitas'] ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-[var(--teks-sekunder)]">Kata sandi sementara</dt>
                    <dd class="mt-1 select-all rounded-xl border border-dashed border-[var(--primer)] bg-[var(--latar)] px-4 py-3 font-mono text-xl font-bold tracking-wider text-[var(--teks)]">
                        {{ $kartu['kata_sandi'] ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-[var(--teks-sekunder)]">Alamat situs</dt>
                    <dd class="break-all text-[var(--teks)]">{{ $alamatSitus }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-[var(--teks-sekunder)]">Sekolah</dt>
                    <dd class="text-[var(--teks)]">{{ $kartu['sekolah'] ?? '—' }}</dd>
                </div>
            </dl>

            <p class="mt-4 border-t border-[var(--garis)] pt-3 text-xs text-[var(--teks-sekunder)]">
                Segera ganti kata sandi setelah masuk pertama kali. Jangan mengirim kata sandi lewat
                pesan yang dapat dibaca orang lain; sampaikan langsung kepada walisantri.
            </p>

            <div class="mt-4 flex flex-wrap gap-2 print:hidden">
                <button
                    type="button"
                    data-salin-kartu="{{ $kataSandi }}"
                    class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[var(--primer)] px-4 text-sm font-semibold text-[var(--primer)] hover:bg-[var(--latar)]"
                >
                    <span data-label-salin>Salin kata sandi</span>
                </button>

                <button
                    type="button"
                    data-cetak-kartu
                    class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[var(--primer)] px-4 text-sm font-semibold text-white hover:bg-[var(--primer-gelap)]"
                >
                    Cetak kartu
                </button>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 print:hidden">
            @if ($lanjut)
                <a href="{{ route('panitia.offline.sekolah') }}"
                   class="inline-flex min-h-12 items-center justify-center rounded-xl bg-[var(--primer)] px-5 text-sm font-semibold text-white hover:bg-[var(--primer-gelap)]">
                    Lanjut daftarkan anak
                </a>
            @else
                <a href="{{ route('panitia.pendaftar.index') }}"
                   class="inline-flex min-h-12 items-center justify-center rounded-xl bg-[var(--primer)] px-5 text-sm font-semibold text-white hover:bg-[var(--primer-gelap)]">
                    Kembali ke daftar pendaftar
                </a>
            @endif

            <a href="{{ route('panitia.beranda') }}"
               class="inline-flex min-h-12 items-center justify-center rounded-xl border border-[var(--garis)] bg-white px-5 text-sm font-semibold text-[var(--teks)] hover:bg-gray-50">
                Dasbor
            </a>
        </div>
    </div>
@endsection
