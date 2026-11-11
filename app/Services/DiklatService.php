<?php

namespace App\Services;

use App\Models\Legacy\TbBiayaPendidikan;
use App\Models\Legacy\TbDiklat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DiklatService
{
    /**
     * Master kegiatan yang dapat dipilih pada pendaftaran peserta baru.
     * Data lama tetap dihitung terpisah sehingga tidak perlu migrasi isi historis.
     */
    public function trainingMasters(): array
    {
        $stats = DB::table('tb_diklat')
            ->whereNotNull('diklat_id')
            ->select('diklat_id')
            ->selectRaw('COUNT(*) as peserta_terdaftar')
            ->selectRaw('COALESCE(SUM(COALESCE(biaya_per_peserta, biaya, 0)), 0) as realisasi_biaya')
            ->groupBy('diklat_id')
            ->get()
            ->keyBy('diklat_id');

        return TbBiayaPendidikan::query()
            ->whereNotNull('pengembangan_sdm')
            ->where('pengembangan_sdm', '<>', '')
            ->orderByDesc('tgl_pengembangan_sdm')
            ->orderBy('pengembangan_sdm')
            ->get([
                'biaya_id',
                'pengembangan_sdm',
                'pihak_pelaksana',
                'tipe_pelaksana',
                'waktu_pelaksanaan',
                'durasi_hari',
                'jumlah_sdm',
                'total_biaya',
                'tgl_pengembangan_sdm',
            ])
            ->map(function (TbBiayaPendidikan $master) use ($stats): array {
                $target = max((int) ($master->jumlah_sdm ?? 0), 0);
                $total = max((int) ($master->total_biaya ?? 0), 0);
                $stat = $stats->get($master->biaya_id);
                $registered = (int) ($stat->peserta_terdaftar ?? 0);
                $spent = (int) ($stat->realisasi_biaya ?? 0);

                return [
                    'value' => (string) $master->biaya_id,
                    'label' => (string) $master->pengembangan_sdm,
                    'description' => trim(implode(' · ', array_filter([
                        $master->pihak_pelaksana,
                        $master->tgl_pengembangan_sdm?->format('Y'),
                    ]))),
                    'penyelenggara' => $master->pihak_pelaksana,
                    'tipe_pelaksana' => $master->tipe_pelaksana,
                    'tahun' => $master->tgl_pengembangan_sdm?->format('Y'),
                    'date_reg' => $master->tgl_pengembangan_sdm?->format('Y-m-d'),
                    'total_biaya' => $total,
                    'target_peserta' => $target,
                    'peserta_terdaftar' => $registered,
                    'realisasi_biaya' => $spent,
                    'sisa_kuota' => max($target - $registered, 0),
                    'sisa_anggaran' => max($total - $spent, 0),
                    'batas_per_peserta' => $target > 0 ? (int) round($total / $target) : 0,
                    'durasi_hari' => $master->durasi_hari,
                    'waktu_pelaksanaan' => $master->waktu_pelaksanaan,
                ];
            })
            ->values()
            ->all();
    }

    public function saveLinked(Request $request, ?TbDiklat $existing, string $actor): TbDiklat
    {
        $validated = $request->validate([
            'id_peg' => ['required', 'string', 'max:12', Rule::exists('tb_pegawai', 'id_peg')],
            'diklat_id' => ['required', 'integer', Rule::exists('tb_biaya_pendidikan', 'biaya_id')],
            'tempat' => ['nullable', 'string', 'max:128'],
            'angkatan' => ['nullable', 'string', 'max:64'],
            'biaya_per_peserta' => ['required', 'string', 'max:32', 'regex:/^[0-9]+$/'],
        ], [
            'id_peg.required' => 'Pegawai wajib dipilih.',
            'id_peg.exists' => 'Pegawai yang dipilih tidak ditemukan.',
            'diklat_id.required' => 'Master kegiatan diklat wajib dipilih.',
            'diklat_id.exists' => 'Master kegiatan diklat tidak ditemukan.',
            'biaya_per_peserta.required' => 'Biaya per peserta wajib diisi.',
            'biaya_per_peserta.regex' => 'Biaya per peserta harus berupa angka tanpa desimal.',
        ]);

        $cost = (int) $validated['biaya_per_peserta'];

        return DB::transaction(function () use ($validated, $cost, $existing, $actor): TbDiklat {
            $master = TbBiayaPendidikan::query()
                ->lockForUpdate()
                ->findOrFail((int) $validated['diklat_id']);

            $target = max((int) ($master->jumlah_sdm ?? 0), 0);
            $totalBudget = max((int) ($master->total_biaya ?? 0), 0);
            $registeredQuery = TbDiklat::query()->where('diklat_id', $master->biaya_id);

            if ($existing) {
                $registeredQuery->where('id_diklat', '<>', $existing->id_diklat);
            }

            $registered = (int) $registeredQuery->count();
            $spent = (int) $registeredQuery->sum(DB::raw('COALESCE(biaya_per_peserta, biaya, 0)'));
            $perParticipantLimit = $target > 0 ? (int) round($totalBudget / $target) : 0;

            if ($registered >= $target) {
                throw ValidationException::withMessages([
                    'diklat_id' => "Kuota peserta sudah penuh ({$registered}/{$target}).",
                ]);
            }

            if (TbDiklat::query()
                ->where('id_peg', $validated['id_peg'])
                ->where('diklat_id', $master->biaya_id)
                ->when($existing, fn ($query) => $query->where('id_diklat', '<>', $existing->id_diklat))
                ->exists()) {
                throw ValidationException::withMessages([
                    'id_peg' => 'Pegawai tersebut sudah terdaftar pada kegiatan diklat ini.',
                ]);
            }

            if ($cost > $perParticipantLimit) {
                throw ValidationException::withMessages([
                    'biaya_per_peserta' => 'Biaya melebihi batas per peserta (Rp '.number_format($perParticipantLimit, 0, ',', '.').').',
                ]);
            }

            if ($spent + $cost > $totalBudget) {
                $remaining = max($totalBudget - $spent, 0);
                throw ValidationException::withMessages([
                    'biaya_per_peserta' => 'Total biaya peserta melebihi anggaran. Sisa anggaran Rp '.number_format($remaining, 0, ',', '.').'.',
                ]);
            }

            $date = $master->tgl_pengembangan_sdm?->format('Y-m-d');
            $payload = [
                'id_peg' => $validated['id_peg'],
                'diklat_id' => $master->biaya_id,
                'diklat' => $master->pengembangan_sdm,
                'penyelenggara' => $master->pihak_pelaksana,
                'tempat' => $validated['tempat'] ?? null,
                'biaya' => $cost,
                'biaya_per_peserta' => $cost,
                'angkatan' => $validated['angkatan'] ?? null,
                'tahun' => $master->tgl_pengembangan_sdm?->format('Y') ?? '',
                'date_reg' => $date,
            ];

            if ($existing) {
                $existing = TbDiklat::query()->lockForUpdate()->findOrFail($existing->id_diklat);
                $existing->update([...$payload, 'updated_by' => $actor, 'updated_at' => now()]);

                return $existing->refresh();
            }

            return TbDiklat::query()->create([...$payload, 'created_by' => $actor]);
        });
    }
}
