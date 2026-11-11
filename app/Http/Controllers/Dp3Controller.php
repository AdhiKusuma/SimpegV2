<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbDp3;
use App\Models\Legacy\TbPegawai;
use App\Models\Legacy\TbRecycleBin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class Dp3Controller extends Controller
{
    private const RESULTS = ['Sangat Baik', 'Baik', 'Cukup Baik', 'Kurang Baik'];

    private const SCORES = [
        'nilai_kesetiaan',
        'nilai_prestasi',
        'nilai_tgjwb',
        'nilai_ketaatan',
        'nilai_kejujuran',
        'nilai_kerjasama',
        'nilai_prakarsa',
        'nilai_kepemimpinan',
    ];

    public function index(Request $request): Response
    {
        $this->ensureStaffAccess($request);
        $role = strtolower((string) $request->session()->get('hak_akses'));
        $unit = $role === 'kepala' ? (string) $request->session()->get('kode_kantor') : '';
        $employeeId = $request->string('uid')->trim()->value();
        $search = $request->string('search')->trim()->value();
        $result = $request->string('hasil_penilaian')->trim()->value();

        $query = TbDp3::query()
            ->from('tb_dp3 as dp3')
            ->leftJoin('tb_pegawai as pegawai', 'pegawai.id_peg', '=', 'dp3.id_peg')
            ->when($employeeId !== '', fn (Builder $builder) => $builder->where('dp3.id_peg', $employeeId))
            ->when($result !== '', fn (Builder $builder) => $builder->where('dp3.hasil_penilaian', $result))
            ->when($unit !== '', fn (Builder $builder) => $builder->whereExists(function (QueryBuilder $scope) use ($unit): void {
                $scope->selectRaw('1')
                    ->from('tb_jabatan as jabatan_scope')
                    ->whereColumn('jabatan_scope.id_peg', 'dp3.id_peg')
                    ->where('jabatan_scope.status_jab', 'Aktif')
                    ->where('jabatan_scope.unit_kerja', $unit);
            }))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $like = "%{$search}%";
                $builder->where(function (QueryBuilder $scope) use ($like): void {
                    $scope->where('pegawai.nama', 'like', $like)
                        ->orWhere('dp3.id_peg', 'like', $like);
                });
            });

        $items = $query
            ->select('dp3.*', 'pegawai.nama as nama_pegawai')
            ->orderByDesc('dp3.periode_akhir')
            ->orderBy('pegawai.nama')
            ->paginate(min(max($request->integer('per_page', 10), 1), 50))
            ->withQueryString()
            ->through(fn (TbDp3 $item): array => $this->present($item));

        return Inertia::render('Dp3/Index', [
            'items' => $items,
            'filters' => ['uid' => $employeeId, 'search' => $search, 'hasil_penilaian' => $result],
            'results' => self::RESULTS,
            'canManage' => $this->canManage($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('Dp3/Form', [
            'mode' => 'create',
            'record' => ['id_peg' => $request->string('uid')->trim()->value(), 'hasil_penilaian' => 'Baik'],
            'employees' => $this->employees(),
            'results' => self::RESULTS,
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $this->ensureStaffAccess($request);
        $record = TbDp3::query()->findOrFail($id);
        $employee = $this->employeeQuery($request)->whereKey($record->id_peg)->firstOrFail();

        return Inertia::render('Dp3/Show', [
            'record' => $this->present($record),
            'employee' => ['id_peg' => $employee->id_peg, 'nama' => $employee->nama, 'nik' => $employee->nik],
            'canManage' => $this->canManage($request),
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->ensureCanManage($request);
        $record = TbDp3::query()->findOrFail($id);
        $this->employeeQuery($request)->whereKey($record->id_peg)->firstOrFail();

        return Inertia::render('Dp3/Form', [
            'mode' => 'edit',
            'record' => $this->formRecord($record),
            'employees' => $this->employees(),
            'results' => self::RESULTS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);
        $data = $this->validatedData($request, false);

        DB::transaction(function () use ($data): void {
            $data['id_dp3'] = $this->nextId();
            TbDp3::query()->create($data);
        });

        return to_route('dp3.index')->with('success', 'Data SKP berhasil disimpan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $record = TbDp3::query()->findOrFail($id);
        $this->employeeQuery($request)->whereKey($record->id_peg)->firstOrFail();
        $record->update($this->validatedData($request, true));

        return to_route('dp3.show', $record->getKey())->with('success', 'Data SKP berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($request, $id, $validated): void {
            $record = TbDp3::query()->lockForUpdate()->findOrFail($id);
            $this->employeeQuery($request)->whereKey($record->id_peg)->firstOrFail();
            TbRecycleBin::query()->create([
                'tabel_by' => 'tb_dp3',
                'data_json' => json_encode($record->getAttributes(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'alasan' => $validated['reason'],
                'created_by' => $request->session()->get('nama_user', 'Admin'),
                'created' => now(),
            ]);
            $record->delete();
        });

        return to_route('dp3.index')->with('success', 'Data SKP dipindahkan ke Recycle Bin.');
    }

    private function validatedData(Request $request, bool $isEdit): array
    {
        $rules = [
            'periode_awal' => ['required', 'date'],
            'periode_akhir' => ['required', 'date'],
            'pejabat_penilai' => ['required', 'string', 'max:64'],
            'atasan_pejabat_penilai' => ['required', 'string', 'max:64'],
            'hasil_penilaian' => ['required', Rule::in(self::RESULTS)],
        ];

        foreach (self::SCORES as $score) {
            $rules[$score] = ['nullable', 'integer', 'min:0', 'max:999'];
        }

        if (! $isEdit) {
            $rules['id_peg'] = ['required', 'string', 'max:8', Rule::exists('tb_pegawai', 'id_peg')];
        }

        $data = $request->validate($rules);
        if (! $isEdit) {
            $this->employeeQuery($request)->whereKey($data['id_peg'])->firstOrFail();
        }

        foreach (self::SCORES as $score) {
            $data[$score] = (int) ($data[$score] ?? 0);
        }

        return $data;
    }

    private function present(TbDp3 $record): array
    {
        $total = collect(self::SCORES)->sum(fn (string $score): int => (int) $record->{$score});

        return [
            'id_dp3' => $record->id_dp3,
            'id_peg' => $record->id_peg,
            'nama_pegawai' => $record->nama_pegawai ?? null,
            'periode_awal' => $record->periode_awal?->format('d-m-Y'),
            'periode_akhir' => $record->periode_akhir?->format('d-m-Y'),
            'periode_awal_value' => $record->periode_awal?->format('Y-m-d'),
            'periode_akhir_value' => $record->periode_akhir?->format('Y-m-d'),
            'pejabat_penilai' => $record->pejabat_penilai,
            'atasan_pejabat_penilai' => $record->atasan_pejabat_penilai,
            ...collect(self::SCORES)->mapWithKeys(fn (string $score): array => [$score => (int) $record->{$score}])->all(),
            'hasil_penilaian' => $record->hasil_penilaian,
            'nilai_total' => $total,
            'nilai_rata_rata' => round($total / count(self::SCORES), 2),
        ];
    }

    private function formRecord(TbDp3 $record): array
    {
        return array_merge($this->present($record), [
            'periode_awal' => $record->periode_awal?->format('Y-m-d'),
            'periode_akhir' => $record->periode_akhir?->format('Y-m-d'),
        ]);
    }

    private function employees(): array
    {
        return TbPegawai::query()->orderBy('nama')->get(['id_peg', 'nama'])->map(fn (TbPegawai $employee): array => [
            'id_peg' => $employee->id_peg,
            'nama' => $employee->nama,
        ])->all();
    }

    private function nextId(): string
    {
        $last = TbDp3::query()->lockForUpdate()->orderByDesc('id_dp3')->value('id_dp3');
        $next = max(1, ((int) $last) + 1);

        do {
            $id = str_pad((string) $next, 8, '0', STR_PAD_LEFT);
            $next++;
        } while (TbDp3::query()->where('id_dp3', $id)->exists());

        return $id;
    }

    private function employeeQuery(Request $request): Builder
    {
        $query = TbPegawai::query();
        if (strtolower((string) $request->session()->get('hak_akses')) === 'kepala') {
            $query->whereHas('jabatan', function (Builder $builder) use ($request): void {
                $builder->where('status_jab', 'Aktif')->where('unit_kerja', $request->session()->get('kode_kantor'));
            });
        }

        return $query;
    }

    private function canManage(Request $request): bool
    {
        return strtolower((string) $request->session()->get('hak_akses')) === 'admin';
    }

    private function ensureCanManage(Request $request): void
    {
        abort_unless($this->canManage($request), 403);
    }

    private function ensureStaffAccess(Request $request): void
    {
        abort_unless(in_array(strtolower((string) $request->session()->get('hak_akses')), ['admin', 'superadmin', 'kepala'], true), 403);
    }
}
