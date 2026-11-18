@extends('layouts.panel')

@section('judul', 'Ekspor Data Pendaftar')

@section('isi')
    @php
        $programTki = (array) config('spmb.nomor_peserta.program_tki');
        $bolehSensitif = auth()->user()?->peran?->value === 'admin';
        $kolomLama = old('kolom', []);
    @endphp

    <div class="space-y-4">
        <x-kepala-halaman
            judul="Ekspor data pendaftar"
            :sekolah="$sekolah?->nama"
            :kembali="route('panitia.pendaftar.index')"
            labelKembali="Daftar pendaftar"
            :keterangan="'Hanya pendaftaran terkirim yang diekspor, maksimal '.number_format((int) config('ekspor.batas_baris'), 0, ',', '.').' baris per unduhan. Setiap unduhan dicatat di log sistem (pelaku, penyaring, jumlah baris, dan daftar kolom).'"
        />

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-900">
                @foreach ($errors->all() as $galat)
                    <p>{{ $galat }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('panitia.ekspor.unduh') }}" class="space-y-4">
            @csrf

            {{-- Penyaring --}}
            <div class="rounded-2xl border border-[var(--garis)] bg-white p-4 shadow-sm">
                <h2 class="text-sm font-bold text-[var(--teks)]">Penyaring</h2>
                <p class="mt-1 text-xs text-[var(--teks-sekunder)]">Sama dengan penyaring di daftar pendaftar.</p>

                <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="sm:col-span-2">
                        <label for="cari" class="mb-1 block text-xs font-semibold text-[var(--teks)]">Cari nama atau nomor peserta</label>
                        <input type="search" id="cari" name="saring[cari]" value="{{ $saring['cari'] }}" maxlength="150"
                               class="min-h-11 w-full rounded-xl border border-[var(--garis)] bg-white px-3 text-sm text-[var(--teks)]">
                    </div>

                    <div>
                        <label for="gelombang" class="mb-1 block text-xs font-semibold text-[var(--teks)]">Gelombang</label>
                        <select id="gelombang" name="saring[gelombang]" class="min-h-11 w-full rounded-xl border border-[var(--garis)] bg-white px-3 text-sm">
                            <option value="">Semua gelombang</option>
                            @foreach ($gelombang as $g)
                                <option value="{{ $g->id }}" @selected((string) $saring['gelombang'] === (string) $g->id)>{{ $g->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="status" class="mb-1 block text-xs font-semibold text-[var(--teks)]">Status hasil</label>
                        <select id="status" name="saring[status]" class="min-h-11 w-full rounded-xl border border-[var(--garis)] bg-white px-3 text-sm">
                            <option value="">Semua status</option>
                            @foreach (\App\Enums\StatusHasil::cases() as $s)
                                <option value="{{ $s->value }}" @selected($saring['status'] === $s->value)>{{ $s->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="jenis" class="mb-1 block text-xs font-semibold text-[var(--teks)]">Jenis pendaftar</label>
                        <select id="jenis" name="saring[jenis]" class="min-h-11 w-full rounded-xl border border-[var(--garis)] bg-white px-3 text-sm">
                            <option value="">Semua</option>
                            <option value="baru" @selected($saring['jenis'] === 'baru')>Siswa baru</option>
                            <option value="lulusan" @selected($saring['jenis'] === 'lulusan')>Lulusan yayasan</option>
                        </select>
                    </div>

                    <div>
                        <label for="pembayaran" class="mb-1 block text-xs font-semibold text-[var(--teks)]">Pembayaran</label>
                        <select id="pembayaran" name="saring[pembayaran]" class="min-h-11 w-full rounded-xl border border-[var(--garis)] bg-white px-3 text-sm">
                            <option value="">Semua</option>
                            <option value="belum" @selected($saring['pembayaran'] === 'belum')>Belum ada</option>
                            <option value="menunggu" @selected($saring['pembayaran'] === 'menunggu')>Menunggu</option>
                            <option value="disetujui" @selected($saring['pembayaran'] === 'disetujui')>Disetujui</option>
                            <option value="ditolak" @selected($saring['pembayaran'] === 'ditolak')>Ditolak</option>
                        </select>
                    </div>

                    <div>
                        <label for="kelengkapan" class="mb-1 block text-xs font-semibold text-[var(--teks)]">Kelengkapan berkas</label>
                        <select id="kelengkapan" name="saring[kelengkapan]" class="min-h-11 w-full rounded-xl border border-[var(--garis)] bg-white px-3 text-sm">
                            <option value="">Semua</option>
                            <option value="perlu_perbaikan" @selected($saring['kelengkapan'] === 'perlu_perbaikan')>Ada yang perlu perbaikan</option>
                            <option value="lengkap" @selected($saring['kelengkapan'] === 'lengkap')>Tidak ada perbaikan</option>
                        </select>
                    </div>

                    @if ($sekolah?->kode === config('spmb.nomor_peserta.kode_program'))
                        <div>
                            <label for="program" class="mb-1 block text-xs font-semibold text-[var(--teks)]">Program</label>
                            <select id="program" name="saring[program]" class="min-h-11 w-full rounded-xl border border-[var(--garis)] bg-white px-3 text-sm">
                                <option value="">Semua program</option>
                                @foreach ($programTki as $p)
                                    <option value="{{ $p }}" @selected(strtoupper($saring['program']) === $p)>{{ $p }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Kolom --}}
            <div class="rounded-2xl border border-[var(--garis)] bg-white p-4 shadow-sm">
                <h2 class="text-sm font-bold text-[var(--teks)]">Kolom</h2>
                <p class="mt-1 text-xs text-[var(--teks-sekunder)]">Pilih kolom yang akan dimuat. Nomor peserta selalu ikut.</p>

                <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($grup as $kunciGrup => $grupData)
                        <fieldset class="rounded-xl border border-[var(--garis)] p-3">
                            <legend class="px-1 text-xs font-bold uppercase tracking-wide text-[var(--teks-sekunder)]">{{ $grupData['label'] }}</legend>

                            <div class="space-y-1.5">
                                @foreach ($grupData['kolom'] as $kunciKolom => $definisi)
                                    <label class="flex items-start gap-2 text-sm text-[var(--teks)]">
                                        <input type="checkbox" name="kolom[{{ $kunciKolom }}]" value="1"
                                               @checked(isset($kolomLama[$kunciKolom]) || ! old())
                                               class="mt-0.5 h-4 w-4 rounded border-[var(--garis)]">
                                        <span>{{ $definisi['label'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>
            </div>

            {{-- Kolom sensitif (admin saja) --}}
            @if ($bolehSensitif)
                <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4">
                    <h2 class="text-sm font-bold text-amber-900">Data pribadi sensitif</h2>
                    <p class="mt-1 text-xs text-amber-900">
                        Kolom ini tidak ikut bawaan. Hanya admin yang dapat memilihnya dan wajib menyatakan pemahaman.
                        NIK dan nomor KK diekspor sebagai teks.
                    </p>

                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        @foreach ($sensitif as $grupData)
                            <fieldset class="rounded-xl border border-amber-300 bg-white/70 p-3">
                                <legend class="px-1 text-xs font-bold uppercase tracking-wide text-amber-900">{{ $grupData['label'] }}</legend>

                                <div class="space-y-1.5">
                                    @foreach ($grupData['kolom'] as $kunciKolom => $definisi)
                                        <label class="flex items-start gap-2 text-sm text-amber-900">
                                            <input type="checkbox" name="kolom[{{ $kunciKolom }}]" value="1"
                                                   @checked(isset($kolomLama[$kunciKolom]))
                                                   class="mt-0.5 h-4 w-4 rounded border-amber-400">
                                            <span>{{ $definisi['label'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>

                    <label class="mt-3 flex items-start gap-2 text-sm font-semibold text-amber-900">
                        <input type="checkbox" name="konfirmasi_sensitif" value="1" @checked(old('konfirmasi_sensitif'))
                               class="mt-0.5 h-4 w-4 rounded border-amber-500">
                        <span>{{ $konfirmasiSensitif }}</span>
                    </label>
                </div>
            @endif

            {{-- Format --}}
            <div class="rounded-2xl border border-[var(--garis)] bg-white p-4 shadow-sm">
                <h2 class="text-sm font-bold text-[var(--teks)]">Format</h2>

                <div class="mt-2 flex flex-wrap gap-4 text-sm text-[var(--teks)]">
                    <label class="flex items-center gap-2">
                        <input type="radio" name="format" value="xlsx" @checked(old('format', 'xlsx') === 'xlsx')>
                        <span>Excel (.xlsx)</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" name="format" value="csv" @checked(old('format') === 'csv')>
                        <span>CSV (.csv, UTF-8)</span>
                    </label>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <x-tombol>Unduh</x-tombol>
                <x-tautan-tombol :href="route('panitia.pendaftar.index')" tipe="sekunder">Batal</x-tautan-tombol>
            </div>
        </form>
    </div>
@endsection
