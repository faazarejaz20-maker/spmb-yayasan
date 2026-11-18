<?php

namespace App\Services;

use App\Enums\JenisRiwayat;
use App\Enums\PeranPengguna;
use App\Enums\StatusHasil;
use App\Exceptions\DataGandaException;
use App\Exceptions\SyaratKirimException;
use App\Models\Anak;
use App\Models\Berkas;
use App\Models\Gelombang;
use App\Models\JenisBerkas;
use App\Models\OrangTua;
use App\Models\Pendaftaran;
use App\Models\RincianBiaya;
use App\Models\RiwayatStatus;
use App\Models\Sekolah;
use App\Models\User;
use App\Policies\AkunWalisantriPolicy;
use App\Support\Catat;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PendaftaranService
{
    public function __construct(
        private readonly AturanIsian $aturanIsian,
        private readonly DeteksiGandaService $deteksiGanda,
        private readonly NomorPesertaService $nomorPeserta,
        private readonly StatusTampilService $statusTampil,
    ) {}

    /**
     * Buat baris pendaftaran draf untuk anak yang sudah ada.
     */
    public function buatUntukAnak(
        User $pelaku,
        User $pemilik,
        Anak $anak,
        Sekolah $sekolah,
        Gelombang $gelombang,
        ?string $program,
    ): Pendaftaran {
        $this->pastikanBolehBuat($pelaku, $pemilik, $anak);

        $program = $this->programSah($sekolah, $program);

        $ada = Pendaftaran::query()
            ->where('anak_id', $anak->id)
            ->where('sekolah_id', $sekolah->id)
            ->where('gelombang_id', $gelombang->id)
            ->first();

        if ($ada) {
            return $ada;
        }

        return Pendaftaran::create([
            'anak_id' => $anak->id,
            'sekolah_id' => $sekolah->id,
            'gelombang_id' => $gelombang->id,
            'program' => $program,
            'dibuat_oleh' => $pelaku->id,
            'lulusan_yayasan' => false,
        ]);
    }

    /**
     * Buat anak baru (data minimal langkah 2) sekaligus pendaftaran draf.
     *
     * @param  array<string, mixed>  $data
     */
    public function buatDenganAnakBaru(
        User $pelaku,
        User $pemilik,
        Sekolah $sekolah,
        Gelombang $gelombang,
        ?string $program,
        array $data,
    ): Pendaftaran {
        $this->pastikanBolehBuat($pelaku, $pemilik);

        $program = $this->programSah($sekolah, $program);
        $data = $this->aturanIsian->saring($sekolah->id, 'identitas', $data);

        // Alamat baru diisi di langkah 3; kolom tidak boleh null.
        $data['alamat'] = '';

        $percobaan = new Anak($data);
        $percobaan->user_id = $pemilik->id;

        if ($this->deteksiGanda->adaGanda($percobaan, $sekolah->id, $gelombang->tahun_ajaran_id)) {
            throw new DataGandaException(DataGandaException::PESAN);
        }

        return DB::transaction(function () use ($pelaku, $pemilik, $sekolah, $gelombang, $program, $data) {
            $anak = new Anak($data);
            $anak->user_id = $pemilik->id;
            $anak->save();

            return Pendaftaran::create([
                'anak_id' => $anak->id,
                'sekolah_id' => $sekolah->id,
                'gelombang_id' => $gelombang->id,
                'program' => $program,
                'dibuat_oleh' => $pelaku->id,
                'lulusan_yayasan' => false,
            ]);
        });
    }

    /**
     * Simpan satu langkah isian (2 sampai 5). Hanya kunci yang tampil diterima.
     *
     * @param  array<string, mixed>  $data
     */
    public function simpanLangkah(User $pelaku, Pendaftaran $pendaftaran, int $langkah, array $data): void
    {
        $this->pastikanMilik($pelaku, $pendaftaran);

        if (! $pendaftaran->bolehDiubah()) {
            throw new AuthorizationException('Pendaftaran sudah dikirim dan tidak dapat diubah.');
        }

        $bagian = AturanIsian::bagianDariLangkah($langkah);

        if ($bagian === null) {
            throw new \InvalidArgumentException('Langkah formulir tidak dikenal.');
        }

        $pendaftaran->loadMissing(['anak', 'gelombang']);
        $anak = $pendaftaran->anak;

        $data = $this->aturanIsian->saring($pendaftaran->sekolah_id, $bagian, $data);

        if ($langkah === 2 && $this->deteksiGanda->adaGanda(
            $this->anakSementara($anak, $data),
            $pendaftaran->sekolah_id,
            $pendaftaran->gelombang->tahun_ajaran_id,
        )) {
            throw new DataGandaException(DataGandaException::PESAN);
        }

        $anak->fill($data)->save();

        if ($langkah === 5) {
            // `lulusan_yayasan` hanya kolom `pendaftaran`, bukan kolom `anak`,
            // jadi tidak ikut `fill()` di atas. Nilainya dihitung dari asal
            // sekolah yang baru disimpan, sama seperti `simpanDataOlehStaf()`.
            $pendaftaran->forceFill([
                'lulusan_yayasan' => $this->lulusanYayasan($anak->asal_sekolah),
            ])->save();
        }
    }

    /**
     * Simpan persetujuan biaya dan pilihan IAP.
     *
     * @param  array<string, mixed>  $data
     */
    public function simpanBiaya(User $pelaku, Pendaftaran $pendaftaran, array $data): void
    {
        $this->pastikanMilik($pelaku, $pendaftaran);

        if (! $pendaftaran->bolehDiubah()) {
            throw new AuthorizationException('Pendaftaran sudah dikirim dan tidak dapat diubah.');
        }

        $rincian = $this->rincianBiaya($pendaftaran);

        if ($rincian->isEmpty()) {
            $pendaftaran->forceFill([
                'pilihan_iap' => null,
                'setuju_biaya_pada' => null,
            ])->save();

            return;
        }

        $pilihan = $rincian->where('tipe', \App\Enums\TipeBiaya::Pilihan)
            ->pluck('nominal')
            ->map(fn ($nominal) => (string) $nominal);

        $pilihanIap = null;

        if ($pilihan->isNotEmpty()) {
            $dipilih = isset($data['pilihan_iap']) ? (string) $data['pilihan_iap'] : '';

            if (! $pilihan->contains($dipilih)) {
                throw new \InvalidArgumentException('Pilihan IAP tidak sesuai dengan rincian sekolah.');
            }

            $pilihanIap = (int) $dipilih;
        }

        $pendaftaran->forceFill([
            'pilihan_iap' => $pilihanIap,
            'setuju_biaya_pada' => now(),
        ])->save();
    }

    /**
     * @return \Illuminate\Support\Collection<int, RincianBiaya>
     */
    public function rincianBiaya(Pendaftaran $pendaftaran)
    {
        $pendaftaran->loadMissing('gelombang');

        return RincianBiaya::query()
            ->where('sekolah_id', $pendaftaran->sekolah_id)
            ->where('aktif', true)
            ->where(function ($query) use ($pendaftaran) {
                $query->whereNull('tahun_ajaran_id')
                    ->orWhere('tahun_ajaran_id', $pendaftaran->gelombang->tahun_ajaran_id);
            })
            ->orderBy('urutan')
            ->orderBy('id')
            ->get();
    }

    /**
     * Daftar syarat yang belum terpenuhi.
     *
     * @return array<int, array{pesan: string, langkah: int, tautan: string|null}>
     */
    public function periksaSyarat(Pendaftaran $pendaftaran, ?User $pelaku = null): array
    {
        $pendaftaran->loadMissing(['anak', 'sekolah', 'gelombang']);

        $staf = $pelaku !== null && $pelaku->peran?->terikatSekolah();
        $kekurangan = [];

        if (! $this->gelombangDibuka($pendaftaran->gelombang)) {
            $kekurangan[] = [
                'pesan' => 'Gelombang pendaftaran sudah ditutup. Hubungi panitia bila masih perlu mendaftar.',
                'langkah' => 1,
                'tautan' => null,
            ];
        }

        if (! $this->orangTuaCukup($pendaftaran->anak->user_id)) {
            $kekurangan[] = [
                'pesan' => 'Lengkapi nama dan nomor HP ayah atau ibu pada data orang tua.',
                'langkah' => 1,
                'tautan' => $staf
                    ? route('panitia.offline.orang-tua', $pendaftaran->uuid)
                    : route('walisantri.orang-tua'),
            ];
        }

        foreach ($this->aturanIsian->kekuranganTahap1($pendaftaran->anak, $pendaftaran->sekolah_id) as $kurang) {
            $kekurangan[] = [
                'pesan' => $kurang['pesan'],
                'langkah' => $kurang['langkah'],
                'tautan' => $staf
                    ? route('panitia.offline.langkah', [
                        'pendaftaran' => $pendaftaran->uuid,
                        'n' => $kurang['langkah'],
                    ])
                    : route('walisantri.pendaftaran.langkah', [
                        'pendaftaran' => $pendaftaran->uuid,
                        'n' => $kurang['langkah'],
                    ]),
            ];
        }

        foreach ($this->jenisBerkasWajibKurang($pendaftaran) as $nama) {
            $kekurangan[] = [
                'pesan' => 'Berkas wajib belum diunggah: '.$nama.'.',
                'langkah' => 6,
                'tautan' => $staf
                    ? route('panitia.offline.berkas.index', $pendaftaran->uuid)
                    : route('walisantri.pendaftaran.berkas.index', $pendaftaran->uuid),
            ];
        }

        if ($this->biayaBelumDisetujui($pendaftaran)) {
            $kekurangan[] = [
                'pesan' => 'Baca dan setujui rincian biaya, serta pilih nominal IAP bila ada pilihan.',
                'langkah' => 7,
                'tautan' => $staf
                    ? route('panitia.offline.biaya', $pendaftaran->uuid)
                    : route('walisantri.pendaftaran.langkah', [
                        'pendaftaran' => $pendaftaran->uuid,
                        'n' => 7,
                    ]),
            ];
        }

        if ($this->deteksiGanda->adaGanda(
            $pendaftaran->anak,
            $pendaftaran->sekolah_id,
            $pendaftaran->gelombang->tahun_ajaran_id,
        )) {
            $kekurangan[] = [
                'pesan' => DataGandaException::PESAN,
                'langkah' => 2,
                'tautan' => null,
            ];
        }

        return $kekurangan;
    }

    /**
     * Kirim pendaftaran draf. Nomor peserta dibuat di dalam transaksi dan kunci.
     */
    public function kirim(User $pelaku, Pendaftaran $pendaftaran, bool $dataBenar, bool $privasi): Pendaftaran
    {
        $this->pastikanMilik($pelaku, $pendaftaran);

        if (! $pendaftaran->masihDraf()) {
            throw new AuthorizationException('Pendaftaran sudah pernah dikirim.');
        }

        $kekurangan = [];

        if (! $dataBenar) {
            $kekurangan[] = ['pesan' => 'Centang pernyataan bahwa data yang diisi benar.', 'langkah' => 8, 'tautan' => null];
        }

        if (! $privasi) {
            $kekurangan[] = ['pesan' => 'Centang persetujuan penggunaan data sesuai Kebijakan Privasi.', 'langkah' => 8, 'tautan' => null];
        }

        $kekurangan = array_merge($kekurangan, $this->periksaSyarat($pendaftaran, $pelaku));

        if ($kekurangan !== []) {
            throw new SyaratKirimException($kekurangan);
        }

        $percobaan = 0;

        while (true) {
            try {
                $hasil = DB::transaction(function () use ($pelaku, $pendaftaran) {
                    /** @var Pendaftaran $segar */
                    $segar = Pendaftaran::query()
                        ->whereKey($pendaftaran->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (! $segar->masihDraf()) {
                        throw new AuthorizationException('Pendaftaran sudah pernah dikirim.');
                    }

                    $nomor = $this->nomorPeserta->buat($segar);

                    $segar->forceFill([
                        'nomor_peserta' => $nomor,
                        'status_hasil' => StatusHasil::Baru,
                        'dikirim_pada' => now(),
                        'setuju_data_pada' => now(),
                    ])->save();

                    $olehStaf = $pelaku->peran?->terikatSekolah() ?? false;

                    RiwayatStatus::create([
                        'pendaftaran_id' => $segar->id,
                        'jenis' => JenisRiwayat::Hasil,
                        'dari' => null,
                        'ke' => StatusHasil::Baru->value,
                        'oleh' => $pelaku->id,
                        'catatan' => $olehStaf
                            ? 'Pendaftaran dikirim oleh panitia atas nama walisantri.'
                            : 'Pendaftaran dikirim oleh walisantri.',
                    ]);

                    Catat::aksi('kirim_pendaftaran', $segar, [
                        'nomor_peserta' => $nomor,
                        'oleh_panitia' => $olehStaf,
                    ], $segar->sekolah_id);

                    return $segar;
                });

                return $hasil->refresh();
            } catch (QueryException $galat) {
                $percobaan++;

                if ($percobaan >= 2 || ! $this->galatUnik($galat)) {
                    throw $galat;
                }
            }
        }
    }

    /**
     * Kirim perbaikan setelah panitia menandai Perlu perbaikan.
     */
    public function kirimPerbaikan(User $pelaku, Pendaftaran $pendaftaran): Pendaftaran
    {
        $this->pastikanMilik($pelaku, $pendaftaran);

        if ($pendaftaran->status_hasil !== StatusHasil::PerluPerbaikan) {
            throw new AuthorizationException('Pendaftaran ini tidak sedang dalam perbaikan.');
        }

        $berkasPerbaikan = Berkas::query()
            ->where('pendaftaran_id', $pendaftaran->id)
            ->where('status', \App\Enums\StatusBerkas::PerluPerbaikan)
            ->exists();

        if ($berkasPerbaikan) {
            throw new SyaratKirimException([[
                'pesan' => 'Masih ada berkas yang ditandai perlu perbaikan. Unggah ulang berkas tersebut.',
                'langkah' => 6,
                'tautan' => route('walisantri.pendaftaran.berkas.index', $pendaftaran->uuid),
            ]]);
        }

        $kekurangan = $this->periksaSyarat($pendaftaran, $pelaku);

        if ($kekurangan !== []) {
            throw new SyaratKirimException($kekurangan);
        }

        return DB::transaction(function () use ($pelaku, $pendaftaran) {
            $pendaftaran->forceFill([
                'status_hasil' => StatusHasil::Baru,
            ])->save();

            RiwayatStatus::create([
                'pendaftaran_id' => $pendaftaran->id,
                'jenis' => JenisRiwayat::Hasil,
                'dari' => StatusHasil::PerluPerbaikan->value,
                'ke' => StatusHasil::Baru->value,
                'oleh' => $pelaku->id,
                'catatan' => 'Perbaikan dikirim walisantri.',
            ]);

            Catat::aksi('kirim_perbaikan', $pendaftaran, [], $pendaftaran->sekolah_id);

            return $pendaftaran->refresh();
        });
    }

    /**
     * Simpan perubahan data anak oleh panitia/admin. Semua kunci disaring
     * lewat `AturanIsian`; log hanya mencatat nama kolom yang berubah.
     *
     * @param  array<string, mixed>  $data
     */
    public function simpanDataOlehStaf(User $pelaku, Pendaftaran $pendaftaran, array $data): void
    {
        $this->pastikanMilik($pelaku, $pendaftaran);

        if (! $pelaku->peran?->terikatSekolah()) {
            throw new AuthorizationException('Hanya panitia atau admin yang boleh mengubah data pendaftar.');
        }

        $pendaftaran->loadMissing(['anak', 'gelombang']);
        $anak = $pendaftaran->anak;
        $berubah = [];

        DB::transaction(function () use ($pendaftaran, $anak, $data, &$berubah) {
            foreach (['identitas', 'alamat', 'kesehatan', 'asal_sekolah'] as $bagian) {
                $bersih = $this->aturanIsian->saring($pendaftaran->sekolah_id, $bagian, $data);

                foreach ($bersih as $kunci => $nilai) {
                    $nilai = $nilai === '' ? null : $nilai;
                    $lama = $anak->getAttribute($kunci);

                    if ((string) $lama !== (string) $nilai) {
                        $berubah[] = $kunci;
                    }

                    $anak->setAttribute($kunci, $nilai);
                }
            }

            $anak->save();

            $lulusan = $this->lulusanYayasan($anak->asal_sekolah);
            $pendaftaran->forceFill(['lulusan_yayasan' => $lulusan])->save();
        });

        Catat::aksi('ubah_data_pendaftar', $pendaftaran, [
            'kolom' => array_values(array_unique($berubah)),
        ], $pendaftaran->sekolah_id);
    }

    /**
     * Simpan data orang tua milik akun pemilik anak atas nama panitia.
     *
     * @param  array<string, array<string, mixed>>  $data  Kunci: ayah/ibu/wali
     * @return array<int, string> Kolom yang berubah (nama saja, tanpa nilai).
     */
    public function simpanOrangTuaOlehStaf(User $pelaku, Pendaftaran $pendaftaran, array $data): array
    {
        $this->pastikanMilik($pelaku, $pendaftaran);

        if (! $pelaku->peran?->terikatSekolah()) {
            throw new AuthorizationException('Hanya panitia atau admin yang boleh mengubah data orang tua.');
        }

        $pendaftaran->loadMissing('anak');
        $berubah = [];

        DB::transaction(function () use ($pendaftaran, $data, &$berubah) {
            foreach (\App\Enums\HubunganOrangTua::cases() as $hubungan) {
                $baris = $data[$hubungan->value] ?? [];

                if (! is_array($baris)) {
                    continue;
                }

                $baris['no_hp'] = filled($baris['no_hp'] ?? null)
                    ? \App\Support\NomorHp::normalisasi($baris['no_hp'])
                    : null;

                $hasil = \App\Support\DataOrangTua::simpan(
                    (int) $pendaftaran->anak->user_id,
                    $hubungan,
                    $baris,
                );

                foreach ($hasil['kolom'] as $kolom) {
                    $berubah[] = $hubungan->value.'.'.$kolom;
                }
            }
        });

        Catat::aksi('ubah_orang_tua_pendaftar', $pendaftaran, [
            'kolom' => array_values(array_unique($berubah)),
        ], $pendaftaran->sekolah_id);

        return array_values(array_unique($berubah));
    }

    /**
     * Data ringkasan untuk halaman "Periksa & kirim", dipakai bersama oleh
     * formulir walisantri dan formulir yang diisi panitia.
     *
     * @return array<string, mixed>
     */
    public function ringkasan(Pendaftaran $pendaftaran): array
    {
        $pendaftaran->loadMissing(['anak', 'gelombang']);

        $bagian = [];

        foreach ([
            'identitas' => 'Data anak',
            'alamat' => 'Alamat',
            'kesehatan' => 'Kesehatan',
            'asal_sekolah' => 'Asal sekolah',
        ] as $kunciBagian => $judul) {
            $isian = $this->aturanIsian->isianTampil($pendaftaran->sekolah_id, $kunciBagian);
            $nilai = $this->aturanIsian->nilaiAnak($pendaftaran->anak, $pendaftaran->sekolah_id, $kunciBagian);

            $bagian[$judul] = collect($isian)->map(function (array $definisi, string $kunci) use ($nilai) {
                $isi = $nilai[$kunci] ?? null;

                if (in_array($kunci, ['nik', 'no_kk'], true)) {
                    $isi = \App\Support\Samarkan::nik(is_string($isi) ? $isi : null);
                } elseif ($isi instanceof \DateTimeInterface) {
                    $isi = $isi->format('d-m-Y');
                } elseif (is_bool($isi)) {
                    $isi = $isi ? 'Ya' : 'Tidak';
                } elseif (isset($definisi['pilihan'][$isi])) {
                    $isi = $definisi['pilihan'][$isi];
                }

                return [
                    'label' => $definisi['label'],
                    'nilai' => filled($isi) ? (string) $isi : '—',
                ];
            })->values()->all();
        }

        $orangTua = OrangTua::query()
            ->where('user_id', $pendaftaran->anak->user_id)
            ->get()
            // Baris tanpa nama (contoh: data wali dikosongkan) tidak
            // ditampilkan di ringkasan.
            ->filter(fn (OrangTua $baris) => filled($baris->nama))
            ->mapWithKeys(fn (OrangTua $baris) => [
                $baris->hubungan->value => [
                    'nama' => $baris->nama,
                    'no_hp' => $baris->no_hp,
                ],
            ])
            ->all();

        $rincian = $this->rincianBiaya($pendaftaran);
        $pilihan = $rincian->where('tipe', \App\Enums\TipeBiaya::Pilihan);
        $terpilih = $pendaftaran->pilihan_iap !== null ? (string) $pendaftaran->pilihan_iap : null;

        $total = $rincian
            ->where('tipe', \App\Enums\TipeBiaya::Tetap)
            ->sum(fn (RincianBiaya $biaya) => (int) $biaya->nominal);

        if ($terpilih !== null) {
            $total += (int) $terpilih;
        }

        $berkas = Berkas::query()
            ->where('pendaftaran_id', $pendaftaran->id)
            ->with('jenisBerkas')
            ->get()
            ->map(fn (Berkas $baris) => [
                'nama' => $baris->jenisBerkas->nama,
                'status' => $baris->status->label(),
            ])
            ->values()
            ->all();

        return [
            'bagian' => $bagian,
            'orang_tua' => $orangTua,
            'rincian' => $rincian,
            'pilihan' => $pilihan,
            'terpilih' => $terpilih,
            'total' => $total,
            'setuju_biaya' => $pendaftaran->setuju_biaya_pada !== null,
            'berkas' => $berkas,
            'program' => $pendaftaran->program,
        ];
    }

    public function gelombangDibuka(Gelombang $gelombang): bool
    {
        if ($gelombang->status !== \App\Enums\StatusGelombang::Dibuka) {
            return false;
        }

        $hariIni = now()->toDateString();

        if ($gelombang->tanggal_buka !== null && $gelombang->tanggal_buka->toDateString() > $hariIni) {
            return false;
        }

        if ($gelombang->tanggal_tutup !== null && $gelombang->tanggal_tutup->toDateString() < $hariIni) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function jenisBerkasWajibKurang(Pendaftaran $pendaftaran): array
    {
        $wajib = JenisBerkas::query()
            ->where('sekolah_id', $pendaftaran->sekolah_id)
            ->where('aktif', true)
            ->where('wajib', true)
            ->get();

        $ada = Berkas::query()
            ->where('pendaftaran_id', $pendaftaran->id)
            ->whereNotNull('path')
            ->pluck('jenis_berkas_id')
            ->all();

        return $wajib
            ->filter(fn (JenisBerkas $jenis) => ! in_array($jenis->id, $ada, true))
            ->pluck('nama')
            ->all();
    }

    private function orangTuaCukup(int $userId): bool
    {
        return OrangTua::query()
            ->where('user_id', $userId)
            ->whereIn('hubungan', [\App\Enums\HubunganOrangTua::Ayah->value, \App\Enums\HubunganOrangTua::Ibu->value])
            ->whereNotNull('nama')
            ->where('nama', '!=', '')
            ->whereNotNull('no_hp')
            ->where('no_hp', '!=', '')
            ->exists();
    }

    private function biayaBelumDisetujui(Pendaftaran $pendaftaran): bool
    {
        $rincian = $this->rincianBiaya($pendaftaran);

        if ($rincian->isEmpty()) {
            return false;
        }

        if ($pendaftaran->setuju_biaya_pada === null) {
            return true;
        }

        $pilihan = $rincian->where('tipe', \App\Enums\TipeBiaya::Pilihan)
            ->pluck('nominal')
            ->map(fn ($nominal) => (string) $nominal);

        if ($pilihan->isEmpty()) {
            return false;
        }

        return ! $pilihan->contains((string) $pendaftaran->pilihan_iap);
    }

    private function anakSementara(Anak $anak, array $data): Anak
    {
        $salin = $anak->replicate(['id', 'uuid']);
        $salin->setAttribute('id', $anak->id);
        $salin->exists = true;
        $salin->forceFill($data);

        return $salin;
    }

    private function lulusanYayasan(?string $asalSekolah): bool
    {
        if (blank($asalSekolah)) {
            return false;
        }

        $cari = Str::lower(trim((string) $asalSekolah));

        return Sekolah::query()
            ->pluck('nama')
            ->contains(fn (string $nama) => Str::lower(trim($nama)) === $cari);
    }

    private function programSah(Sekolah $sekolah, ?string $program): ?string
    {
        if ($sekolah->kode === config('spmb.nomor_peserta.kode_program')) {
            $program = strtoupper((string) $program);

            if (! in_array($program, config('spmb.nomor_peserta.program_tki'), true)) {
                throw new \InvalidArgumentException('Program KB, TK, atau TPA wajib dipilih.');
            }

            return $program;
        }

        return null;
    }

    private function pastikanMilik(User $pelaku, Pendaftaran $pendaftaran): void
    {
        // Panitia/admin boleh bekerja pada pendaftaran sekolahnya sendiri.
        if ($pelaku->peran?->terikatSekolah()) {
            if (! $pelaku->sekolah_id || (int) $pelaku->sekolah_id !== (int) $pendaftaran->sekolah_id) {
                throw (new ModelNotFoundException)->setModel(Pendaftaran::class, [$pendaftaran->getKey()]);
            }

            return;
        }

        $milik = $pendaftaran->anak()
            ->where('user_id', $pelaku->id)
            ->exists();

        if (! $milik) {
            throw (new ModelNotFoundException)->setModel(Pendaftaran::class, [$pendaftaran->getKey()]);
        }
    }

    private function pastikanBolehBuat(User $pelaku, User $pemilik, ?Anak $anak = null): void
    {
        if ($pelaku->id !== $pemilik->id
            && ! app(AkunWalisantriPolicy::class)->kelola($pelaku, $pemilik)) {
            throw new AuthorizationException('Akun ini tidak boleh mendaftarkan anak untuk akun lain.');
        }

        if ($anak && (int) $anak->user_id !== (int) $pemilik->id) {
            throw (new ModelNotFoundException)->setModel(Anak::class, [$anak->getKey()]);
        }
    }

    private function galatUnik(QueryException $galat): bool
    {
        return Str::contains($galat->getMessage(), ['UNIQUE constraint failed', 'Duplicate entry', 'duplicate key']);
    }
}
