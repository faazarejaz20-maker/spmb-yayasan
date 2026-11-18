@extends('layouts.publik')

@section('judul', $sekolah['nama'].' — Penerimaan Murid Baru')
@section('deskripsi', 'Informasi penerimaan murid baru '.$sekolah['nama'].' ('.$sekolah['jenjang'].'), termasuk alur, syarat, dan berkas yang perlu disiapkan.')

@section('isi')
    <nav aria-label="Remah roti" class="text-sm text-[var(--teks-sekunder)]">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a href="{{ route('beranda') }}" class="font-semibold text-[var(--primer)] hover:underline">Beranda</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page">{{ $sekolah['nama'] }}</li>
        </ol>
    </nav>

    <header class="mt-4 overflow-hidden rounded-3xl border border-[var(--garis)] bg-white p-6 shadow-sm sm:p-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
            <div class="shrink-0">
                @if ($sekolah['logo'])
                    <img
                        src="{{ $sekolah['logo']['url'] }}"
                        alt="{{ $sekolah['logo']['alt'] }}"
                        width="{{ $sekolah['logo']['lebar'] ?? 72 }}"
                        height="{{ $sekolah['logo']['tinggi'] ?? 72 }}"
                        loading="lazy"
                        class="h-20 w-20 rounded-2xl bg-white object-contain"
                    >
                @else
                    <span class="flex h-20 w-20 items-center justify-center rounded-2xl bg-gray-200 px-2 text-center text-[11px] leading-tight text-gray-500">
                        Logo menyusul
                    </span>
                @endif
            </div>

            <div class="min-w-0 flex-1">
                <h1 class="text-2xl font-bold leading-tight text-[var(--teks)] sm:text-3xl">{{ $sekolah['nama'] }}</h1>
                <p class="mt-1 text-sm text-[var(--teks-sekunder)]">{{ $sekolah['jenjang'] }} &middot; {{ config('spmb.yayasan.nama') }}</p>

                @if ($konten['slogan'])
                    <p class="mt-3 text-base italic leading-relaxed text-[var(--teks)]">&ldquo;{{ $konten['slogan'] }}&rdquo;</p>
                @endif

                <p class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $status['dibuka'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                        {{ $status['dibuka'] ? 'Pendaftaran dibuka' : 'Belum dibuka' }}
                    </span>
                    @if ($status['tanggal_buka'])
                        <span class="text-xs text-[var(--teks-sekunder)]">
                            {{ $status['tanggal_buka'] }}@if ($status['tanggal_tutup']) – {{ $status['tanggal_tutup'] }}@endif
                        </span>
                    @endif
                </p>

                <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    <a href="{{ route('daftar-akun') }}" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-[var(--primer)] px-5 py-3 font-semibold text-white hover:bg-[var(--primer-gelap)]">
                        Daftar sekarang
                    </a>
                    <a href="{{ route('masuk') }}" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-[var(--garis)] px-5 py-3 font-semibold text-[var(--teks)] hover:bg-gray-50">
                        Saya sudah punya akun
                    </a>
                    @if ($sekolah['whatsapp_tautan'])
                        <a href="{{ $sekolah['whatsapp_tautan'] }}" rel="noopener" target="_blank" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-[var(--primer)] px-5 py-3 font-semibold text-[var(--primer)] hover:bg-[var(--primer)] hover:text-white">
                            WhatsApp panitia
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </header>

    @if ($sekolah['kode'] === 'TKI' && $program_tki !== [])
        <section class="mt-6" aria-labelledby="judul-program">
            <h2 id="judul-program" class="text-xl font-bold text-[var(--teks)]">Tiga Program</h2>
            <p class="mt-1 text-sm text-[var(--teks-sekunder)]">Satu sekolah, tiga layanan pendidikan anak usia dini.</p>

            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                @foreach ($program_tki as $program)
                    <article class="rounded-2xl border border-[var(--garis)] bg-white p-4 text-center">
                        @if ($program['logo'])
                            <img
                                src="{{ $program['logo']['url'] }}"
                                alt="{{ $program['logo']['alt'] }}"
                                width="{{ $program['logo']['lebar'] ?? 48 }}"
                                height="{{ $program['logo']['tinggi'] ?? 48 }}"
                                loading="lazy"
                                class="mx-auto h-12 w-12 object-contain"
                            >
                        @else
                            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-lg bg-gray-200 text-center text-[10px] leading-tight text-gray-500">
                                Logo menyusul
                            </span>
                        @endif
                        <h3 class="mt-3 text-sm font-bold text-[var(--teks)]">{{ $program['nama'] }}</h3>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if ($konten['profil'])
        <section class="mt-6" aria-labelledby="judul-profil">
            <h2 id="judul-profil" class="text-xl font-bold text-[var(--teks)]">Profil Sekolah</h2>
            <div class="mt-3 whitespace-pre-line rounded-2xl border border-[var(--garis)] bg-white p-5 text-sm leading-relaxed text-[var(--teks)]">{{ $konten['profil'] }}</div>
        </section>
    @endif

    <section class="mt-6 grid gap-4 lg:grid-cols-2" aria-labelledby="judul-visi">
        <div class="rounded-2xl border border-[var(--garis)] bg-white p-5">
            <h2 id="judul-visi" class="text-xl font-bold text-[var(--teks)]">Visi</h2>

            @if ($konten['visi'])
                <p class="mt-3 text-sm font-semibold leading-relaxed text-[var(--teks)]">{{ $konten['visi'] }}</p>
            @else
                <p class="mt-3 rounded-xl bg-gray-50 p-3 text-sm text-[var(--teks-sekunder)]">Visi menyusul.</p>
            @endif

            @if ($konten['indikator_visi'] !== [])
                <ul class="mt-4 space-y-2 text-sm leading-relaxed text-[var(--teks)]">
                    @foreach ($konten['indikator_visi'] as $indikator)
                        <li class="flex gap-2">
                            <span aria-hidden="true" class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--primer)]"></span>
                            <span>{{ $indikator }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="rounded-2xl border border-[var(--garis)] bg-white p-5">
            <h2 class="text-xl font-bold text-[var(--teks)]">Misi</h2>

            @if ($konten['misi'] !== [])
                <ol class="mt-3 list-inside list-decimal space-y-2 text-sm leading-relaxed text-[var(--teks)]">
                    @foreach ($konten['misi'] as $misi)
                        <li>{{ $misi }}</li>
                    @endforeach
                </ol>
            @else
                <p class="mt-3 rounded-xl bg-gray-50 p-3 text-sm text-[var(--teks-sekunder)]">Misi menyusul.</p>
            @endif
        </div>
    </section>

    @if ($konten['program_unggulan'] !== [])
        <section class="mt-6" aria-labelledby="judul-unggulan">
            <h2 id="judul-unggulan" class="text-xl font-bold text-[var(--teks)]">Program Unggulan</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ($konten['program_unggulan'] as $program)
                    <article class="overflow-hidden rounded-2xl border border-[var(--garis)] bg-white">
                        @if ($program['gambar'])
                            <img
                                src="{{ $program['gambar']['url'] }}"
                                alt="{{ $program['gambar']['alt'] }}"
                                width="{{ $program['gambar']['lebar'] ?? 640 }}"
                                height="{{ $program['gambar']['tinggi'] ?? 360 }}"
                                loading="lazy"
                                class="h-44 w-full object-cover"
                            >
                        @endif
                        <div class="p-5">
                            @if ($program['judul'])
                                <h3 class="text-sm font-bold text-[var(--teks)]">{{ $program['judul'] }}</h3>
                            @endif
                            @if ($program['isi'])
                                <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-[var(--teks-sekunder)]">{{ $program['isi'] }}</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-6" aria-labelledby="judul-galeri">
        <h2 id="judul-galeri" class="text-xl font-bold text-[var(--teks)]">Galeri</h2>

        @if ($konten['galeri'] !== [])
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($konten['galeri'] as $foto)
                    <img
                        src="{{ $foto['url'] }}"
                        alt="{{ $foto['alt'] }}"
                        width="{{ $foto['lebar'] ?? 480 }}"
                        height="{{ $foto['tinggi'] ?? 360 }}"
                        loading="lazy"
                        class="h-32 w-full rounded-xl border border-[var(--garis)] object-cover sm:h-40"
                    >
                @endforeach
            </div>
        @else
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                @for ($i = 0; $i < 3; $i++)
                    <x-foto-placeholder tinggi="sedang" />
                @endfor
            </div>
        @endif
    </section>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <section class="rounded-2xl border border-[var(--garis)] bg-white p-5" aria-labelledby="judul-ekskul">
            <h2 id="judul-ekskul" class="text-xl font-bold text-[var(--teks)]">Ekstrakurikuler</h2>

            @if ($konten['ekskul'] !== [])
                <ul class="mt-3 flex flex-wrap gap-2 text-sm">
                    @foreach ($konten['ekskul'] as $ekskul)
                        <li class="rounded-full bg-[var(--latar)] px-3 py-1.5 font-semibold text-[var(--teks)]">{{ $ekskul }}</li>
                    @endforeach
                </ul>
            @else
                <p class="mt-3 text-sm text-[var(--teks-sekunder)]">Menyusul.</p>
            @endif
        </section>

        <section class="rounded-2xl border border-[var(--garis)] bg-white p-5" aria-labelledby="judul-fasilitas">
            <h2 id="judul-fasilitas" class="text-xl font-bold text-[var(--teks)]">Fasilitas</h2>

            @if ($konten['fasilitas'] !== [])
                <ul class="mt-3 space-y-2 text-sm leading-relaxed text-[var(--teks)]">
                    @foreach ($konten['fasilitas'] as $fasilitas)
                        <li>
                            <span class="font-semibold">{{ $fasilitas['judul'] ?: 'Fasilitas' }}</span>
                            @if ($fasilitas['isi'])
                                <span class="text-[var(--teks-sekunder)]">— {{ $fasilitas['isi'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-3 text-sm text-[var(--teks-sekunder)]">Menyusul.</p>
            @endif
        </section>
    </div>

    <section class="mt-6" aria-labelledby="judul-prestasi">
        <h2 id="judul-prestasi" class="text-xl font-bold text-[var(--teks)]">Prestasi</h2>

        @if ($konten['prestasi'] !== [])
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($konten['prestasi'] as $prestasi)
                    <article class="overflow-hidden rounded-2xl border border-[var(--garis)] bg-white">
                        @if ($prestasi['gambar'])
                            <img
                                src="{{ $prestasi['gambar']['url'] }}"
                                alt="{{ $prestasi['gambar']['alt'] }}"
                                width="{{ $prestasi['gambar']['lebar'] ?? 480 }}"
                                height="{{ $prestasi['gambar']['tinggi'] ?? 320 }}"
                                loading="lazy"
                                class="h-40 w-full object-cover"
                            >
                        @endif
                        <div class="p-4">
                            <h3 class="text-sm font-bold text-[var(--teks)]">{{ $prestasi['judul'] ?: 'Prestasi' }}</h3>
                            @if ($prestasi['isi'])
                                <p class="mt-1 whitespace-pre-line text-sm text-[var(--teks-sekunder)]">{{ $prestasi['isi'] }}</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <p class="mt-3 rounded-2xl border border-dashed border-[var(--garis)] bg-white p-5 text-sm text-[var(--teks-sekunder)]">
                Data prestasi menyusul.
            </p>
        @endif
    </section>

    <section class="mt-6 grid gap-4 lg:grid-cols-2" aria-labelledby="judul-alur">
        <div class="rounded-2xl border border-[var(--garis)] bg-white p-5">
            <h2 id="judul-alur" class="text-xl font-bold text-[var(--teks)]">Alur Pendaftaran</h2>

            @if ($konten['alur'])
                <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-[var(--teks)]">{{ $konten['alur'] }}</p>
            @else
                <ol class="mt-3 list-inside list-decimal space-y-2 text-sm leading-relaxed text-[var(--teks)]">
                    @foreach ($alur_umum as $langkah)
                        <li>{{ $langkah }}</li>
                    @endforeach
                </ol>
            @endif

            @if ($konten['jam_belajar'])
                <p class="mt-4 border-t border-[var(--garis)] pt-3 text-sm text-[var(--teks)]">
                    <span class="font-semibold">Jam belajar:</span> {{ $konten['jam_belajar'] }}
                </p>
            @endif
        </div>

        <div class="rounded-2xl border border-[var(--garis)] bg-white p-5">
            <h2 class="text-xl font-bold text-[var(--teks)]">Syarat dan Berkas</h2>

            @if ($konten['syarat'])
                <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-[var(--teks)]">{{ $konten['syarat'] }}</p>
            @endif

            @if ($jenis_berkas !== [])
                <ul class="mt-4 space-y-2">
                    @foreach ($jenis_berkas as $berkas)
                        <li class="flex items-start gap-2 rounded-xl border border-[var(--garis)] p-3 text-sm">
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold text-[var(--teks)]">{{ $berkas['nama'] }}</span>
                                <span class="block text-xs text-[var(--teks-sekunder)]">{{ $berkas['jumlah_lembar'] }} lembar</span>
                                @if ($berkas['petunjuk'])
                                    <span class="mt-1 block text-xs text-[var(--teks-sekunder)]">{{ $berkas['petunjuk'] }}</span>
                                @endif
                            </span>
                            <span class="shrink-0 rounded-full px-2 py-1 text-xs font-semibold {{ $berkas['wajib'] ? 'bg-amber-100 text-amber-900' : 'bg-gray-100 text-gray-700' }}">
                                {{ $berkas['wajib'] ? 'Wajib' : 'Opsional' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-3 text-sm text-[var(--teks-sekunder)]">Daftar berkas menyusul.</p>
            @endif

            <p class="mt-4 rounded-xl bg-[var(--latar)] p-3 text-xs leading-relaxed text-[var(--teks-sekunder)]">
                Biaya pendidikan tidak ditampilkan di halaman publik. Rincian biaya dapat dilihat setelah Anda masuk ke akun walisantri.
            </p>
        </div>
    </section>

    <section class="mt-6 rounded-2xl border border-[var(--garis)] bg-white p-5" aria-labelledby="judul-kontak">
        <h2 id="judul-kontak" class="text-xl font-bold text-[var(--teks)]">Kontak dan Lokasi</h2>

        <div class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
            <div>
                <p class="font-semibold text-[var(--teks)]">Alamat</p>
                <p class="mt-1 text-[var(--teks-sekunder)]">{{ $sekolah['alamat'] ?: 'Alamat menyusul.' }}</p>

                @if ($sekolah['alamat'])
                    <a href="https://www.google.com/maps/search/?api=1&amp;query={{ urlencode($sekolah['alamat']) }}"
                       rel="noopener" target="_blank"
                       class="mt-2 inline-flex min-h-11 items-center font-semibold text-[var(--primer)] hover:underline">
                        Buka di peta
                    </a>
                @endif
            </div>

            <div class="space-y-2">
                @if ($sekolah['whatsapp_tautan'])
                    <p>
                        <span class="block font-semibold text-[var(--teks)]">WhatsApp</span>
                        <a href="{{ $sekolah['whatsapp_tautan'] }}" rel="noopener" target="_blank" class="text-[var(--primer)] hover:underline">{{ $sekolah['whatsapp_tampil'] }}</a>
                    </p>
                @endif

                @if ($sekolah['instagram_tautan'])
                    <p>
                        <span class="block font-semibold text-[var(--teks)]">Instagram</span>
                        <a href="{{ $sekolah['instagram_tautan'] }}" rel="noopener" target="_blank" class="text-[var(--primer)] hover:underline">{{ '@'.$sekolah['instagram'] }}</a>
                    </p>
                @endif

                @if ($konten['kontak'])
                    <p class="whitespace-pre-line text-[var(--teks-sekunder)]">{{ $konten['kontak'] }}</p>
                @endif
            </div>
        </div>

        <div class="mt-5 flex flex-col gap-2 sm:flex-row">
            <a href="{{ route('daftar-akun') }}" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-[var(--primer)] px-5 py-3 font-semibold text-white hover:bg-[var(--primer-gelap)]">
                Daftar sekarang
            </a>
            <a href="{{ route('beranda') }}" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-[var(--garis)] px-5 py-3 font-semibold text-[var(--teks)] hover:bg-gray-50">
                Lihat sekolah lain
            </a>
        </div>
    </section>
@endsection
