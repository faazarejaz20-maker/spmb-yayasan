@extends('layouts.walisantri')

@section('judul', 'Daftarkan Anak')

@section('isi')
    @php
        $alur = [
            1 => [
                'judul' => 'Pilih sekolah',
                'isi' => 'Tentukan sekolah, gelombang, dan program bila diminta.',
                'ikon' => 'gedung',
            ],
            2 => [
                'judul' => 'Data anak',
                'isi' => 'Nama lengkap, NIK, tanggal lahir, dan jenis kelamin.',
                'ikon' => 'pengguna',
            ],
            3 => [
                'judul' => 'Alamat',
                'isi' => 'Alamat tempat tinggal dan data orang tua atau wali.',
                'ikon' => 'peta',
            ],
            4 => [
                'judul' => 'Kesehatan',
                'isi' => 'Riwayat penyakit, alergi, dan kebutuhan khusus.',
                'ikon' => 'perisai',
            ],
            5 => [
                'judul' => 'Asal sekolah',
                'isi' => 'Asal sekolah sebelumnya, atau pilih "Belum sekolah".',
                'ikon' => 'papan-clip',
            ],
            6 => [
                'judul' => 'Berkas',
                'isi' => 'Unggah akta, Kartu Keluarga, dan pas foto anak.',
                'ikon' => 'dokumen',
            ],
            7 => [
                'judul' => 'Biaya',
                'isi' => 'Lihat rincian biaya pendidikan dan pilih IAP bila ada.',
                'ikon' => 'uang',
            ],
            8 => [
                'judul' => 'Kirim',
                'isi' => 'Periksa ringkasan, centang pernyataan, lalu kirim.',
                'ikon' => 'centang',
            ],
        ];
    @endphp

    <div class="space-y-4">
        <div class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-[var(--garis)]">
            <div class="pola-hero bg-[var(--primer)] px-5 py-6 text-white">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-white/75">Langkah 1 dari 8</p>
                <h1 class="mt-1 text-xl font-bold">Daftarkan anak</h1>
                <p class="mt-2 max-w-md text-sm leading-relaxed text-white/85">
                    Pilih sekolah dan gelombang yang sedang dibuka. Satu anak boleh didaftarkan ke satu sekolah pada satu gelombang.
                </p>

                <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1">
                        <x-ikon nama="jam" kelas="h-4 w-4" />
                        Bisa dikerjakan bertahap
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1">
                        <x-ikon nama="centang" kelas="h-4 w-4" />
                        Bisa disimpan sebagai draf
                    </span>
                </div>
            </div>
        </div>

        <section aria-labelledby="judul-alur" class="rounded-2xl border border-[var(--garis)] bg-white p-5 shadow-sm">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-[var(--primer)]">
                    <x-ikon nama="papan-clip" />
                </span>

                <div class="min-w-0">
                    <h2 id="judul-alur" class="font-bold text-[var(--teks)]">Alur pendaftaran</h2>
                    <p class="mt-0.5 text-sm leading-relaxed text-[var(--teks-sekunder)]">
                        Delapan langkah, semuanya bisa disimpan sebagai draf. Halaman ini adalah langkah pertama.
                    </p>
                </div>
            </div>

            <ol class="mt-4 grid gap-2 sm:grid-cols-2">
                @foreach ($alur as $nomor => $langkah)
                    <li class="flex items-start gap-3 rounded-xl border border-[var(--garis)] bg-[var(--latar)]/50 p-3">
                        <span @class([
                            'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                            'bg-[var(--primer)] text-white' => $nomor === 1,
                            'bg-white text-[var(--primer)] ring-1 ring-[var(--garis)]' => $nomor !== 1,
                        ])>
                            {{ $nomor }}
                        </span>

                        <span class="min-w-0">
                            <span class="flex items-center gap-1.5 text-sm font-semibold text-[var(--teks)]">
                                <x-ikon :nama="$langkah['ikon']" kelas="h-4 w-4 shrink-0 text-[var(--primer)]" />
                                {{ $langkah['judul'] }}
                                @if ($nomor === 1)
                                    <span class="rounded-full bg-teal-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-teal-800">Sekarang</span>
                                @endif
                            </span>
                            <span class="mt-0.5 block text-xs leading-relaxed text-[var(--teks-sekunder)]">{{ $langkah['isi'] }}</span>
                        </span>
                    </li>
                @endforeach
            </ol>
        </section>

        <section aria-labelledby="judul-siapkan" class="rounded-2xl border border-[var(--garis)] bg-white p-5 shadow-sm">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-[var(--aksen)]">
                    <x-ikon nama="dokumen" />
                </span>

                <div class="min-w-0">
                    <h2 id="judul-siapkan" class="font-bold text-[var(--teks)]">Siapkan dari sekarang</h2>
                    <p class="mt-0.5 text-sm leading-relaxed text-[var(--teks-sekunder)]">
                        Tidak harus lengkap hari ini. Berkas dapat diunggah nanti pada langkah keenam.
                    </p>
                </div>
            </div>

            <ul class="mt-4 flex flex-wrap gap-2 text-sm">
                @foreach (['Kartu Keluarga (KK)', 'Akta kelahiran', 'Pas foto anak', 'Surat keterangan sehat (bila diminta)'] as $siapkan)
                    <li class="inline-flex items-center gap-1.5 rounded-full border border-[var(--garis)] bg-[var(--latar)] px-3 py-1.5 text-[var(--teks)]">
                        <x-ikon nama="centang" kelas="h-3.5 w-3.5 text-[var(--primer)]" />
                        {{ $siapkan }}
                    </li>
                @endforeach
            </ul>

            <p class="mt-3 flex items-start gap-2 text-xs leading-relaxed text-[var(--teks-sekunder)]">
                <x-ikon nama="foto" kelas="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                <span>Berkas boleh difoto langsung dengan kamera HP. Format JPG, PNG, atau PDF, maksimal 2 MB per berkas.</span>
            </p>
        </section>

        @if ($gelombang->isEmpty())
            <x-kosong
                judul="Pendaftaran belum dibuka"
                keterangan="Belum ada gelombang yang dibuka saat ini. Silakan cek kembali nanti atau hubungi sekolah."
            />
        @else
            @foreach ($gelombang as $g)
                @php
                    $butuhProgram = $g->sekolah->kode === config('spmb.nomor_peserta.kode_program');
                    $daftarAnak = $anak;
                    $berkasSekolah = $jenisBerkas->get($g->sekolah_id, collect());
                    $berkasWajib = $berkasSekolah->where('wajib', true);
                @endphp

                <form method="POST" action="{{ route('walisantri.daftar.baru.simpan') }}"
                      class="overflow-hidden rounded-2xl border border-[var(--garis)] bg-white shadow-sm">
                    @csrf
                    <input type="hidden" name="sekolah_id" value="{{ $g->sekolah_id }}">
                    <input type="hidden" name="gelombang_id" value="{{ $g->id }}">

                    <div class="flex flex-wrap items-center gap-3 border-b border-[var(--garis)] bg-[var(--latar)]/60 p-5">
                        <x-logo-sekolah :kode="$g->sekolah->kode" :nama="$g->sekolah->nama" kelas="h-12 w-12" />

                        <div class="min-w-0 flex-1">
                            <h2 class="text-base font-bold leading-snug text-[var(--teks)]">{{ $g->sekolah->nama }}</h2>
                            <p class="mt-0.5 text-sm text-[var(--teks-sekunder)]">
                                {{ $g->nama }}
                                @if ($g->tanggal_tutup)
                                    · ditutup {{ $g->tanggal_tutup->translatedFormat('j F Y') }}
                                @endif
                            </p>
                        </div>

                        <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-800">
                            <span class="h-1.5 w-1.5 rounded-full bg-green-500" aria-hidden="true"></span>
                            Dibuka
                        </span>
                    </div>

                    <div class="space-y-4 p-5">
                        <details class="group rounded-xl border border-[var(--garis)] bg-[var(--latar)]/40 p-3">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold text-[var(--teks)] [&::-webkit-details-marker]:hidden">
                                <span class="flex items-center gap-2">
                                    <x-ikon nama="dokumen" kelas="h-4 w-4 text-[var(--primer)]" />
                                    Berkas yang perlu disiapkan
                                </span>
                                <span class="flex items-center gap-2">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-1 text-xs font-bold text-[var(--primer)] ring-1 ring-[var(--garis)]">
                                        {{ $berkasWajib->count() }} wajib
                                    </span>
                                    <x-ikon nama="panah-kanan" kelas="h-4 w-4 shrink-0 text-gray-400 transition group-open:rotate-90" />
                                </span>
                            </summary>

                            <div class="mt-3 space-y-2">
                                @forelse ($berkasSekolah as $berkas)
                                    <div class="flex items-start justify-between gap-3 rounded-lg bg-white px-3 py-2 text-sm">
                                        <span class="min-w-0">
                                            <span class="block font-semibold text-[var(--teks)]">{{ $berkas->nama }}</span>
                                            <span class="block text-xs text-[var(--teks-sekunder)]">{{ $berkas->jumlah_lembar }} lembar</span>
                                        </span>

                                        @if ($berkas->wajib)
                                            <span class="shrink-0 rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[var(--bahaya)]">Wajib</span>
                                        @else
                                            <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-gray-600">Opsional</span>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-sm leading-relaxed text-[var(--teks-sekunder)]">
                                        Sekolah belum menetapkan daftar berkas. Panitia akan memberi tahu bila ada tambahan.
                                    </p>
                                @endforelse

                                <p class="text-xs leading-relaxed text-[var(--teks-sekunder)]">
                                    Berkas diunggah pada langkah keenam. Bisa difoto dengan HP, maksimal 2 MB per berkas.
                                </p>
                            </div>
                        </details>

                        @if ($butuhProgram)
                            <x-pilihan
                                name="program"
                                label="Program"
                                :opsi="['KB' => 'KB (Kelompok Bermain)', 'TK' => 'TK (Taman Kanak-kanak)', 'TPA' => 'TPA (Taman Penitipan Anak)']"
                                wajib
                            />
                        @endif

                        @if ($daftarAnak->isEmpty())
                            <input type="hidden" name="anak_baru" value="1">

                            <p class="flex items-start gap-2 rounded-xl bg-[var(--latar)] p-3 text-sm text-[var(--teks-sekunder)]">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-[var(--primer)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                </svg>
                                <span>Anda belum memiliki data anak. Setelah menekan tombol di bawah, Anda akan mengisi data anak terlebih dahulu.</span>
                            </p>

                            <x-tombol class="w-full">Isi data anak baru</x-tombol>
                        @else
                            <fieldset>
                                <legend class="mb-2 text-sm font-semibold text-[var(--teks)]">Pilih anak</legend>

                                <div class="space-y-2">
                                    @foreach ($daftarAnak as $a)
                                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-[var(--garis)] p-3 transition hover:bg-[var(--latar)] has-[:checked]:border-[var(--primer)] has-[:checked]:bg-teal-50">
                                            <input type="radio" name="anak_id" value="{{ $a->id }}" class="mt-1 accent-[var(--primer)]" data-pilih-anak>
                                            <span class="min-w-0">
                                                <span class="block truncate font-semibold text-[var(--teks)]">{{ $a->nama_lengkap }}</span>
                                                <span class="block text-xs text-[var(--teks-sekunder)]">
                                                    Lahir {{ $a->tanggal_lahir?->format('d-m-Y') ?? '—' }}
                                                </span>
                                            </span>
                                        </label>
                                    @endforeach

                                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-dashed border-[var(--garis)] p-3 transition hover:bg-[var(--latar)] has-[:checked]:border-[var(--primer)] has-[:checked]:bg-teal-50">
                                        <input type="radio" name="anak_baru" value="1" class="mt-1 accent-[var(--primer)]" data-pilih-anak>
                                        <span class="min-w-0">
                                            <span class="block font-semibold text-[var(--teks)]">Anak baru</span>
                                            <span class="block text-xs text-[var(--teks-sekunder)]">Tambahkan anak lain ke akun ini.</span>
                                        </span>
                                    </label>
                                </div>
                            </fieldset>

                            <x-tombol class="w-full">Lanjut</x-tombol>
                        @endif
                    </div>
                </form>
            @endforeach
        @endif

        <section aria-labelledby="judul-catatan" class="rounded-2xl border border-[var(--garis)] bg-white p-5 shadow-sm">
            <p class="flex items-center gap-2 font-semibold text-[var(--teks)]">
                <x-ikon nama="seru" kelas="h-5 w-5 text-[var(--aksen)]" />
                Catatan saat mengisi
            </p>

            <ul class="mt-3 space-y-2 text-sm leading-relaxed text-[var(--teks-sekunder)]">
                <li class="flex items-start gap-2">
                    <x-ikon nama="centang" kelas="mt-0.5 h-4 w-4 shrink-0 text-[var(--primer)]" />
                    <span>Draf tersimpan setiap kali Anda menekan <span class="font-semibold text-[var(--teks)]">Simpan &amp; lanjut</span> atau <span class="font-semibold text-[var(--teks)]">Simpan draf</span>.</span>
                </li>
                <li class="flex items-start gap-2">
                    <x-ikon nama="centang" kelas="mt-0.5 h-4 w-4 shrink-0 text-[var(--primer)]" />
                    <span>Ingin berhenti dulu? Tekan <span class="font-semibold text-[var(--teks)]">Simpan &amp; keluar</span>, lalu lanjutkan dari beranda kapan saja.</span>
                </li>
                <li class="flex items-start gap-2">
                    <x-ikon nama="centang" kelas="mt-0.5 h-4 w-4 shrink-0 text-[var(--primer)]" />
                    <span>Bagian yang bertanda <span class="font-bold text-[var(--bahaya)]">*</span> wajib diisi. Bila ragu, kosongkan dulu dan tanyakan kepada panitia.</span>
                </li>
            </ul>
        </section>

        <section aria-labelledby="judul-bantuan" class="rounded-2xl border border-[var(--garis)] bg-white p-5 shadow-sm">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-[var(--primer)]">
                    <x-ikon nama="telepon" />
                </span>

                <div class="min-w-0">
                    <h2 id="judul-bantuan" class="font-bold text-[var(--teks)]">Butuh bantuan?</h2>
                    <p class="mt-1 text-sm leading-relaxed text-[var(--teks-sekunder)]">
                        Hubungi panitia sekolah tujuan lewat WhatsApp atau Instagram. Nomor dan alamatnya ada di beranda akun Anda.
                    </p>

                    <a href="{{ url('/akun') }}#judul-kontak"
                       class="mt-3 inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-[var(--garis)] px-4 text-sm font-semibold text-[var(--primer)] transition hover:bg-[var(--latar)]">
                        <x-ikon nama="peta" kelas="h-4 w-4" />
                        Lihat kontak sekolah
                    </a>
                </div>
            </div>
        </section>
    </div>
@endsection
