<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbKantor;
use App\Models\Legacy\TbMasterJabatan;
use App\Models\Legacy\TbRefJabatan;
use App\Models\QuotaJabatan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class QuotaJabatanController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureAdmin($request);

        $branchOptions = $this->branchOptions();
        $positionOptions = $this->positionOptions();
        $branchNames = collect($branchOptions)->keyBy('kode_cabang');
        $positionNames = collect($positionOptions)->keyBy(fn (array $position): string => (string) $position['kode_jabatan']);
        $filterValue = static function (string $key) use ($request): string {
            $value = $request->query($key, '');

            return is_scalar($value) ? trim((string) $value) : '';
        };
        $filters = [
            'search' => $filterValue('search'),
            'kode_cabang' => $filterValue('kode_cabang'),
            'jabatan_id' => $this->normalisePositionId($filterValue('jabatan_id')),
        ];

        $storedQuotas = Schema::hasTable('kuota_jabatan')
            ? QuotaJabatan::query()
                ->orderBy('kode_cabang')
                ->orderBy('jabatan_id')
                ->get()
            : collect();

        $overrides = $storedQuotas
            ->filter(fn (QuotaJabatan $quota): bool => $quota->jabatan_id !== null)
            ->keyBy(fn (QuotaJabatan $quota): string => $this->quotaKey(
                (string) $quota->kode_cabang,
                (string) $quota->jabatan_id,
            ));
        $quotas = collect();

        // The dashboard also uses master quotas for occupied positions without a branch override.
        // Include those effective values here so admins can edit or remove them from this page.
        if (Schema::hasTable('tb_jabatan') && Schema::hasTable('tb_pegawai') && Schema::hasTable('tb_kantor')) {
            $defaultsQuery = DB::table('tb_jabatan as j')
                ->join('tb_pegawai as p', 'p.id_peg', '=', 'j.id_peg')
                ->join('tb_kantor as k', 'k.kode_kantor_detail', '=', 'j.unit_kerja')
                ->where('j.status_jab', 'Aktif')
                ->where('p.status_aktif', 1)
                // One branch code can contain the head office and regional offices.
                // Quota Jabatan is configured for the main KP/KC office only.
                ->where(function ($query): void {
                    $query
                        ->whereIn('k.level', ['KP', 'KC'])
                        ->orWhereRaw("RIGHT(k.kode_kantor_detail, 3) = '000'");
                });

            if ($this->positionMasterTable() === 'tb_master_jabatan') {
                $defaultsQuery
                    ->join('tb_master_jabatan as m', 'm.kode_jabatan', '=', 'j.kode_jabatan')
                    ->whereIn('m.group_jabatan', ['PE', 'PS'])
                    ->select(['k.kode_cabang', 'm.kode_jabatan'])
                    ->selectRaw('m.nama_jabatan, m.group_jabatan')
                    ->selectRaw('MAX(m.kuota) as kuota_bawaan')
                    ->selectRaw('m.lingkup')
                    ->groupBy('k.kode_cabang', 'm.kode_jabatan', 'm.nama_jabatan', 'm.group_jabatan', 'm.lingkup');
            } else {
                // Fallback for installations that have not yet populated the newer master table.
                $defaultsQuery
                    ->join('tb_ref_jabatan as m', 'm.kode_jabatan', '=', 'j.kode_jabatan')
                    ->whereIn('m.`group`', ['PE', 'PS'])
                    ->select(['k.kode_cabang', 'm.kode_jabatan'])
                    ->selectRaw('m.jabatan as nama_jabatan, m.`group` as group_jabatan, m.lingkup')
                    ->selectRaw('MAX(m.kuota) as kuota_bawaan')
                    ->groupBy('k.kode_cabang', 'm.kode_jabatan', 'm.jabatan', 'm.lingkup')
                    ->groupByRaw('m.`group`');
            }

            $defaults = $defaultsQuery->get();

            foreach ($defaults as $default) {
                $branchCode = (string) $default->kode_cabang;
                $positionId = (string) $default->kode_jabatan;
                $key = $this->quotaKey($branchCode, $positionId);
                $override = $overrides->get($key);
                $position = $positionNames->get($positionId);

                // A zero override means this branch has removed the available quota.
                if ($override && (int) $override->kuota_pegawai === 0) {
                    continue;
                }

                $branch = $branchNames->get($branchCode);
                $quotas->push([
                    'id' => $override?->id,
                    'row_key' => $key,
                    'source' => $override ? 'cabang' : 'master',
                    'kode_cabang' => $branchCode,
                    'nama_cabang' => $branch['nama_cabang'] ?? $branchCode,
                    'jabatan_id' => (int) $positionId,
                    'nama_jabatan' => trim((string) ($override?->nama_jabatan ?: $default->nama_jabatan)),
                    'lingkup' => (string) ($default->lingkup ?: '-'),
                    'group_jabatan' => (string) ($default->group_jabatan ?: '-'),
                    'kuota_pegawai' => $override ? (int) $override->kuota_pegawai : max((int) $default->kuota_bawaan, 1),
                    'updated_at' => $override?->updated_at?->format('d-m-Y H:i'),
                ]);
            }
        }

        foreach ($storedQuotas as $quota) {
            $positionId = $quota->jabatan_id !== null ? (string) $quota->jabatan_id : '';
            $key = $positionId !== ''
                ? $this->quotaKey((string) $quota->kode_cabang, $positionId)
                : 'custom:'.$quota->id;
            if ($quotas->contains('row_key', $key) || (int) $quota->kuota_pegawai === 0) {
                continue;
            }

            $branch = $branchNames->get((string) $quota->kode_cabang);
            $position = $positionId !== '' ? $positionNames->get($positionId) : null;
            $customName = trim((string) ($quota->nama_jabatan ?? ''));
            $quotas->push([
                'id' => $quota->id,
                'row_key' => $key,
                'source' => 'cabang',
                'kode_cabang' => (string) $quota->kode_cabang,
                'nama_cabang' => $branch['nama_cabang'] ?? (string) $quota->kode_cabang,
                'jabatan_id' => $positionId !== '' ? (int) $quota->jabatan_id : null,
                'nama_jabatan' => $customName !== '' ? $customName : ($position['nama_jabatan'] ?? 'Jabatan tidak ditemukan'),
                'lingkup' => $position['lingkup'] ?? '-',
                'group_jabatan' => $position['group_jabatan'] ?? ($positionId === '' ? 'Custom' : null),
                'kuota_pegawai' => (int) $quota->kuota_pegawai,
                'updated_at' => $quota->updated_at?->format('d-m-Y H:i'),
            ]);
        }

        $search = mb_strtolower($filters['search']);
        $quotas = $quotas
            ->filter(function (array $quota) use ($filters, $search): bool {
                if ($filters['kode_cabang'] !== '' && (string) $quota['kode_cabang'] !== $filters['kode_cabang']) {
                    return false;
                }

                if ($filters['jabatan_id'] !== '' && $this->normalisePositionId($quota['jabatan_id']) !== $filters['jabatan_id']) {
                    return false;
                }

                if ($search !== '') {
                    $haystack = mb_strtolower(implode(' ', [
                        $quota['kode_cabang'],
                        $quota['nama_cabang'],
                        $quota['jabatan_id'],
                        $quota['nama_jabatan'],
                        $quota['lingkup'],
                        $quota['group_jabatan'],
                    ]));

                    if (! str_contains($haystack, $search)) {
                        return false;
                    }
                }

                return true;
            })
            ->sortBy(['kode_cabang', 'jabatan_id'])
            ->values()
            ->all();

        return Inertia::render('Config/QuotaJabatan', [
            'branchOptions' => $branchOptions,
            'branchPositionOptions' => $this->branchPositionOptions(),
            'positionOptions' => $positionOptions,
            'quotas' => $quotas,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        if ($request->has('quotas')) {
            $validated = $request->validate([
                'kode_cabang' => ['required', 'string', 'max:20', 'exists:tb_kantor,kode_cabang'],
                'quotas' => ['required', 'array', 'min:1'],
                'quotas.*.id' => ['nullable', 'integer'],
                'quotas.*.jabatan_id' => ['nullable', 'integer', Rule::exists($this->positionMasterTable(), 'kode_jabatan')],
                'quotas.*.nama_jabatan' => ['nullable', 'string', 'max:100'],
                'quotas.*.kuota_pegawai' => ['required', 'integer', 'min:0', 'max:100000'],
            ]);

            $customNames = [];
            foreach ($validated['quotas'] as $quota) {
                $hasMasterPosition = ($quota['jabatan_id'] ?? null) !== null;
                $hasCustomPosition = trim((string) ($quota['nama_jabatan'] ?? '')) !== '';

                if (! $hasMasterPosition && ! $hasCustomPosition) {
                    throw ValidationException::withMessages([
                        'quotas' => 'Setiap kuota harus memiliki jabatan master atau nama jabatan custom.',
                    ]);
                }

                if ($hasCustomPosition) {
                    $normalizedCustomName = mb_strtolower(trim((string) $quota['nama_jabatan']));
                    if (array_key_exists($normalizedCustomName, $customNames)) {
                        throw ValidationException::withMessages([
                            'quotas' => 'Nama jabatan custom tidak boleh duplikat dalam satu kantor.',
                        ]);
                    }
                    $customNames[$normalizedCustomName] = $quota['id'] ?? null;
                    $this->ensureCustomNameAvailable(
                        $validated['kode_cabang'],
                        trim((string) $quota['nama_jabatan']),
                        ! empty($quota['id']) ? (int) $quota['id'] : null,
                    );
                }
            }

            DB::transaction(function () use ($validated): void {
                foreach ($validated['quotas'] as $quota) {
                    if (($quota['jabatan_id'] ?? null) !== null) {
                        QuotaJabatan::query()->updateOrCreate(
                            [
                                'kode_cabang' => $validated['kode_cabang'],
                                'jabatan_id' => (int) $quota['jabatan_id'],
                            ],
                            [
                                'nama_jabatan' => trim((string) ($quota['nama_jabatan'] ?? '')) ?: null,
                                'kuota_pegawai' => (int) $quota['kuota_pegawai'],
                            ],
                        );

                        continue;
                    }

                    $customValues = [
                        'kode_cabang' => $validated['kode_cabang'],
                        'jabatan_id' => null,
                        'nama_jabatan' => trim((string) $quota['nama_jabatan']),
                        'kuota_pegawai' => (int) $quota['kuota_pegawai'],
                    ];
                    $customQuota = ! empty($quota['id'])
                        ? QuotaJabatan::query()
                            ->whereKey((int) $quota['id'])
                            ->where('kode_cabang', $validated['kode_cabang'])
                            ->first()
                        : null;

                    if ($customQuota) {
                        $customQuota->update($customValues);
                    } else {
                        QuotaJabatan::query()->create($customValues);
                    }
                }
            });

            return to_route('config.quota.index')->with(
                'success',
                sprintf('Kuota %d jabatan untuk %s berhasil disimpan.', count($validated['quotas']), $validated['kode_cabang']),
            );
        }

        $validated = $request->validate([
            'kode_cabang' => ['required', 'string', 'max:20', 'exists:tb_kantor,kode_cabang'],
            'jabatan_id' => ['nullable', 'integer', Rule::exists($this->positionMasterTable(), 'kode_jabatan')],
            'nama_jabatan' => ['nullable', 'string', 'max:100'],
            'kuota_pegawai' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        $positionId = $validated['jabatan_id'] ?? null;
        $customName = trim((string) ($validated['nama_jabatan'] ?? ''));
        if ($positionId === null && $customName === '') {
            throw ValidationException::withMessages([
                'nama_jabatan' => 'Isi nama jabatan custom atau pilih jabatan master.',
            ]);
        }

        if ($positionId === null) {
            $this->ensureCustomNameAvailable($validated['kode_cabang'], $customName);
        }

        if ($positionId !== null) {
            QuotaJabatan::query()->updateOrCreate(
                [
                    'kode_cabang' => $validated['kode_cabang'],
                    'jabatan_id' => (int) $positionId,
                ],
                [
                    'nama_jabatan' => $customName !== '' ? $customName : null,
                    'kuota_pegawai' => (int) $validated['kuota_pegawai'],
                ],
            );
        } else {
            QuotaJabatan::query()->create([
                'kode_cabang' => $validated['kode_cabang'],
                'jabatan_id' => null,
                'nama_jabatan' => $customName,
                'kuota_pegawai' => (int) $validated['kuota_pegawai'],
            ]);
        }

        $message = $positionId === null
            ? 'Kuota jabatan custom berhasil ditambahkan.'
            : ((int) $validated['kuota_pegawai'] === 0
            ? 'Kuota cabang disetel menjadi 0. Data jabatan master tetap tersimpan.'
            : 'Kuota jabatan berhasil disimpan.');

        return to_route('config.quota.index')->with('success', $message);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->ensureAdmin($request);

        $quota = QuotaJabatan::query()->findOrFail($id);
        $validated = $request->validate([
            'kode_cabang' => ['required', 'string', 'max:20', 'exists:tb_kantor,kode_cabang'],
            'jabatan_id' => ['nullable', 'integer', Rule::exists($this->positionMasterTable(), 'kode_jabatan')],
            'nama_jabatan' => ['nullable', 'string', 'max:100'],
            'kuota_pegawai' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        $positionId = $validated['jabatan_id'] ?? null;
        $customName = trim((string) ($validated['nama_jabatan'] ?? ''));
        if ($positionId === null && $customName === '') {
            throw ValidationException::withMessages([
                'nama_jabatan' => 'Isi nama jabatan custom atau pilih jabatan master.',
            ]);
        }

        if ($positionId !== null && QuotaJabatan::query()
            ->where('kode_cabang', $validated['kode_cabang'])
            ->where('jabatan_id', (int) $positionId)
            ->where($quota->getKeyName(), '<>', $quota->getKey())
            ->exists()) {
            throw ValidationException::withMessages([
                'jabatan_id' => 'Jabatan tersebut sudah memiliki kuota pada kantor ini.',
            ]);
        }

        if ($positionId === null) {
            $this->ensureCustomNameAvailable($validated['kode_cabang'], $customName, $quota->getKey());
        }

        $quota->update([
            'kode_cabang' => $validated['kode_cabang'],
            'jabatan_id' => $positionId !== null ? (int) $positionId : null,
            'nama_jabatan' => $customName !== '' ? $customName : null,
            'kuota_pegawai' => (int) $validated['kuota_pegawai'],
        ]);

        return to_route('config.quota.index')->with('success', 'Kuota jabatan berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->ensureAdmin($request);

        QuotaJabatan::query()->findOrFail($id)->update(['kuota_pegawai' => 0]);

        return to_route('config.quota.index')->with('success', 'Kuota cabang dihapus dari daftar aktif dan kapasitasnya disetel menjadi 0.');
    }

    private function ensureCustomNameAvailable(string $branchCode, string $name, ?int $ignoreId = null): void
    {
        $normalized = mb_strtolower(trim($name));
        $query = QuotaJabatan::query()
            ->where('kode_cabang', $branchCode)
            ->whereNull('jabatan_id')
            ->whereRaw('LOWER(TRIM(nama_jabatan)) = ?', [$normalized]);

        if ($ignoreId !== null) {
            $query->where((new QuotaJabatan)->getKeyName(), '<>', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'nama_jabatan' => 'Nama jabatan custom tersebut sudah memiliki kuota pada kantor ini.',
            ]);
        }
    }

    private function branchOptions(): array
    {
        return TbKantor::query()
            ->whereNotNull('kode_cabang')
            ->where('kode_cabang', '<>', '')
            ->where(function ($query): void {
                $query
                    ->whereIn('level', ['KP', 'KC'])
                    ->orWhereRaw("RIGHT(kode_kantor_detail, 3) = '000'");
            })
            ->select('kode_cabang')
            ->selectRaw('MIN(nama_kantor) as nama_kantor')
            ->groupBy('kode_cabang')
            ->orderBy('kode_cabang')
            ->get()
            ->map(fn ($branch): array => [
                'kode_cabang' => (string) $branch->kode_cabang,
                'nama_cabang' => trim((string) ($branch->nama_kantor ?: 'Cabang '.$branch->kode_cabang)),
                'label' => sprintf('%s - %s', $branch->kode_cabang, trim((string) ($branch->nama_kantor ?: 'Cabang'))),
            ])
            ->values()
            ->all();
    }

    private function positionOptions(): array
    {
        if ($this->positionMasterTable() === 'tb_master_jabatan') {
            return TbMasterJabatan::query()
                ->select(['kode_jabatan', 'nama_jabatan', 'group_jabatan', 'lingkup'])
                ->whereIn('group_jabatan', ['PE', 'PS'])
                ->orderBy('group_jabatan')
                ->orderBy('nama_jabatan')
                ->get()
                ->map(fn (TbMasterJabatan $position): array => [
                    'kode_jabatan' => (string) $position->kode_jabatan,
                    'nama_jabatan' => (string) $position->nama_jabatan,
                    'group_jabatan' => (string) ($position->group_jabatan ?: '-'),
                    'lingkup' => (string) ($position->lingkup ?: '-'),
                ])
                ->values()
                ->all();
        }

        return TbRefJabatan::query()
            ->selectRaw('kode_jabatan, jabatan as nama_jabatan, `group` as group_jabatan, lingkup')
            ->whereIn('group', ['PE', 'PS'])
            ->orderBy('group')
            ->orderBy('jabatan')
            ->get()
            ->map(fn (TbRefJabatan $position): array => [
                'kode_jabatan' => (string) $position->kode_jabatan,
                'nama_jabatan' => (string) $position->nama_jabatan,
                'group_jabatan' => (string) ($position->group_jabatan ?: '-'),
                'lingkup' => (string) ($position->lingkup ?: '-'),
            ])
            ->values()
            ->all();
    }

    /**
     * Return the active positions actually assigned to each branch.
     * The quota form uses this list after an office is selected.
     */
    private function branchPositionOptions(): array
    {
        if (! Schema::hasTable('tb_jabatan') || ! Schema::hasTable('tb_pegawai') || ! Schema::hasTable('tb_kantor')) {
            return [];
        }

        $positionsQuery = DB::table('tb_jabatan as j')
            ->join('tb_pegawai as p', 'p.id_peg', '=', 'j.id_peg')
            ->join('tb_kantor as k', 'k.kode_kantor_detail', '=', 'j.unit_kerja')
            ->where('j.status_jab', 'Aktif')
            ->where('p.status_aktif', 1)
            // Do not mix Kantor Pusat positions with KANWIL positions that share
            // the same three-digit branch code (for example branch 000).
            ->where(function ($query): void {
                $query
                    ->whereIn('k.level', ['KP', 'KC'])
                    ->orWhereRaw("RIGHT(k.kode_kantor_detail, 3) = '000'");
            });

        if ($this->positionMasterTable() === 'tb_master_jabatan') {
            $positionsQuery
                ->join('tb_master_jabatan as m', 'm.kode_jabatan', '=', 'j.kode_jabatan')
                ->whereIn('m.group_jabatan', ['PE', 'PS'])
                ->select([
                    'k.kode_cabang',
                    'm.kode_jabatan',
                    'm.nama_jabatan',
                    'm.group_jabatan',
                    'm.lingkup',
                ])
                ->groupBy(
                    'k.kode_cabang',
                    'm.kode_jabatan',
                    'm.nama_jabatan',
                    'm.group_jabatan',
                    'm.lingkup',
                )
                ->orderBy('k.kode_cabang')
                ->orderBy('m.nama_jabatan');
        } else {
            $positionsQuery
                ->join('tb_ref_jabatan as m', 'm.kode_jabatan', '=', 'j.kode_jabatan')
                ->whereIn('m.`group`', ['PE', 'PS'])
                ->selectRaw('k.kode_cabang, m.kode_jabatan, m.jabatan as nama_jabatan, m.`group` as group_jabatan, m.lingkup')
                ->groupBy(
                    'k.kode_cabang',
                    'm.kode_jabatan',
                    'm.jabatan',
                    'm.lingkup',
                )
                ->groupByRaw('m.`group`')
                ->orderBy('k.kode_cabang')
                ->orderBy('m.jabatan');
        }

        return $positionsQuery
            ->get()
            ->groupBy(fn ($position): string => (string) $position->kode_cabang)
            ->map(fn ($positions, string $branchCode): array => [
                'kode_cabang' => $branchCode,
                'positions' => $positions
                    ->map(fn ($position): array => [
                        'kode_jabatan' => (string) $position->kode_jabatan,
                        'nama_jabatan' => (string) $position->nama_jabatan,
                        'group_jabatan' => (string) ($position->group_jabatan ?: '-'),
                        'lingkup' => (string) ($position->lingkup ?: '-'),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    private function positionMasterTable(): string
    {
        return Schema::hasTable('tb_master_jabatan') ? 'tb_master_jabatan' : 'tb_ref_jabatan';
    }

    private function normalisePositionId(mixed $value): string
    {
        $value = trim((string) $value);

        return $value !== '' && ctype_digit($value) ? (string) ((int) $value) : $value;
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(strtolower((string) $request->session()->get('hak_akses')) === 'admin', 403);
    }

    private function quotaKey(string $branchCode, string $positionId): string
    {
        return $branchCode.':'.$positionId;
    }
}
