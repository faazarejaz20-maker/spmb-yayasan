@extends('layouts.panel')

@section('judul', 'Detail Pendaftar')

@section('isi')
    @php
        $anak = $pendaftaran->anak;
        $statusHasil = $pendaftaran->status_hasil;
        $bolehPutuskan = count($pilihanStatus) > 0;
        $ringkasPendaftaran = collect([
            $pendaftaran->sekolah?->nama,
            $pendaftaran->program ? 'Program '.$pendaftaran->program : null,
            $pendaftaran->gelombang?->nama,
        ])->filter()->implode(' · ');
    @endphp

    <div class="space-y-4">
        <div class="rounded-2xl border border-[var(--garis)] bg-white p-5 shadow-sm">
            <x-kepala-halaman
                :judul="$anak?->nama_lengkap ?? 'Tanpa nama'"
                :sekolah="$ringkasPendaftaran"
                :kembali="route('panitia.pendaftar.index')"
                labelKembali="Daftar pendaftar"
            >
                <p class="mt-1 text-sm text-[var(--teks-sekunder)]">
                    Nomor peserta:
                    <span class="font-mono font-semibold text-[var(--teks)]">{{ $pendaftaran->nomor_peserta ?? '—' }}</span>
                </p>

                <div class="mt-3 flex flex-wrap gap-2">
                    <x-tautan-tombol :href="route('panitia.pendaftar.cetak.formulir', $pendaftaran->uuid)" target="_blank" rel="noopener" tipe="sekunder" ukuran="kecil">
                        Cetak Formulir
                    </x-tautan-tombol>
                    <x-tautan-tombol :href="route('panitia.pendaftar.zip', $pendaftaran->uuid)" tipe="sekunder" ukuran="kecil">
                        Unduh Berkas (ZIP)
                    </x-tautan-tombol>
                    <x-tautan-tombol :href="route('panitia.pendaftar.cetak.bukti', $pendaftaran->uuid)" target="_blank" rel="noopener" tipe="sekunder" ukuran="kecil">
                        Cetak Bukti
                    </x-tautan-tombol>
                </div>

                @if ($errors->has('zip'))
                    <p class="mt-2 text-xs font-semibold text-[var(--bahaya)]">{{ $errors->first('zip') }}</p>
                @endif

                <x-slot:aksi>
                    <div class="flex flex-col items-start gap-1 sm:items-end">
                        <x-lencana-status :label="$statusHasil?->label() ?? '—'" />
                        @if ($pendaftaran->status_murid)
                            <x-lencana-status :label="$pendaftaran->status_murid->label()" />
                        @endif
                        <p class="text-xs text-[var(--teks-sekunder)]">
                            Dikirim {{ $pendaftaran->dikirim_pada?->format('d-m-Y H:i') ?? '—' }}
                        </p>
                    </div>
                </x-slot:aksi>
            </x-kepala-halaman>

            <div class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                <div class="rounded-xl bg-[var(--latar)] p-3">
                    <p class="text-xs text-[var(--teks-sekunder)]">Mode penerimaan</p>
                    <p class="font-semibold text-[var(--teks)]">{{ $pendaftaran->sekolah?->mode_penerimaan?->label() ?? '—' }}</p>
                </div>
                <div class="rounded-xl bg-[var(--latar)] p-3">
                    <p class="text-xs text-[var(--teks-sekunder)]">Kuota terisi</p>
                    <p class="font-semibold text-[var(--teks)]">
                        {{ $kuotaTerisi }}@if ($pendaftaran->gelombang?->kuota !== null) / {{ $pendaftaran->gelombang?->kuota }} @else <span class="text-xs font-normal">(tanpa batas)</span> @endif
                    </p>
                </div>
                <div class="rounded-xl bg-[var(--latar)] p-3">
                    <p class="text-xs text-[var(--teks-sekunder)]">Sisa kuota</p>
                    <p class="font-semibold text-[var(--teks)]">{{ $kuotaSisa === null ? 'Tanpa batas' : max(0, $kuotaSisa) }}</p>
                </div>
                <div class="rounded-xl bg-[var(--latar)] p-3">
                    <p class="text-xs text-[var(--teks-sekunder)]">
                        {{ $posisiGelombang !== null ? 'Posisi kirim' : 'Jenis pendaftar' }}
                    </p>
                    <p class="font-semibold text-[var(--teks)]">
                        @if ($posisiGelombang !== null)
                            Ke-{{ $posisiGelombang }}
                        @else
                            {{ $pendaftaran->lulusan_yayasan ? 'Lulusan yayasan' : 'Siswa baru' }}
                        @endif
                    </p>
                </div>
            </div>

            @if ($wajibBelumSiap > 0 || $adaPerluPerbaikan)
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    @if ($adaPerluPerbaikan)
                        Ada berkas yang ditandai perlu perbaikan.
                    @endif
                    @if ($wajibBelumSiap > 0)
                        {{ $wajibBelumSiap }} berkas wajib belum diunggah atau belum diperiksa.
                    @endif
                </div>
            @endif
        </div>

        {{-- Papan keputusan --}}
        <div class="rounded-2xl border border-[var(--garis)] bg-white p-5 shadow-sm">
            <h2 class="text-sm font-bold text-[var(--teks)]">Keputusan hasil seleksi</h2>

            @if ($catatanPerbaikan && $statusHasil === \App\Enums\StatusHasil::PerluPerbaikan)
                <div class="mt-3 rounded-xl border border-orange-200 bg-orange-50 p-3 text-sm text-orange-900">
                    <p class="font-semibold">Catatan perbaikan yang tampil ke walisantri</p>
                    <p class="mt-1 whitespace-pre-line">{{ $catatanPerbaikan }}</p>
                </div>
            @endif

            @if ($bolehPutuskan)
                <form method="POST" action="{{ route('panitia.pendaftar.keputusan', $pendaftaran->uuid) }}" class="mt-3 space-y-3">
                    @csrf

                    @error('keputusan')
                        <p class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p>
                    @enderror

                    <div>
                        <label for="status" class="mb-1 block text-sm font-semibold text-[var(--teks)]">Status tujuan</label>
                        <select id="status" name="status" required
                                class="min-h-11 w-full rounded-xl border border-[var(--garis)] bg-white px-3 text-sm">
                            <option value="">Pilih status</option>
                            @foreach ($pilihanStatus as $pilihan)
                                <option value="{{ $pilihan['status']->value }}" @disabled(! $pilihan['aktif'])>
                                    {{ $pilihan['label'] }}{{ $pilihan['alasan'] ? ' — '.$pilihan['alasan'] : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="catatan" class="mb-1 block text-sm font-semibold text-[var(--teks)]">Catatan</label>
                        <textarea id="catatan" name="catatan" rows="3" maxlength="2000"
                                  class="w-full rounded-xl border border-[var(--garis)] bg-white px-3 py-2 text-sm"
                                  placeholder="Wajib diisi untuk Perlu perbaikan dan perubahan setelah hasil diumumkan."></textarea>
                    </div>

                    @if ($wajibBelumSiap > 0)
                        <label class="flex items-start gap-3 text-sm text-[var(--teks)]">
                            <input type="checkbox" name="konfirmasi_berkas" value="1" class="mt-1">
                            <span>
                                Saya sudah memeriksa {{ $wajibBelumSiap }} berkas wajib yang belum siap dan tetap melanjutkan verifikasi.
                            </span>
                        </label>
                    @endif

                    <x-tombol>Simpan keputusan</x-tombol>
                </form>
            @else
                <p class="mt-2 text-sm text-[var(--teks-sekunder)]">
                    @if (\App\Services\StatusHasilService::hasilAkhir($statusHasil))
                        Hasil untuk pendaftar ini sudah final. Perubahan setelah pengumuman hanya dapat dilakukan admin sekolah dengan catatan.
                    @else
                        Tidak ada perpindahan status yang tersedia dari status saat ini.
                    @endif
                </p>
            @endif
        </div>

        <x-tab :aktif="$tab" :item="[
            'data' => ['label' => 'Data', 'url' => route('panitia.pendaftar.detail', ['pendaftaran' => $pendaftaran->uuid, 'tab' => 'data'])],
            'berkas' => ['label' => 'Berkas', 'url' => route('panitia.pendaftar.detail', ['pendaftaran' => $pendaftaran->uuid, 'tab' => 'berkas']), 'lencana' => $pendaftaran->berkas->count()],
            'pembayaran' => ['label' => 'Pembayaran', 'url' => route('panitia.pendaftar.detail', ['pendaftaran' => $pendaftaran->uuid, 'tab' => 'pembayaran']), 'lencana' => $pendaftaran->pembayaran->count()],
            'daftar-ulang' => ['label' => 'Daftar ulang', 'url' => route('panitia.pendaftar.detail', ['pendaftaran' => $pendaftaran->uuid, 'tab' => 'daftar-ulang'])],
            'riwayat' => ['label' => 'Riwayat', 'url' => route('panitia.pendaftar.detail', ['pendaftaran' => $pendaftaran->uuid, 'tab' => 'riwayat'])],
        ]" />

        @include('panitia.pendaftar.tab-'.$tab)
    </div>
@endsection
