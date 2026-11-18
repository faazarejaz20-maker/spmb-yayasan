@extends('layouts.panel')

@section('judul', 'Dasbor Panitia')

@section('isi')
    <div class="space-y-5">
        <section class="pola-hero relative overflow-hidden rounded-3xl bg-[var(--primer)] p-6 text-white shadow-sm sm:p-7">
            <div class="absolute -right-12 -top-14 h-40 w-40 rounded-full bg-white/10" aria-hidden="true"></div>
            <div class="absolute -bottom-16 right-12 h-32 w-32 rounded-full bg-[var(--aksen)]/20" aria-hidden="true"></div>

            <div class="relative flex flex-wrap items-end justify-between gap-5">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-white/75">
                        Dasbor panitia<span class="hidden sm:inline"> · {{ $sekolah?->nama ?? 'Sekolah belum ditetapkan' }}</span>
                    </p>
                    <h1 class="mt-1 text-2xl font-bold leading-tight">Selamat datang, {{ auth()->user()->nama }}</h1>
                    <p class="mt-2 max-w-xl text-sm leading-relaxed text-white/85">
                        Ringkasan pendaftar yang sudah dikirim untuk sekolah Anda. Data hanya mencakup sekolah ini.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('panitia.pendaftar.index', ['status' => 'Baru']) }}"
                       class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-white px-4 text-sm font-bold text-[var(--primer)] shadow-sm transition hover:bg-teal-50">
                        <x-ikon nama="centang" kelas="h-4 w-4" />
                        Verifikasi pendaftar
                    </a>
                    <a href="{{ route('panitia.pembayaran.index') }}"
                       class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-white/40 px-4 text-sm font-semibold text-white transition hover:bg-white/10">
                        <x-ikon nama="kartu" kelas="h-4 w-4" />
                        Pembayaran
                        @if ($antrean > 0)
                            <span class="rounded-full bg-[var(--aksen)] px-2 py-0.5 text-xs font-bold text-[var(--teks)]">{{ $antrean }}</span>
                        @endif
                    </a>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <x-statistik label="Pendaftar terkirim" :nilai="$jumlah['total']" ikon="pengguna-banyak" warna="primer" />
            <x-statistik label="Belum diverifikasi" :nilai="$jumlah['baru']" ikon="jam" warna="biru" />
            <x-statistik label="Perlu perbaikan" :nilai="$jumlah['perlu_perbaikan']" ikon="seru" warna="oranye" />
            <x-statistik label="Diterima" :nilai="$jumlah['diterima']" ikon="centang" warna="hijau" />
            <x-statistik label="Cadangan" :nilai="$jumlah['cadangan']" ikon="daftar" warna="kuning" />
        </div>

        <div class="grid gap-4 lg:grid-cols-2 lg:items-start">
            <div class="space-y-4">
                <div class="rounded-2xl border border-[var(--garis)] bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="flex items-center gap-2 text-sm font-bold text-[var(--teks)]">
                            <x-ikon nama="kalender" kelas="h-4 w-4 text-[var(--primer)]" />
                            Kuota gelombang dibuka
                        </h2>
                        <a href="{{ route('panitia.pendaftar.index') }}" class="text-xs font-semibold text-[var(--primer)] hover:underline">Semua pendaftar</a>
                    </div>

                @if ($gelombang->isEmpty())
                    <div class="mt-4">
                        <x-kosong judul="Belum ada gelombang dibuka" keterangan="Admin sekolah belum membuka gelombang pendaftaran." />
                    </div>
                @else
                    <div class="mt-4 space-y-2.5">
                        @foreach ($gelombang as $baris)
                            <div class="rounded-xl border border-[var(--garis)] p-3.5">
                                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <span class="font-semibold text-[var(--teks)]">{{ $baris['nama'] }}</span>

                                    @if ($baris['kuota'] === null)
                                        <x-lencana-status label="Tanpa batas" warna="netral" />
                                    @else
                                        <span class="text-xs font-semibold text-[var(--teks-sekunder)]">
                                            <span class="text-[var(--teks)]">{{ $baris['terisi'] }}</span> / {{ $baris['kuota'] }} terisi
                                        </span>
                                    @endif
                                </div>

                                @if ($baris['kuota'] !== null && (int) $baris['kuota'] > 0)
                                    <progress class="kemajuan mt-2.5 w-full" value="{{ max(0, (int) $baris['terisi']) }}" max="{{ max(1, (int) $baris['kuota']) }}">
                                        {{ $baris['terisi'] }} dari {{ $baris['kuota'] }}
                                    </progress>
                                @endif

                                <p class="mt-1.5 text-xs text-[var(--teks-sekunder)]">
                                    Sisa kuota:
                                    <span class="font-semibold text-[var(--teks)]">{{ $baris['sisa'] === null ? 'tanpa batas' : max(0, (int) $baris['sisa']) }}</span>
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
                </div>

                <div class="rounded-2xl border border-[var(--garis)] bg-white p-5 shadow-sm">
                    <h2 class="flex items-center gap-2 text-sm font-bold text-[var(--teks)]">
                        <x-ikon nama="papan-clip" kelas="h-4 w-4 text-[var(--primer)]" />
                        Alur kerja panitia
                    </h2>

                    <ol class="mt-3 space-y-3">
                        @foreach ([
                            ['judul' => 'Verifikasi data dan berkas', 'isi' => 'Periksa isian dan berkas pendaftar baru, lalu tetapkan Diverifikasi atau minta perbaikan.'],
                            ['judul' => 'Tetapkan hasil seleksi', 'isi' => 'Tentukan Diterima, Ditolak, atau Cadangan sesuai kuota dan kebijakan sekolah.'],
                            ['judul' => 'Layani daftar ulang', 'isi' => 'Setelah admin mengumumkan hasil, periksa pembayaran dan catat daftar ulang di sekolah.'],
                        ] as $langkah)
                            <li class="flex gap-2.5">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-teal-50 text-[11px] font-bold text-[var(--primer)] ring-1 ring-inset ring-teal-100">{{ $loop->iteration }}</span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-[var(--teks)]">{{ $langkah['judul'] }}</span>
                                    <span class="block text-xs leading-relaxed text-[var(--teks-sekunder)]">{{ $langkah['isi'] }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>

            <div class="rounded-2xl border border-[var(--garis)] bg-white p-5 shadow-sm">
                <h2 class="flex items-center gap-2 text-sm font-bold text-[var(--teks)]">
                    <x-ikon nama="seru" kelas="h-4 w-4 text-[var(--aksen)]" />
                    Perlu tindakan
                </h2>

                <div class="mt-3 space-y-2">
                    <a href="{{ route('panitia.pendaftar.index', ['status' => 'Baru']) }}"
                       class="group flex items-center gap-3 rounded-xl border border-[var(--garis)] p-3 transition hover:border-[var(--primer)]/40 hover:bg-[var(--latar)]">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700">
                            <x-ikon nama="jam" kelas="h-4 w-4" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-[var(--teks)]">Verifikasi pendaftar baru</span>
                            <span class="block text-xs text-[var(--teks-sekunder)]">Data dan berkas menunggu pemeriksaan</span>
                        </span>
                        <span class="rounded-full bg-[var(--latar)] px-2.5 py-1 text-xs font-bold text-[var(--primer)]">{{ $jumlah['baru'] }}</span>
                        <x-ikon nama="panah-kanan" kelas="h-4 w-4 text-gray-300 group-hover:text-[var(--primer)]" />
                    </a>

                    <a href="{{ route('panitia.pendaftar.index', ['status' => 'Perlu perbaikan']) }}"
                       class="group flex items-center gap-3 rounded-xl border border-[var(--garis)] p-3 transition hover:border-[var(--primer)]/40 hover:bg-[var(--latar)]">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-700">
                            <x-ikon nama="seru" kelas="h-4 w-4" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-[var(--teks)]">Menunggu perbaikan walisantri</span>
                            <span class="block text-xs text-[var(--teks-sekunder)]">Walisantri diminta melengkapi data</span>
                        </span>
                        <span class="rounded-full bg-[var(--latar)] px-2.5 py-1 text-xs font-bold text-[var(--primer)]">{{ $jumlah['perlu_perbaikan'] }}</span>
                        <x-ikon nama="panah-kanan" kelas="h-4 w-4 text-gray-300 group-hover:text-[var(--primer)]" />
                    </a>

                    <a href="{{ route('panitia.pembayaran.index') }}"
                       class="group flex items-center gap-3 rounded-xl border border-[var(--garis)] p-3 transition hover:border-[var(--primer)]/40 hover:bg-[var(--latar)]">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-green-50 text-green-700">
                            <x-ikon nama="kartu" kelas="h-4 w-4" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-[var(--teks)]">Bukti transfer menunggu</span>
                            <span class="block text-xs text-[var(--teks-sekunder)]">Periksa bukti pembayaran daftar ulang</span>
                        </span>
                        <span class="rounded-full bg-[var(--latar)] px-2.5 py-1 text-xs font-bold text-[var(--primer)]">{{ $antrean }}</span>
                        <x-ikon nama="panah-kanan" kelas="h-4 w-4 text-gray-300 group-hover:text-[var(--primer)]" />
                    </a>

                    <a href="{{ route('panitia.offline.index') }}"
                       class="group flex items-center gap-3 rounded-xl border border-[var(--garis)] p-3 transition hover:border-[var(--primer)]/40 hover:bg-[var(--latar)]">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-700">
                            <x-ikon nama="tambah-pengguna" kelas="h-4 w-4" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-[var(--teks)]">Daftarkan walisantri offline</span>
                            <span class="block text-xs text-[var(--teks-sekunder)]">Buat akun dan isi data atas nama walisantri</span>
                        </span>
                        <x-ikon nama="panah-kanan" kelas="h-4 w-4 text-gray-300 group-hover:text-[var(--primer)]" />
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
