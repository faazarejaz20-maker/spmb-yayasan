@php
    $sudahDiterima = $pendaftaran->status_hasil === \App\Enums\StatusHasil::Diterima;
    $adaPembayaran = $pendaftaran->pembayaran->where('status', \App\Enums\StatusPembayaran::Disetujui)->isNotEmpty();
    $daftarUlang = $pendaftaran->daftarUlang;
    $mengundurkanDiri = $pendaftaran->status_murid === \App\Enums\StatusMurid::MengundurkanDiri;
@endphp

<div class="space-y-4">
    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-[var(--garis)] bg-white p-4">
            <p class="text-xs text-[var(--teks-sekunder)]">Status hasil</p>
            <p class="mt-1 text-sm font-bold text-[var(--teks)]">{{ $pendaftaran->status_hasil?->label() ?? '—' }}</p>
        </div>
        <div class="rounded-2xl border border-[var(--garis)] bg-white p-4">
            <p class="text-xs text-[var(--teks-sekunder)]">Pembayaran disetujui</p>
            <p class="mt-1 text-sm font-bold {{ $adaPembayaran ? 'text-[var(--primer)]' : 'text-[var(--bahaya)]' }}">
                {{ $adaPembayaran ? 'Sudah ada' : 'Belum ada' }}
            </p>
        </div>
        <div class="rounded-2xl border border-[var(--garis)] bg-white p-4">
            <p class="text-xs text-[var(--teks-sekunder)]">Status murid</p>
            <p class="mt-1 text-sm font-bold text-[var(--teks)]">{{ $pendaftaran->status_murid?->label() ?? 'Belum resmi' }}</p>
        </div>
    </div>

    @if (! $sudahDiterima)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            Daftar ulang hanya dapat dicatat untuk pendaftar yang sudah berstatus Diterima.
        </div>
    @elseif (! $adaPembayaran)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            Setujui bukti transfer atau catat pembayaran tunai terlebih dahulu sebelum mencatat daftar ulang.
        </div>
    @endif

    <x-kartu judul="Catatan daftar ulang dan wawancara">
        @if ($sudahDiterima)
            <form method="POST" action="{{ route('panitia.pendaftar.daftar-ulang', $pendaftaran->uuid) }}" class="space-y-3">
                @csrf
                @method('PUT')

                @error('daftar_ulang')
                    <p class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p>
                @enderror

                <label class="flex items-start gap-3 text-sm text-[var(--teks)]">
                    <input type="checkbox" name="hadir" value="1" class="mt-1" @checked(old('hadir', $daftarUlang?->hadir))>
                    <span>Walisantri hadir di sekolah untuk daftar ulang dan wawancara.</span>
                </label>

                <div>
                    <label for="kekurangan" class="mb-1 block text-sm font-semibold text-[var(--teks)]">Kekurangan berkas atau administrasi</label>
                    <textarea id="kekurangan" name="kekurangan" rows="3" maxlength="2000"
                              class="w-full rounded-xl border border-[var(--garis)] bg-white px-3 py-2 text-sm">{{ old('kekurangan', $daftarUlang?->kekurangan) }}</textarea>
                </div>

                <div>
                    <label for="catatan_wawancara" class="mb-1 block text-sm font-semibold text-[var(--teks)]">Catatan wawancara</label>
                    <textarea id="catatan_wawancara" name="catatan_wawancara" rows="5" maxlength="5000"
                              class="w-full rounded-xl border border-[var(--garis)] bg-white px-3 py-2 text-sm"
                              placeholder="Hanya terlihat panitia dan admin. Tidak pernah tampil di akun walisantri.">{{ old('catatan_wawancara', $daftarUlang?->catatan_wawancara) }}</textarea>
                </div>

                <label class="flex items-start gap-3 text-sm text-[var(--teks)]">
                    <input type="checkbox" name="selesai" value="1" class="mt-1" @checked(old('selesai', $daftarUlang?->selesai))>
                    <span>Tandai daftar ulang selesai. Pendaftar akan tercatat sebagai murid resmi.</span>
                </label>

                @if ($daftarUlang?->selesai && auth()->user()->peran === \App\Enums\PeranPengguna::Admin)
                    <div>
                        <label for="catatan" class="mb-1 block text-sm font-semibold text-[var(--teks)]">Alasan pembatalan (wajib bila membatalkan daftar ulang selesai)</label>
                        <input type="text" id="catatan" name="catatan" maxlength="2000" value="{{ old('catatan') }}"
                               class="min-h-11 w-full rounded-xl border border-[var(--garis)] bg-white px-3 text-sm">
                    </div>
                @elseif ($daftarUlang?->selesai)
                    <p class="text-xs text-[var(--teks-sekunder)]">
                        Daftar ulang sudah selesai. Pembatalan hanya dapat dilakukan admin sekolah.
                    </p>
                @endif

                <x-tombol>Simpan daftar ulang</x-tombol>
            </form>
        @endif

        @if ($daftarUlang)
            <div class="mt-4 border-t border-[var(--garis)] pt-3 text-xs text-[var(--teks-sekunder)]">
                Terakhir dicatat {{ $daftarUlang->updated_at?->format('d-m-Y H:i') ?? '—' }}.
            </div>
        @endif
    </x-kartu>

    @if ($sudahDiterima && ! $mengundurkanDiri)
        <x-kartu judul="Mengundurkan diri">
            <p class="text-sm text-[var(--teks-sekunder)]">
                Catat bila walisantri membatalkan pendaftaran. Kuota yang terpakai akan dibebaskan kembali.
            </p>

            @error('mengundurkan_diri')
                <p class="mt-2 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p>
            @enderror

            <div class="mt-3 space-y-2">
                <p class="text-xs text-[var(--teks-sekunder)]">Catatan alasan dapat diisi di dalam kotak konfirmasi.</p>

                <x-modal-konfirmasi
                    :aksi="route('panitia.pendaftar.mengundurkan-diri', $pendaftaran->uuid)"
                    metode="POST"
                    judul="Catat mengundurkan diri?"
                    teks="Status murid akan menjadi Mengundurkan diri dan kuota dibebaskan."
                    labelTombol="Ya, catat mengundurkan diri"
                >
                    <x-slot:isian>
                        <label for="catatan-mundur" class="mb-1 mt-3 block text-sm font-semibold text-[var(--teks)]">Catatan (opsional)</label>
                        <input type="text" id="catatan-mundur" name="catatan" maxlength="2000" value="{{ old('catatan') }}"
                               class="min-h-11 w-full rounded-xl border border-[var(--garis)] bg-white px-3 text-sm">
                    </x-slot:isian>

                    Catat mengundurkan diri
                </x-modal-konfirmasi>
            </div>
        </x-kartu>
    @endif
</div>
