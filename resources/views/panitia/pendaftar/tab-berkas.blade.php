@php
    $wajib = $jenisBerkas->where('wajib', true);
    $wajibSiap = $wajib->filter(fn ($j) => $berkasPerJenis->get($j->id)?->status === \App\Enums\StatusBerkas::Sesuai)->count();
@endphp

<div class="space-y-4">
    <div class="rounded-2xl border border-[var(--garis)] bg-white p-4">
        <div class="flex items-center justify-between text-sm">
            <span class="font-semibold text-[var(--teks)]">Berkas wajib sesuai</span>
            <span class="font-bold text-[var(--primer)]">{{ $wajibSiap }} / {{ $wajib->count() }}</span>
        </div>

        <progress class="kemajuan mt-2 w-full" value="{{ $wajibSiap }}" max="{{ max(1, $wajib->count()) }}">
            {{ $wajibSiap }} dari {{ $wajib->count() }} berkas wajib sesuai
        </progress>

        <p class="mt-2 text-xs text-[var(--teks-sekunder)]">
            Berkas tersimpan di tempat privat. Panitia dapat melihat dan mengganti berkas atas nama walisantri.
        </p>
    </div>

    @if ($jenisBerkas->isEmpty())
        <x-kosong judul="Belum ada jenis berkas" keterangan="Admin sekolah belum menetapkan berkas untuk sekolah ini." />
    @else
        <div class="space-y-3">
            @foreach ($jenisBerkas as $jenis)
                @php
                    $berkas = $berkasPerJenis->get($jenis->id);
                    $status = $berkas?->status;
                    $warna = match ($status?->value) {
                        'sesuai' => 'hijau',
                        'perlu_perbaikan' => 'oranye',
                        'menunggu' => 'biru',
                        default => 'netral',
                    };
                @endphp

                <div class="rounded-2xl border border-[var(--garis)] bg-white p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h3 class="text-sm font-bold text-[var(--teks)]">
                                {{ $jenis->nama }}
                                @if ($jenis->wajib)
                                    <span class="text-[var(--bahaya)]" aria-label="wajib">*</span>
                                @endif
                            </h3>
                            <p class="mt-0.5 text-xs text-[var(--teks-sekunder)]">{{ $jenis->jumlah_lembar }} lembar</p>
                        </div>

                        <x-lencana-status :label="$status?->label() ?? 'Belum diunggah'" :warna="$warna" />
                    </div>

                    @if ($berkas?->catatan)
                        <div class="mt-3 rounded-xl border border-[var(--garis)] bg-[var(--latar)] p-3 text-sm">
                            <p class="text-xs font-semibold text-[var(--teks-sekunder)]">Catatan pemeriksaan</p>
                            <p class="mt-1 whitespace-pre-line text-[var(--teks)]">{{ $berkas->catatan }}</p>
                        </div>
                    @endif

                    <div class="mt-3 flex flex-wrap gap-3 text-sm">
                        @if ($berkas)
                            <a href="{{ route('panitia.berkas.tampil', $berkas->uuid) }}" target="_blank" rel="noopener"
                               class="font-semibold text-[var(--primer)] hover:underline">
                                Lihat berkas
                            </a>
                        @else
                            <span class="text-[var(--teks-sekunder)]">Belum ada berkas.</span>
                        @endif
                    </div>

                    @if ($berkas && filled($berkas->path))
                        <form method="POST" action="{{ route('panitia.pendaftar.berkas.periksa', ['pendaftaran' => $pendaftaran->uuid, 'berkas' => $berkas->uuid]) }}"
                              class="mt-3 grid gap-2 border-t border-[var(--garis)] pt-3 sm:grid-cols-[10rem_1fr_auto] sm:items-end">
                            @csrf

                            <div>
                                <label for="status-{{ $berkas->uuid }}" class="mb-1 block text-xs font-semibold text-[var(--teks)]">Hasil pemeriksaan</label>
                                <select id="status-{{ $berkas->uuid }}" name="status" required
                                        class="min-h-11 w-full rounded-xl border border-[var(--garis)] bg-white px-3 text-sm">
                                    <option value="sesuai">Sesuai</option>
                                    <option value="perlu_perbaikan">Perlu perbaikan</option>
                                </select>
                            </div>

                            <div>
                                <label for="catatan-{{ $berkas->uuid }}" class="mb-1 block text-xs font-semibold text-[var(--teks)]">Catatan (wajib bila perlu perbaikan)</label>
                                <input type="text" id="catatan-{{ $berkas->uuid }}" name="catatan" maxlength="1000"
                                       class="min-h-11 w-full rounded-xl border border-[var(--garis)] bg-white px-3 text-sm">
                            </div>

                            <x-tombol tipe="sekunder" ukuran="kecil">Simpan</x-tombol>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('panitia.pendaftar.berkas.simpan', $pendaftaran->uuid) }}"
                          enctype="multipart/form-data" class="mt-3 border-t border-[var(--garis)] pt-3">
                        @csrf
                        <input type="hidden" name="jenis_berkas_id" value="{{ $jenis->id }}">

                        {{-- Satu tombol: membuka pemilih sistem (galeri/pengelola
                             berkas); formulir terkirim begitu berkas dipilih. --}}
                        <input id="unggah-{{ $jenis->id }}" type="file" name="berkas"
                               accept="image/jpeg,image/png,application/pdf"
                               class="sr-only" data-unggah-otomatis>

                        <label for="unggah-{{ $jenis->id }}" data-tombol-unggah
                               class="inline-flex min-h-12 cursor-pointer items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-[var(--teks)] ring-1 ring-inset ring-[var(--garis)] transition hover:bg-gray-50">
                            {{ $berkas ? 'Ganti berkas' : 'Unggah untuk walisantri' }}
                        </label>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
</div>
