@extends('layouts.publik')

@section('judul', 'SPMB Yayasan Al-Ishlah Insan Mulia — Wonosobo')
@section('deskripsi', 'Penerimaan murid baru TKIT Al Ishlah Kertek, KB-TKIT Insan Mulia, SDIT Insan Mulia, dan SMPIT Insan Mulia Wonosobo.')

@section('isi')
    @php
        $sampulHero = $sorotan[0] ?? null;
        $galeriSorotan = array_slice($sorotan, 1);
        $jumlahSekolah = count($sekolah);
    @endphp

    {{-- Hero: ajakan mendaftar berdampingan dengan foto prestasi murid. --}}
    <section class="overflow-hidden rounded-3xl border border-[var(--garis)] bg-white shadow-sm">
        <div class="grid lg:grid-cols-[1.05fr_1fr]">
            <div class="pola-hero flex flex-col justify-center bg-[var(--primer)] p-6 text-white sm:p-10">
                <p class="inline-flex w-fit items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white ring-1 ring-inset ring-white/25">
                    <span class="h-1.5 w-1.5 rounded-full bg-[var(--aksen)]" aria-hidden="true"></span>
                    Tahun ajaran {{ $tahun_ajaran ?? '2027/2028' }}
                </p>

                <h1 class="mt-4 text-3xl font-bold leading-tight sm:text-4xl">
                    Penerimaan Murid Baru {{ $tahun_ajaran ?? '2027/2028' }}
                </h1>
                <p class="mt-3 max-w-xl text-base leading-relaxed text-white/90 sm:text-lg">
                    Satu pintu untuk {{ $jumlahSekolah > 0 ? $jumlahSekolah : 'empat' }} sekolah Islam di Wonosobo.
                    Daftar dari rumah dan pantau seluruh prosesnya lewat akun Anda.
                </p>

                <ul class="mt-5 space-y-2 text-sm text-white/90">
                    <li class="flex items-start gap-2">
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white/15 text-[11px] font-bold" aria-hidden="true">✓</span>
                        Satu akun untuk semua anak dan semua sekolah
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white/15 text-[11px] font-bold" aria-hidden="true">✓</span>
                        Unggah berkas dari rumah, tanpa antre
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white/15 text-[11px] font-bold" aria-hidden="true">✓</span>
                        Hasil seleksi diumumkan di akun Anda
                    </li>
                </ul>

                <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('daftar-akun') }}" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-[var(--aksen)] px-6 py-3 font-bold text-white shadow-sm hover:opacity-90">
                        Daftar Sekarang
                    </a>
                    <a href="{{ route('masuk') }}" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-white/60 px-6 py-3 font-semibold text-white hover:bg-white/10">
                        Masuk ke Akun
                    </a>
                </div>
            </div>

            <div class="relative min-h-60 bg-[var(--primer-gelap)] sm:min-h-72">
                @if ($sampulHero)
                    <img
                        src="{{ $sampulHero['url'] }}"
                        alt="{{ $sampulHero['alt'] }}"
                        @if ($sampulHero['lebar']) width="{{ $sampulHero['lebar'] }}" @endif
                        @if ($sampulHero['tinggi']) height="{{ $sampulHero['tinggi'] }}" @endif
                        class="absolute inset-0 h-full w-full object-cover object-center"
                        fetchpriority="high"
                        decoding="async"
                    >
                    <div class="absolute inset-x-0 bottom-0 bg-gray-900/80 p-4 text-white sm:p-5">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-white/75">{{ $sampulHero['sekolah'] }}</p>
                        <p class="mt-1 text-sm font-semibold leading-snug">{{ $sampulHero['keterangan'] }}</p>
                    </div>
                @else
                    <div class="flex h-full min-h-60 items-center justify-center p-6 text-center text-sm font-semibold text-white/80 sm:min-h-72">
                        Foto kegiatan murid menyusul.
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Ringkasan singkat, tanpa angka kuota atau biaya. --}}
    <dl class="mt-4 grid grid-cols-2 gap-3 sm:mt-6 sm:grid-cols-4">
        <div class="rounded-2xl border border-[var(--garis)] bg-white p-4">
            <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--teks-sekunder)]">Sekolah</dt>
            <dd class="mt-1 text-2xl font-bold text-[var(--primer)]">{{ $jumlahSekolah > 0 ? $jumlahSekolah : '4' }}</dd>
            <p class="mt-1 text-xs leading-snug text-[var(--teks-sekunder)]">Sekolah Islam dalam satu yayasan</p>
        </div>
        <div class="rounded-2xl border border-[var(--garis)] bg-white p-4">
            <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--teks-sekunder)]">Jenjang</dt>
            <dd class="mt-1 text-2xl font-bold text-[var(--primer)]">KB–SMP</dd>
            <p class="mt-1 text-xs leading-snug text-[var(--teks-sekunder)]">KB, TK, TPA, SD, dan SMP</p>
        </div>
        <div class="rounded-2xl border border-[var(--garis)] bg-white p-4">
            <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--teks-sekunder)]">Akun</dt>
            <dd class="mt-1 text-2xl font-bold text-[var(--primer)]">1</dd>
            <p class="mt-1 text-xs leading-snug text-[var(--teks-sekunder)]">Untuk semua anak dan sekolah</p>
        </div>
        <div class="rounded-2xl border border-[var(--garis)] bg-white p-4">
            <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--teks-sekunder)]">Pendaftaran</dt>
            <dd class="mt-1 text-2xl font-bold text-[var(--primer)]">Daring</dd>
            <p class="mt-1 text-xs leading-snug text-[var(--teks-sekunder)]">Isi formulir dari rumah</p>
        </div>
    </dl>

    {{-- Pilih sekolah: foto asli gedung dan kegiatan tiap sekolah. --}}
    <section id="pilih-sekolah" class="mt-10">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 class="text-xl font-bold text-[var(--teks)] sm:text-2xl">Pilih Sekolah</h2>
                <p class="mt-1 text-sm text-[var(--teks-sekunder)]">Empat sekolah, satu proses pendaftaran.</p>
            </div>
            <a href="{{ route('daftar-akun') }}" class="text-sm font-semibold text-[var(--primer)] hover:underline">Buat akun walisantri</a>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            @forelse ($sekolah as $s)
                <article class="flex flex-col overflow-hidden rounded-2xl border border-[var(--garis)] bg-white shadow-sm">
                    <div class="relative aspect-[16/10] bg-gray-100">
                        @if ($s['sampul'])
                            <img
                                src="{{ $s['sampul']['url'] }}"
                                alt="{{ $s['sampul']['alt'] }}"
                                class="h-full w-full object-cover object-center"
                                loading="lazy"
                                decoding="async"
                            >
                        @else
                            <div class="flex h-full items-center justify-center text-sm text-gray-500">Foto menyusul</div>
                        @endif

                        <span class="absolute left-3 top-3 inline-flex rounded-full bg-white/95 px-2.5 py-1 text-xs font-semibold shadow-sm {{ $s['status']['dibuka'] ? 'text-green-800' : 'text-gray-700' }}">
                            {{ $s['status']['label'] }}
                        </span>
                    </div>

                    <div class="flex flex-1 flex-col p-5">
                        <div class="flex items-start gap-3">
                            @if ($s['logo'])
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-[var(--garis)] bg-white p-1">
                                    <img src="{{ $s['logo']['url'] }}" alt="{{ $s['logo']['alt'] }}" width="{{ $s['logo']['lebar'] ?? 48 }}" height="{{ $s['logo']['tinggi'] ?? 48 }}" loading="lazy" decoding="async" class="h-full w-full object-contain">
                                </span>
                            @else
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gray-200 text-center text-[10px] leading-tight text-gray-500">Logo menyusul</span>
                            @endif

                            <div class="min-w-0">
                                <h3 class="font-bold leading-snug text-[var(--teks)]">{{ $s['nama'] }}</h3>
                                <p class="text-sm text-[var(--teks-sekunder)]">{{ $s['jenjang'] }}</p>
                            </div>
                        </div>

                        @if ($s['slogan'])
                            <p class="mt-3 text-sm italic leading-relaxed text-[var(--teks)]">“{{ $s['slogan'] }}”</p>
                        @endif

                        @if ($s['status']['tanggal_buka'])
                            <p class="mt-2 text-xs text-[var(--teks-sekunder)]">
                                {{ $s['status']['tanggal_buka'] }}@if ($s['status']['tanggal_tutup']) – {{ $s['status']['tanggal_tutup'] }}@endif
                            </p>
                        @endif

                        <div class="mt-4 flex flex-wrap gap-2 pt-1">
                            <a href="{{ route('sekolah', strtolower($s['kode'])) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-[var(--primer)] px-4 py-2 text-sm font-semibold text-[var(--primer)] hover:bg-[var(--primer)] hover:text-white">
                                Lihat sekolah
                            </a>
                            <a href="{{ route('daftar-akun') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-[var(--primer)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--primer-gelap)]">
                                Daftar
                            </a>
                            @if ($s['whatsapp_tautan'])
                                <a href="{{ $s['whatsapp_tautan'] }}" rel="noopener" target="_blank" class="inline-flex min-h-11 items-center justify-center rounded-lg px-3 py-2 text-sm font-semibold text-[var(--teks-sekunder)] hover:text-[var(--primer)]">
                                    Tanya panitia
                                </a>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <x-kosong pesan="Data sekolah belum tersedia." />
            @endforelse
        </div>
    </section>

    {{-- Prestasi dan kegiatan: foto asli yang diunggah sekolah. --}}
    @if ($galeriSorotan !== [])
        <section class="mt-10">
            <h2 class="text-xl font-bold text-[var(--teks)] sm:text-2xl">Prestasi dan Kegiatan Murid</h2>
            <p class="mt-1 max-w-2xl text-sm text-[var(--teks-sekunder)]">
                Dokumentasi asli dari sekolah-sekolah Yayasan Al-Ishlah Insan Mulia.
            </p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($galeriSorotan as $foto)
                    <figure class="overflow-hidden rounded-2xl border border-[var(--garis)] bg-white shadow-sm">
                        <div class="aspect-[4/3] bg-gray-100">
                            <img
                                src="{{ $foto['url'] }}"
                                alt="{{ $foto['alt'] }}"
                                class="h-full w-full object-cover object-center"
                                loading="lazy"
                                decoding="async"
                            >
                        </div>
                        <figcaption class="p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-[var(--primer)]">{{ $foto['sekolah'] }}</p>
                            <p class="mt-1 text-sm font-semibold leading-snug text-[var(--teks)]">{{ $foto['keterangan'] }}</p>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Profil yayasan. --}}
    <section class="mt-10 rounded-3xl border border-[var(--garis)] bg-white p-6 shadow-sm sm:p-8">
        <div class="grid gap-6 lg:grid-cols-[1fr_1.1fr]">
            <div>
                <div class="flex items-center gap-3">
                    @if ($yayasan['logo'])
                        <img src="{{ $yayasan['logo']['url'] }}" alt="{{ $yayasan['logo']['alt'] }}" width="{{ $yayasan['logo']['lebar'] ?? 56 }}" height="{{ $yayasan['logo']['tinggi'] ?? 56 }}" loading="lazy" decoding="async" class="h-14 w-14 object-contain">
                    @endif
                    <div>
                        <h2 class="text-xl font-bold text-[var(--teks)]">Tentang Yayasan</h2>
                        <p class="text-sm text-[var(--teks-sekunder)]">{{ $yayasan['nama'] }}</p>
                    </div>
                </div>

                <p class="mt-4 text-sm leading-relaxed text-[var(--teks)]">
                    {{ $yayasan['profil'] ?: 'Yayasan Al-Ishlah Insan Mulia menaungi sekolah Islam di Wonosobo, dari KB sampai SMP. Semua sekolah memakai satu sistem penerimaan murid baru ini.' }}
                </p>

                @if ($yayasan['tautan_sosial'])
                    <a href="{{ $yayasan['tautan_sosial'] }}" class="mt-4 inline-flex min-h-11 items-center font-semibold text-[var(--primer)] hover:underline" rel="noopener" target="_blank">
                        Ikuti kabar yayasan
                    </a>
                @endif
            </div>

            <div class="rounded-2xl bg-[var(--latar)] p-5">
                @if (! $yayasan['visi'] && $yayasan['misi'] === [])
                    <h3 class="text-sm font-bold uppercase tracking-wide text-[var(--teks-sekunder)]">Sekolah dalam yayasan</h3>
                    <ul class="mt-3 space-y-2">
                        @foreach ($sekolah as $s)
                            <li>
                                <a href="{{ route('sekolah', strtolower($s['kode'])) }}" class="flex items-center justify-between gap-3 rounded-xl bg-white px-3 py-2 text-sm shadow-sm hover:text-[var(--primer)]">
                                    <span class="font-semibold text-[var(--teks)]">{{ $s['nama'] }}</span>
                                    <span class="shrink-0 text-xs text-[var(--teks-sekunder)]">{{ $s['jenjang'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <h3 class="text-sm font-bold uppercase tracking-wide text-[var(--teks-sekunder)]">Visi</h3>
                    @if ($yayasan['visi'])
                        <p class="mt-2 text-sm font-semibold leading-relaxed text-[var(--teks)]">{{ $yayasan['visi'] }}</p>
                    @else
                        <p class="mt-2 rounded-xl bg-white p-3 text-sm text-[var(--teks-sekunder)]">Segera hadir.</p>
                    @endif

                    @if ($yayasan['misi'] !== [])
                        <h3 class="mt-5 text-sm font-bold uppercase tracking-wide text-[var(--teks-sekunder)]">Misi</h3>
                        <ol class="mt-2 list-inside list-decimal space-y-2 text-sm leading-relaxed text-[var(--teks)]">
                            @foreach ($yayasan['misi'] as $misi)
                                <li>{{ $misi }}</li>
                            @endforeach
                        </ol>
                    @endif
                @endif
            </div>
        </div>
    </section>

    {{-- Alur pendaftaran. --}}
    <section class="mt-10">
        <h2 class="text-xl font-bold text-[var(--teks)] sm:text-2xl">Alur Pendaftaran</h2>
        <p class="mt-1 text-sm text-[var(--teks-sekunder)]">Enam langkah dari membuat akun sampai bergabung ke grup murid baru.</p>
        <ol class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($alur as $nomor => $langkah)
                <li class="flex items-start gap-3 rounded-2xl border border-[var(--garis)] bg-white p-4 shadow-sm">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[var(--primer)] text-sm font-bold text-white">{{ $nomor + 1 }}</span>
                    <p class="pt-1.5 text-sm font-semibold leading-relaxed text-[var(--teks)]">{{ $langkah }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Kontak panitia per sekolah. --}}
    <section class="mt-10">
        <h2 class="text-xl font-bold text-[var(--teks)] sm:text-2xl">Hubungi Panitia</h2>
        <p class="mt-1 text-sm text-[var(--teks-sekunder)]">Tanyakan langsung ke panitia sekolah tujuan lewat WhatsApp.</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            @foreach ($sekolah as $s)
                @if ($s['whatsapp_tautan'])
                    <a href="{{ $s['whatsapp_tautan'] }}" rel="noopener" target="_blank" class="flex items-center justify-between gap-3 rounded-2xl border border-[var(--garis)] bg-white p-4 shadow-sm hover:border-[var(--primer)]">
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-[var(--teks)]">{{ $s['nama'] }}</span>
                            <span class="block text-sm text-[var(--teks-sekunder)]">{{ $s['whatsapp_tampil'] }}</span>
                        </span>
                        <span class="shrink-0 text-sm font-semibold text-[var(--primer)]">WhatsApp</span>
                    </a>
                @endif
            @endforeach
        </div>
    </section>

    {{-- Penutup. --}}
    <section class="pola-hero mt-10 rounded-3xl bg-[var(--primer)] p-6 text-white sm:p-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold sm:text-2xl">Siap mendaftar?</h2>
                <p class="mt-1 text-sm text-white/90">Buat akun walisantri, lalu pilih sekolah dan isi formulir.</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('daftar-akun') }}" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-[var(--aksen)] px-6 py-3 font-bold text-white hover:opacity-90">
                    Buat Akun
                </a>
                <a href="{{ route('masuk') }}" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-white/60 px-6 py-3 font-semibold text-white hover:bg-white/10">
                    Masuk
                </a>
            </div>
        </div>
    </section>
@endsection
