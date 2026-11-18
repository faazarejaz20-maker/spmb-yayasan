@extends('layouts.walisantri')

@section('judul', 'Beranda')

@section('isi')
    <div class="space-y-5">
        <section class="pola-hero relative overflow-hidden rounded-3xl bg-[var(--primer)] p-6 text-white shadow-sm">
            <div class="absolute -right-10 -top-12 h-36 w-36 rounded-full bg-white/10" aria-hidden="true"></div>
            <div class="absolute -bottom-16 right-8 h-32 w-32 rounded-full bg-[var(--aksen)]/20" aria-hidden="true"></div>

            <div class="relative">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-white/75">Assalamu'alaikum</p>
                <h1 class="mt-1 text-2xl font-bold leading-tight">{{ auth()->user()->nama }}</h1>
                <p class="mt-2 max-w-md text-sm leading-relaxed text-white/85">
                    Kelola pendaftaran anak Anda di sini. Data Anda hanya terlihat oleh Anda dan panitia sekolah.
                </p>

                <div class="mt-5 flex flex-wrap gap-2">
                    <a href="{{ route('walisantri.daftar.baru') }}"
                       class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-white px-5 text-sm font-bold text-[var(--primer)] shadow-sm transition hover:bg-teal-50">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Daftarkan anak
                    </a>
                    <a href="{{ route('walisantri.orang-tua') }}"
                       class="inline-flex min-h-11 items-center justify-center rounded-xl border border-white/40 px-5 text-sm font-semibold text-white transition hover:bg-white/10">
                        Data orang tua
                    </a>
                </div>
            </div>
        </section>

        <dl class="grid grid-cols-3 gap-2 sm:gap-3">
            <div class="rounded-2xl border border-[var(--garis)] bg-white p-4 text-center shadow-sm">
                <dt class="text-[10px] font-bold uppercase tracking-wide text-[var(--teks-sekunder)] sm:text-[11px]">Anak</dt>
                <dd class="mt-1 text-2xl font-bold text-[var(--primer)]">{{ $ringkasan['anak'] }}</dd>
            </div>
            <div class="rounded-2xl border border-[var(--garis)] bg-white p-4 text-center shadow-sm">
                <dt class="text-[10px] font-bold uppercase tracking-wide text-[var(--teks-sekunder)] sm:text-[11px]">Pendaftaran</dt>
                <dd class="mt-1 text-2xl font-bold text-[var(--primer)]">{{ $ringkasan['pendaftaran'] }}</dd>
            </div>
            <div class="rounded-2xl border p-4 text-center shadow-sm {{ $ringkasan['tindakan'] > 0 ? 'border-amber-200 bg-amber-50' : 'border-[var(--garis)] bg-white' }}">
                <dt class="text-[10px] font-bold uppercase tracking-wide sm:text-[11px] {{ $ringkasan['tindakan'] > 0 ? 'text-amber-800' : 'text-[var(--teks-sekunder)]' }}">Perlu tindakan</dt>
                <dd class="mt-1 text-2xl font-bold {{ $ringkasan['tindakan'] > 0 ? 'text-amber-700' : 'text-[var(--primer)]' }}">{{ $ringkasan['tindakan'] }}</dd>
            </div>
        </dl>

        @if ($item->isEmpty())
            <x-kosong
                judul="Belum ada pendaftaran"
                keterangan="Pilih Daftarkan anak untuk memulai pendaftaran ke sekolah tujuan."
            />
        @else
            <section>
                <div class="flex flex-wrap items-end justify-between gap-2 px-1">
                    <h2 class="text-lg font-bold text-[var(--teks)]">Anak saya</h2>
                    <span class="text-xs font-semibold text-[var(--teks-sekunder)]">{{ $item->count() }} pendaftaran</span>
                </div>

                <div class="mt-3 space-y-3">
                    @foreach ($item as $baris)
                        <article class="overflow-hidden rounded-2xl border border-[var(--garis)] bg-white shadow-sm">
                            <div class="flex flex-wrap items-start justify-between gap-3 p-5">
                                <div class="flex min-w-0 items-start gap-3">
                                    <x-avatar :nama="$baris['nama_anak']" kelas="h-11 w-11 text-base" />

                                    <div class="min-w-0">
                                        <p class="truncate font-bold text-[var(--teks)]">{{ $baris['nama_anak'] }}</p>
                                        <p class="mt-1 flex min-w-0 items-center gap-1.5 text-sm text-[var(--teks-sekunder)]">
                                            <x-logo-sekolah :kode="$baris['kode_sekolah']" :nama="$baris['sekolah']" kelas="h-4 w-4" />
                                            <span class="truncate">
                                                {{ $baris['sekolah'] }}@if ($baris['program']) · Program {{ $baris['program'] }}@endif
                                            </span>
                                        </p>
                                    </div>
                                </div>

                                <x-lencana-status :label="$baris['status']->label" :warna="$baris['status']->warna" />
                            </div>

                            @if ($baris['kemajuan']['tampil'])
                                <div class="px-5">
                                    <x-kemajuan :langkah="$baris['kemajuan']['langkah']" :aktif="$baris['kemajuan']['aktif']" />
                                </div>
                            @endif

                            @if ($baris['status']->langkahBerikutnya)
                                <div class="mx-5 mt-4 flex items-start gap-2 rounded-xl bg-[var(--latar)] p-3">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-[var(--primer)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                    </svg>
                                    <p class="text-sm leading-snug text-[var(--teks-sekunder)]">{{ $baris['status']->langkahBerikutnya }}</p>
                                </div>
                            @endif

                            @if ($baris['nomor_peserta'])
                                <p class="mx-5 mt-3 text-xs text-[var(--teks-sekunder)]">
                                    Nomor peserta
                                    <span class="ml-1 inline-flex rounded-md bg-gray-100 px-2 py-0.5 font-mono text-xs font-semibold text-[var(--teks)]">{{ $baris['nomor_peserta'] }}</span>
                                </p>
                            @endif

                            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-[var(--garis)] bg-[var(--latar)]/60 px-5 py-4">
                                <a href="{{ $baris['aksi_utama']['url'] }}"
                                   class="inline-flex min-h-10 items-center justify-center rounded-xl bg-[var(--primer)] px-4 text-sm font-semibold text-white transition hover:bg-[var(--primer-gelap)]">
                                    {{ $baris['aksi_utama']['label'] }}
                                </a>

                                <a href="{{ route('walisantri.pendaftaran.detail', $baris['uuid']) }}"
                                   class="inline-flex min-h-10 items-center justify-center rounded-xl border border-[var(--garis)] bg-white px-4 text-sm font-semibold text-[var(--teks)] transition hover:bg-gray-50">
                                    Lihat detail
                                </a>

                                <a href="{{ route('walisantri.pendaftaran.hasil', $baris['uuid']) }}"
                                   class="inline-flex min-h-10 items-center justify-center rounded-xl px-3 text-sm font-semibold text-[var(--primer)] transition hover:bg-white">
                                    Hasil
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <section aria-labelledby="judul-kontak">
            <div class="px-1">
                <h2 id="judul-kontak" class="text-lg font-bold text-[var(--teks)]">Kontak sekolah</h2>
                <p class="mt-0.5 text-sm leading-relaxed text-[var(--teks-sekunder)]">
                    Hubungi sekolah tujuan lewat WhatsApp atau Instagram resmi di bawah ini.
                </p>
            </div>

            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                @foreach ($sekolahKontak as $sekolah)
                    <article class="flex flex-col rounded-2xl border border-[var(--garis)] bg-white p-5 shadow-sm">
                        <div class="flex items-start gap-3">
                            <x-logo-sekolah :kode="$sekolah['kode']" :nama="$sekolah['nama']" kelas="h-11 w-11" />

                            <div class="min-w-0">
                                <h3 class="font-bold leading-snug text-[var(--teks)]">{{ $sekolah['nama'] }}</h3>
                                <p class="text-xs text-[var(--teks-sekunder)]">{{ $sekolah['jenjang'] }}</p>
                            </div>
                        </div>

                        @if ($sekolah['alamat'])
                            <p class="mt-3 flex items-start gap-2 text-sm leading-snug text-[var(--teks-sekunder)]">
                                <x-ikon nama="peta" kelas="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                                <span>{{ $sekolah['alamat'] }}</span>
                            </p>
                        @endif

                        <div class="mt-4 flex flex-wrap gap-2">
                            @if ($sekolah['tautan_wa'])
                                <a href="{{ $sekolah['tautan_wa'] }}" target="_blank" rel="noopener noreferrer"
                                   class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-[var(--primer)] px-3.5 text-sm font-semibold text-white transition hover:bg-[var(--primer-gelap)]">
                                    <x-ikon nama="telepon" kelas="h-4 w-4" />
                                    WhatsApp {{ $sekolah['kontak_tampil'] }}
                                </a>
                            @endif

                            @if ($sekolah['tautan_instagram'])
                                <a href="{{ $sekolah['tautan_instagram'] }}" target="_blank" rel="noopener noreferrer"
                                   class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-[var(--garis)] px-3.5 text-sm font-semibold text-[var(--teks)] transition hover:bg-[var(--latar)]">
                                    <x-ikon nama="instagram" kelas="h-4 w-4" />
                                    {{ '@'.$sekolah['instagram'] }}
                                </a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="judul-prestasi">
            <div class="px-1">
                <h2 id="judul-prestasi" class="text-lg font-bold text-[var(--teks)]">Prestasi murid</h2>
                <p class="mt-0.5 text-sm leading-relaxed text-[var(--teks-sekunder)]">
                    Foto asli dari masing-masing sekolah. Bila sekolah belum mengirim foto, tampil "Foto prestasi menyusul".
                </p>
            </div>

            <div class="mt-3 grid gap-3 lg:grid-cols-2">
                @foreach ($sekolahKontak as $sekolah)
                    <article class="rounded-2xl border border-[var(--garis)] bg-white p-5 shadow-sm">
                        <div class="flex items-center gap-3">
                            <x-logo-sekolah :kode="$sekolah['kode']" :nama="$sekolah['nama']" kelas="h-9 w-9" />

                            <div class="min-w-0">
                                <h3 class="text-sm font-bold leading-snug text-[var(--teks)]">{{ $sekolah['nama'] }}</h3>
                                <p class="text-xs text-[var(--teks-sekunder)]">{{ $sekolah['jenjang'] }}</p>
                            </div>
                        </div>

                        @if ($sekolah['prestasi'] !== [])
                            <div class="mt-3 grid grid-cols-2 gap-2">
                                @foreach ($sekolah['prestasi'] as $foto)
                                    <figure class="overflow-hidden rounded-xl border border-[var(--garis)] bg-[var(--latar)]">
                                        <img
                                            src="{{ $foto['url'] }}"
                                            alt="{{ $foto['alt'] }}"
                                            width="{{ $foto['lebar'] ?? 480 }}"
                                            height="{{ $foto['tinggi'] ?? 320 }}"
                                            loading="lazy"
                                            decoding="async"
                                            class="aspect-[4/3] w-full object-cover"
                                        >
                                        <figcaption class="px-2.5 py-2 text-[11px] leading-snug text-[var(--teks-sekunder)]">
                                            {{ $foto['keterangan'] }}
                                        </figcaption>
                                    </figure>
                                @endforeach
                            </div>
                        @else
                            <div class="mt-3 flex items-center gap-2 rounded-xl border border-dashed border-[var(--garis)] px-3 py-7 text-sm text-[var(--teks-sekunder)]">
                                <x-ikon nama="foto" kelas="h-5 w-5 shrink-0 text-gray-300" />
                                <span>Foto prestasi {{ $sekolah['nama'] }} menyusul.</span>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    </div>
@endsection
