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
        $branchNames = collect($branchOptions)->groupBy('kode_cabang');
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
                        ->orWhere('k.kode_cabang', '000');
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

                $branch = $this->branchOptionForScope($branchNames, $branchCode, (string) $default->lingkup);
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
                    'kuota_pegawai' => $override ? (int) $override->kuota_pegawai : 1,
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

            $position = $positionId !== '' ? $positionNames->get($positionId) : null;
            $customName = trim((string) ($quota->nama_jabatan ?? ''));
            $branch = $this->branchOptionForScope(
                $branchNames,
                (string) $quota->kode_cabang,
                (string) ($position['lingkup'] ?? $quota->lingkup ?? ''),
            );
            $quotas->push([
                'id' => $quota->id,
                'row_key' => $key,
                'source' => 'cabang',
                'kode_cabang' => (string) $quota->kode_cabang,
                'nama_cabang' => $branch['nama_cabang'] ?? (string) $quota->kode_cabang,
                'jabatan_id' => $positionId !== '' ? (int) $quota->jabatan_id : null,
                'kode_custom' => $positionId === '' ? $quota->kode_jabatan : null,
                'nama_jabatan' => $customName !== '' ? $customName : ($position['nama_jabatan'] ?? 'Jabatan tidak ditemukan'),
                'lingkup' => $position['lingkup'] ?? ($quota->lingkup ?: ($branch['level'] ?? '-')),
                'group_jabatan' => $position['group_jabatan'] ?? $quota->group_jabatan,
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
                'lingkup' => ['nullable', 'string', Rule::in(['KP', 'KC', 'KANWIL'])],
                'quotas' => ['required', 'array', 'min:1'],
                'quotas.*.id' => ['nullable', 'integer'],
                'quotas.*.jabatan_id' => ['nullable', 'integer', Rule::exists($this->positionMasterTable(), 'kode_jabatan')],
                'quotas.*.kode_jabatan' => ['nullable', 'string', 'max:50'],
                'quotas.*.nama_jabatan' => ['nullable', 'string', 'max:100'],
                'quotas.*.group_jabatan' => ['nullable', 'string', Rule::in(['PE', 'PS'])],
                'quotas.*.kuota_pegawai' => ['required', 'integer', 'min:0', 'max:100000'],
                'quotas.*.lingkup' => ['nullable', 'string', Rule::in(['KP', 'KC', 'KANWIL'])],
            ]);

            $customNames = [];
            foreach ($validated['quotas'] as $quota) {
                $hasMasterPosition = ($quota['jabatan_id'] ?? null) !== null;
                $hasCustomPosition = trim((string) ($quota['nama_jabatan'] ?? '')) !== '';
                $customCode = trim((string) ($quota['kode_jabatan'] ?? ''));
                $customGroup = trim((string) ($quota['group_jabatan'] ?? ''));

                if (! $hasMasterPosition && ! $hasCustomPosition) {
                    throw ValidationException::withMessages([
                        'quotas' => 'Setiap kuota harus memiliki jabatan master atau nama jabatan custom.',
                    ]);
                }

                if ($hasCustomPosition && ! in_array($customGroup, ['PE', 'PS'], true)) {
                    throw ValidationException::withMessages([
                        'quotas' => 'Pilih label PE atau PS untuk setiap jabatan custom.',
                    ]);
                }

                if (! $hasMasterPosition && $customCode === '') {
                    throw ValidationException::withMessages([
                        'quotas' => 'Isi kode jabatan custom untuk setiap jabatan custom.',
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
                    $this->ensureCustomCodeAvailable(
                        $validated['kode_cabang'],
                        $customCode,
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
                                'kode_jabatan' => null,
                                'lingkup' => $quota['lingkup'] ?? $validated['lingkup'] ?? null,
                                'kuota_pegawai' => (int) $quota['kuota_pegawai'],
                            ],
                        );

                        continue;
                    }

                    $customValues = [
                        'kode_cabang' => $validated['kode_cabang'],
                        'jabatan_id' => null,
                        'kode_jabatan' => trim((string) $quota['kode_jabatan']),
                        'nama_jabatan' => trim((string) $quota['nama_jabatan']),
                        'group_jabatan' => trim((string) $quota['group_jabatan']),
                        'lingkup' => $this->branchLevel($validated['kode_cabang'], $validated['lingkup'] ?? null),
                        'kuota_pegawai' => (int) $quota['kuota_pegawai'],
                    ];
                    $customQuota = ! empty($quota['id'])
                        ? QuotaJabatan::query()
                            ->whereKey((int) $quota['id'])
                            ->where('kode_cabang', $validated['kode_cabang'])
                            ->first()
                        : null;

                    $customQuota ??= $this->findInactiveCustomQuota(
                        $validated['kode_cabang'],
                        trim((string) $quota['nama_jabatan']),
                        trim((string) $quota['kode_jabatan']),
                    );

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
            'lingkup' => ['nullable', 'string', Rule::in(['KP', 'KC', 'KANWIL'])],
            'jabatan_id' => ['nullable', 'integer', Rule::exists($this->positionMasterTable(), 'kode_jabatan')],
            'kode_jabatan' => ['nullable', 'string', 'max:50'],
            'nama_jabatan' => ['nullable', 'string', 'max:100'],
            'group_jabatan' => ['nullable', 'string', Rule::in(['PE', 'PS'])],
            'kuota_pegawai' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        $positionId = $validated['jabatan_id'] ?? null;
        $customCode = trim((string) ($validated['kode_jabatan'] ?? ''));
        $customName = trim((string) ($validated['nama_jabatan'] ?? ''));
        $customGroup = trim((string) ($validated['group_jabatan'] ?? ''));
        if ($positionId === null && $customName === '') {
            throw ValidationException::withMessages([
                'nama_jabatan' => 'Isi nama jabatan custom atau pilih jabatan master.',
            ]);
        }

        if ($positionId === null && ! in_array($customGroup, ['PE', 'PS'], true)) {
            throw ValidationException::withMessages([
                'group_jabatan' => 'Pilih label PE atau PS untuk jabatan custom.',
            ]);
        }

        if ($positionId === null && $customCode === '') {
            throw ValidationException::withMessages([
                'kode_jabatan' => 'Isi kode jabatan custom.',
            ]);
        }

        if ($positionId === null) {
            $this->ensureCustomNameAvailable($validated['kode_cabang'], $customName);
            $this->ensureCustomCodeAvailable($validated['kode_cabang'], $customCode);
        }

        if ($positionId !== null) {
            QuotaJabatan::query()->updateOrCreate(
                [
                    'kode_cabang' => $validated['kode_cabang'],
                    'jabatan_id' => (int) $positionId,
                ],
                [
                    'nama_jabatan' => $customName !== '' ? $customName : null,
                    'kode_jabatan' => null,
                    'lingkup' => $validated['lingkup'] ?? null,
                    'kuota_pegawai' => (int) $validated['kuota_pegawai'],
                ],
            );
        } else {
            $customValues = [
                'kode_cabang' => $validated['kode_cabang'],
                'jabatan_id' => null,
                'kode_jabatan' => $customCode,
                'nama_jabatan' => $customName,
                'group_jabatan' => $customGroup,
                'lingkup' => $this->branchLevel($validated['kode_cabang'], $validated['lingkup'] ?? null),
                'kuota_pegawai' => (int) $validated['kuota_pegawai'],
            ];
            $customQuota = $this->findInactiveCustomQuota($validated['kode_cabang'], $customName, $customCode);

            if ($customQuota) {
                $customQuota->update($customValues);
            } else {
                QuotaJabatan::query()->create($customValues);
            }
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
            'lingkup' => ['nullable', 'string', Rule::in(['KP', 'KC', 'KANWIL'])],
            'jabatan_id' => ['nullable', 'integer', Rule::exists($this->positionMasterTable(), 'kode_jabatan')],
            'kode_jabatan' => ['nullable', 'string', 'max:50'],
            'nama_jabatan' => ['nullable', 'string', 'max:100'],
            'group_jabatan' => ['nullable', 'string', Rule::in(['PE', 'PS'])],
            'kuota_pegawai' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        $positionId = $validated['jabatan_id'] ?? null;
        $customCode = trim((string) ($validated['kode_jabatan'] ?? ''));
        $customName = trim((string) ($validated['nama_jabatan'] ?? ''));
        $customGroup = trim((string) ($validated['group_jabatan'] ?? ''));
        if ($positionId === null && $customName === '') {
            throw ValidationException::withMessages([
                'nama_jabatan' => 'Isi nama jabatan custom atau pilih jabatan master.',
            ]);
        }

        if ($positionId === null && ! in_array($customGroup, ['PE', 'PS'], true)) {
            throw ValidationException::withMessages([
                'group_jabatan' => 'Pilih label PE atau PS untuk jabatan custom.',
            ]);
        }

        if ($positionId === null && $customCode === '') {
            throw ValidationException::withMessages([
                'kode_jabatan' => 'Isi kode jabatan custom.',
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
            $this->ensureCustomCodeAvailable($validated['kode_cabang'], $customCode, $quota->getKey());
        }

        $quota->update([
            'kode_cabang' => $validated['kode_cabang'],
            'jabatan_id' => $positionId !== null ? (int) $positionId : null,
            'kode_jabatan' => $positionId === null ? $customCode : null,
            'nama_jabatan' => $customName !== '' ? $customName : null,
            'group_jabatan' => $positionId === null ? $customGroup : null,
            'lingkup' => $positionId === null ? $this->branchLevel($validated['kode_cabang'], $validated['lingkup'] ?? null) : ($validated['lingkup'] ?? null),
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
            ->where('kuota_pegawai', '>', 0)
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

    private function ensureCustomCodeAvailable(string $branchCode, string $code, ?int $ignoreId = null): void
    {
        $normalized = mb_strtolower(trim($code));
        $query = QuotaJabatan::query()
            ->where('kode_cabang', $branchCode)
            ->whereNull('jabatan_id')
            ->where('kuota_pegawai', '>', 0)
            ->whereRaw('LOWER(TRIM(kode_jabatan)) = ?', [$normalized]);

        if ($ignoreId !== null) {
            $query->where((new QuotaJabatan)->getKeyName(), '<>', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'kode_jabatan' => 'Kode jabatan custom tersebut sudah digunakan pada kantor ini.',
            ]);
        }
    }

    private function findInactiveCustomQuota(string $branchCode, string $name, string $code): ?QuotaJabatan
    {
        $normalizedName = mb_strtolower(trim($name));
        $normalizedCode = mb_strtolower(trim($code));

        return QuotaJabatan::query()
            ->where('kode_cabang', $branchCode)
            ->whereNull('jabatan_id')
            ->where('kuota_pegawai', 0)
            ->where(function ($query) use ($normalizedName, $normalizedCode): void {
                $query->whereRaw('LOWER(TRIM(nama_jabatan)) = ?', [$normalizedName])
                    ->orWhereRaw('LOWER(TRIM(kode_jabatan)) = ?', [$normalizedCode]);
            })
            ->first();
    }

    private function branchOptions(): array
    {
        $regionOptions = collect(config('dashboard.region_filters', []))
            ->map(fn (array $region): array => [
                'value' => (string) $region['code'],
                // Region quotas are stored under the first branch code in
                // the same range; dashboard aggregation already uses that
                // range when reading configured quotas.
                'kode_cabang' => (string) $region['from'],
                'nama_cabang' => (string) $region['label'],
                'label' => (string) $region['label'],
                // Pusat is backed by the KP master scope. Every Korwil is
                // backed by the KANWIL master scope; it must not reuse KC
                // positions just because its offices contain KC branches.
                'level' => (string) $region['from'] === '000' && (string) $region['to'] === '000' ? 'KP' : 'KANWIL',
                'type' => 'region',
                'is_region' => true,
                'from' => (string) $region['from'],
                'to' => (string) $region['to'],
            ]);

        // Keep this list aligned with the Dashboard's Wilayah filter:
        // region scopes first, followed by the physical KC offices.
        $officeOptions = TbKantor::query()
            ->where('level', 'KC')
            ->orderBy('kode_cabang')
            ->get(['kode_kantor_detail', 'kode_cabang', 'nama_kantor'])
            ->map(fn (TbKantor $office): array => [
                'value' => (string) $office->kode_kantor_detail,
                'kode_cabang' => (string) $office->kode_cabang,
                'nama_cabang' => sprintf('%s - %s', $office->kode_cabang, trim((string) $office->nama_kantor)),
                'label' => sprintf('%s - %s', $office->kode_cabang, trim((string) $office->nama_kantor)),
                'level' => 'KC',
                'type' => 'office',
                'is_region' => false,
                'office_code' => (string) $office->kode_kantor_detail,
            ]);

        return collect([[
            'value' => '__dashboard_consolidated',
            'kode_cabang' => null,
            'nama_cabang' => 'Konsolidasi',
            'label' => 'Konsolidasi',
            'level' => 'ALL',
            'type' => 'consolidated',
            'is_region' => true,
            'from' => '000',
            'to' => '999',
        ]])
            ->concat($regionOptions)
            ->concat($officeOptions)
            ->values()
            ->all();
    }

    private function branchOptionForScope($branchNames, string $branchCode, string $scope): ?array
    {
        $scope = strtoupper(trim($scope));
        $options = $branchNames->get($branchCode, collect());
        $match = $options->first(fn (array $branch): bool => strtoupper((string) ($branch['level'] ?? '')) === $scope);
        if ($match) {
            return $match;
        }

        $regionalMatch = $branchNames
            ->flatten(1)
            ->first(function (array $branch) use ($branchCode, $scope): bool {
                return ($branch['is_region'] ?? false) === true
                    && ($branch['type'] ?? '') !== 'consolidated'
                    && strtoupper((string) ($branch['level'] ?? '')) === $scope
                    && (int) $branchCode >= (int) ($branch['from'] ?? 0)
                    && (int) $branchCode <= (int) ($branch['to'] ?? 0);
            });

        return $regionalMatch ?: $options->first();
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
     * Return the master positions for each branch level and merge any active
     * positions assigned to a branch. The quota form uses this list after an
     * office is selected.
     */
    private function branchPositionOptions(): array
    {
        if (! Schema::hasTable('tb_jabatan') || ! Schema::hasTable('tb_pegawai') || ! Schema::hasTable('tb_kantor')) {
            return [];
        }

        $branchContexts = collect($this->branchOptions())
            ->mapWithKeys(fn (array $branch): array => [
                (string) $branch['value'] => $branch,
            ])
            ->all();
        $branchLevels = collect($branchContexts)
            ->mapWithKeys(fn (array $branch, string $branchKey): array => [
                $branchKey => strtoupper(trim((string) $branch['level'])),
            ])
            ->all();
        $masterScopes = collect($branchLevels)
            ->filter(fn (string $level): bool => $level === 'ALL')
            ->isNotEmpty()
            ? ['KP', 'KANWIL', 'KC']
            : array_values(array_unique($branchLevels));

        $positionsByBranch = [];
        foreach (array_keys($branchLevels) as $branchCode) {
            $positionsByBranch[$branchCode] = [];
        }

        $masterPositionsQuery = $this->positionMasterTable() === 'tb_master_jabatan'
            ? TbMasterJabatan::query()
                ->select(['kode_jabatan', 'nama_jabatan', 'group_jabatan', 'lingkup', 'kuota'])
                ->whereIn('group_jabatan', ['PE', 'PS'])
                ->whereIn(DB::raw('UPPER(TRIM(lingkup))'), $masterScopes)
                ->orderBy('nama_jabatan')
            : TbRefJabatan::query()
                ->selectRaw('kode_jabatan, jabatan as nama_jabatan, `group` as group_jabatan, lingkup, kuota')
                ->whereIn('group', ['PE', 'PS'])
                ->whereIn(DB::raw('UPPER(TRIM(lingkup))'), $masterScopes)
                ->orderBy('jabatan');
        $masterPositions = $masterPositionsQuery->get();

        foreach ($branchLevels as $branchCode => $branchLevel) {
            foreach ($masterPositions as $position) {
                if ($branchLevel !== 'ALL'
                    && strtoupper(trim((string) ($position->lingkup ?? ''))) !== $branchLevel) {
                    continue;
                }

                $positionId = (string) $position->kode_jabatan;
                $positionsByBranch[$branchCode][$positionId] = [
                    'kode_jabatan' => $positionId,
                    'nama_jabatan' => (string) $position->nama_jabatan,
                    'group_jabatan' => (string) ($position->group_jabatan ?: '-'),
                    'lingkup' => (string) ($position->lingkup ?: '-'),
                    'kuota_bawaan' => 1,
                ];
            }
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
                    ->orWhere('k.kode_cabang', '000');
            });

        if ($this->positionMasterTable() === 'tb_master_jabatan') {
            $positionsQuery
                ->join('tb_master_jabatan as m', 'm.kode_jabatan', '=', 'j.kode_jabatan')
                ->whereIn('m.group_jabatan', ['PE', 'PS'])
                ->select([
                    'k.kode_cabang',
                    'k.kode_kantor_detail',
                    'm.kode_jabatan',
                    'm.nama_jabatan',
                    'm.group_jabatan',
                    'm.lingkup',
                    'm.kuota',
                ])
                ->groupBy(
                    'k.kode_cabang',
                    'k.kode_kantor_detail',
                    'm.kode_jabatan',
                    'm.nama_jabatan',
                    'm.group_jabatan',
                    'm.lingkup',
                    'm.kuota',
                )
                ->orderBy('k.kode_cabang')
                ->orderBy('m.nama_jabatan');
        } else {
            $positionsQuery
                ->join('tb_ref_jabatan as m', 'm.kode_jabatan', '=', 'j.kode_jabatan')
                ->whereIn('m.`group`', ['PE', 'PS'])
                ->selectRaw('k.kode_cabang, k.kode_kantor_detail, m.kode_jabatan, m.jabatan as nama_jabatan, m.`group` as group_jabatan, m.lingkup, m.kuota')
                ->groupBy(
                    'k.kode_cabang',
                    'k.kode_kantor_detail',
                    'm.kode_jabatan',
                    'm.jabatan',
                    'm.lingkup',
                    'm.kuota',
                )
                ->groupByRaw('m.`group`')
                ->orderBy('k.kode_cabang')
                ->orderBy('m.jabatan');
        }

        foreach ($positionsQuery->get() as $position) {
            $positionScope = strtoupper(trim((string) $position->lingkup));
            $positionId = (string) $position->kode_jabatan;

            foreach ($branchContexts as $branchKey => $branch) {
                if ($branch['level'] !== 'ALL' && strtoupper((string) $branch['level']) !== $positionScope) {
                    continue;
                }

                if (($branch['is_region'] ?? false) === true) {
                    if ($branch['type'] !== 'consolidated'
                        && ((int) $position->kode_cabang < (int) $branch['from']
                        || (int) $position->kode_cabang > (int) $branch['to'])) {
                        continue;
                    }
                } elseif (($branch['office_code'] ?? '') !== (string) $position->kode_kantor_detail) {
                    continue;
                }

                $positionsByBranch[$branchKey][$positionId] = [
                    'kode_jabatan' => $positionId,
                    'nama_jabatan' => (string) $position->nama_jabatan,
                    'group_jabatan' => (string) ($position->group_jabatan ?: '-'),
                    'lingkup' => (string) ($position->lingkup ?: '-'),
                    'kuota_bawaan' => 1,
                ];
            }
        }

        return collect($positionsByBranch)
            ->map(fn (array $positions, string $branchKey): array => [
                'value' => $branchKey,
                'kode_cabang' => $branchContexts[$branchKey]['kode_cabang'] ?? null,
                'level' => $branchContexts[$branchKey]['level'] ?? '-',
                'is_region' => $branchContexts[$branchKey]['is_region'] ?? false,
                'positions' => collect($positions)
                    ->sortBy('nama_jabatan', SORT_NATURAL | SORT_FLAG_CASE)
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

    private function branchLevel(string $branchCode, ?string $requestedScope = null): string
    {
        $requestedScope = strtoupper(trim((string) $requestedScope));
        if (in_array($requestedScope, ['KP', 'KC', 'KANWIL'], true)
            && collect($this->branchOptions())->contains(fn (array $branch): bool =>
                (string) $branch['kode_cabang'] === $branchCode && strtoupper((string) $branch['level']) === $requestedScope
            )) {
            return $requestedScope;
        }

        $level = TbKantor::query()
            ->where('kode_cabang', $branchCode)
            ->where(function ($query): void {
                $query
                    ->whereIn('level', ['KP', 'KC'])
                    ->orWhere('kode_cabang', '000');
            })
            ->orderByRaw("CASE WHEN level = 'KP' THEN 0 ELSE 1 END")
            ->value('level');

        return in_array($level, ['KP', 'KC'], true) ? (string) $level : '-';
    }

    private function normalisePositionId(mixed $value): string
    {
        $value = trim((string) $value);

        return $value !== '' && ctype_digit($value) ? (string) ((int) $value) : $value;
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(in_array(strtolower((string) $request->session()->get('hak_akses')), ['admin', 'it_admin'], true), 403);
    }

    private function quotaKey(string $branchCode, string $positionId): string
    {
        return $branchCode.':'.$positionId;
    }
}
