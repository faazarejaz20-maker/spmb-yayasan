@extends('layouts.panel')

@section('judul', 'Rekap Yayasan')

@section('isi')
    @php
        $r = $rekap['ringkasan'];
    @endphp

    <div class="space-y-5">
        <section class="pola-hero relative overflow-hidden rounded-3xl bg-[var(--primer)] p-6 text-white shadow-sm sm:p-7">
            <div class="absolute -right-12 -top-14 h-40 w-40 rounded-full bg-white/10" aria-hidden="true"></div>
            <div class="absolute -bottom-16 right-12 h-32 w-32 rounded-full bg-[var(--aksen)]/20" aria-hidden="true"></div>

            <div class="relative flex flex-wrap items-end justify-between gap-5">
                <div class="min-w-0">
                    <p class="inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.16em] text-white/75">
                        <x-ikon nama="perisai" kelas="h-3.5 w-3.5" />
                        Yayasan · hanya lihat
                    </p>
                    <h1 class="mt-1 text-2xl font-bold leading-tight">Rekap pendaftaran yayasan</h1>
                    <p class="mt-2 max-w-xl text-sm leading-relaxed text-white/85">
                        Angka agregat seluruh sekolah. Panel ini tidak memuat nama anak, nomor peserta, NIK,
                        berkas, maupun pembayaran per orang.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('yayasan.rekap.unduh') }}"
                       class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-white px-4 text-sm font-bold text-[var(--primer)] shadow-sm transition hover:bg-teal-50">
                        <x-ikon nama="unduh" kelas="h-4 w-4" />
                        Unduh rekap
                    </a>
                    <a href="{{ route('yayasan.rekap') }}"
                       class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-white/40 px-4 text-sm font-semibold text-white transition hover:bg-white/10">
                        <x-ikon nama="grafik" kelas="h-4 w-4" />
                        Rekap per gelombang
                    </a>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <x-statistik label="Pendaftar terkirim" :nilai="$r['total']" ikon="pengguna-banyak" warna="primer" />
            <x-statistik label="Daftar ulang selesai" :nilai="$r['daftar_ulang_selesai']" ikon="centang" warna="hijau" />
            <x-statistik label="Mengundurkan diri" :nilai="$r['mengundurkan_diri']" ikon="seru" warna="merah" />

            @foreach ([
                ['status' => \App\Enums\StatusHasil::Baru, 'ikon' => 'jam', 'warna' => 'biru'],
                ['status' => \App\Enums\StatusHasil::Diverifikasi, 'ikon' => 'centang', 'warna' => 'hijau'],
                ['status' => \App\Enums\StatusHasil::PerluPerbaikan, 'ikon' => 'seru', 'warna' => 'oranye'],
                ['status' => \App\Enums\StatusHasil::Diterima, 'ikon' => 'centang', 'warna' => 'hijau'],
                ['status' => \App\Enums\StatusHasil::Ditolak, 'ikon' => 'seru', 'warna' => 'merah'],
                ['status' => \App\Enums\StatusHasil::Cadangan, 'ikon' => 'daftar', 'warna' => 'kuning'],
            ] as $kartu)
                <x-statistik
                    :label="$kartu['status']->label()"
                    :nilai="$r['status'][$kartu['status']->value] ?? 0"
                    :ikon="$kartu['ikon']"
                    :warna="$kartu['warna']"
                />
            @endforeach
        </div>

        <div class="rounded-2xl border border-[var(--garis)] bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="flex items-center gap-2 text-sm font-bold text-[var(--teks)]">
                    <x-ikon nama="grafik" kelas="h-4 w-4 text-[var(--primer)]" />
                    Perbandingan antarsekolah
                </h2>
                <a href="{{ route('yayasan.rekap') }}" class="text-xs font-semibold text-[var(--primer)] hover:underline">Rekap lengkap</a>
            </div>

            <div class="mt-4">
                <x-tabel minimal="860px">
                    <thead class="border-b border-[var(--garis)] bg-gray-50 text-xs uppercase tracking-wide text-[var(--teks-sekunder)]">
                        <tr>
                            <th class="px-4 py-3">Sekolah</th>
                            <th class="px-4 py-3">Terkirim</th>
                            <th class="px-4 py-3">Diterima</th>
                            <th class="px-4 py-3">Ditolak</th>
                            <th class="px-4 py-3">Cadangan</th>
                            <th class="px-4 py-3">Daftar ulang selesai</th>
                            <th class="px-4 py-3">Kuota</th>
                            <th class="px-4 py-3">Terisi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--garis)]">
                        @foreach ($rekap['sekolah'] as $s)
                            @php($rs = $s['ringkasan'])
                            <tr class="transition hover:bg-[var(--latar)]/60">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2.5">
                                        <x-logo-sekolah :kode="$s['sekolah']->kode" :nama="$s['sekolah']->nama" kelas="h-8 w-8" />
                                        <span class="font-semibold text-[var(--teks)]">{{ $s['sekolah']->nama }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm">{{ $rs['total'] }}</td>
                                <td class="px-4 py-3 text-sm">{{ $rs['status'][\App\Enums\StatusHasil::Diterima->value] ?? 0 }}</td>
                                <td class="px-4 py-3 text-sm">{{ $rs['status'][\App\Enums\StatusHasil::Ditolak->value] ?? 0 }}</td>
                                <td class="px-4 py-3 text-sm">{{ $rs['status'][\App\Enums\StatusHasil::Cadangan->value] ?? 0 }}</td>
                                <td class="px-4 py-3 text-sm">{{ $rs['daftar_ulang_selesai'] }}</td>
                                <td class="px-4 py-3 text-sm">{{ $rs['kuota'] > 0 ? $rs['kuota'] : '—' }}</td>
                                <td class="px-4 py-3 text-sm">{{ $rs['terisi'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-tabel>
            </div>
        </div>
    </div>
@endsection
