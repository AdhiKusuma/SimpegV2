<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbLatJabatan;
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

class LatihanJabatanController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureStaffAccess($request);
        $role = strtolower((string) $request->session()->get('hak_akses'));
        $unit = $role === 'kepala' ? (string) $request->session()->get('kode_kantor') : '';
        $employeeId = $request->string('uid')->trim()->value();
        $search = $request->string('search')->trim()->value();

        $query = TbLatJabatan::query()
            ->from('tb_lat_jabatan as latihan')
            ->leftJoin('tb_pegawai as pegawai', 'pegawai.id_peg', '=', 'latihan.id_peg')
            ->when($employeeId !== '', fn (Builder $builder) => $builder->where('latihan.id_peg', $employeeId))
            ->when($unit !== '', fn (Builder $builder) => $builder->whereExists(function (QueryBuilder $scope) use ($unit): void {
                $scope->selectRaw('1')
                    ->from('tb_jabatan as jabatan_scope')
                    ->whereColumn('jabatan_scope.id_peg', 'latihan.id_peg')
                    ->where('jabatan_scope.status_jab', 'Aktif')
                    ->where('jabatan_scope.unit_kerja', $unit);
            }))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $like = "%{$search}%";
                $builder->where(function (QueryBuilder $scope) use ($like): void {
                    $scope->where('pegawai.nama', 'like', $like)
                        ->orWhere('latihan.id_peg', 'like', $like)
                        ->orWhere('latihan.nama_pelatih', 'like', $like)
                        ->orWhere('latihan.tahun_lat', 'like', $like);
                });
            });

        $items = $query
            ->select('latihan.*', 'pegawai.nama as nama_pegawai')
            ->orderByDesc('latihan.tahun_lat')
            ->orderBy('pegawai.nama')
            ->paginate(min(max($request->integer('per_page', 10), 1), 50))
            ->withQueryString()
            ->through(fn (TbLatJabatan $item): array => [
                'id_lat_jabatan' => $item->id_lat_jabatan,
                'id_peg' => $item->id_peg,
                'nama_pegawai' => $item->nama_pegawai,
                'nama_pelatih' => $item->nama_pelatih,
                'tahun_lat' => $item->tahun_lat,
                'jml_jam' => $item->jml_jam,
            ]);

        return Inertia::render('LatihanJabatan/Index', [
            'items' => $items,
            'filters' => ['uid' => $employeeId, 'search' => $search],
            'canManage' => $this->canManage($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('LatihanJabatan/Form', [
            'mode' => 'create',
            'record' => ['id_peg' => $request->string('uid')->trim()->value()],
            'employees' => $this->employees(),
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->ensureCanManage($request);
        $record = TbLatJabatan::query()->findOrFail($id);
        $this->employeeQuery($request)->whereKey($record->id_peg)->firstOrFail();

        return Inertia::render('LatihanJabatan/Form', [
            'mode' => 'edit',
            'record' => $record->only(['id_lat_jabatan', 'id_peg', 'nama_pelatih', 'tahun_lat', 'jml_jam']),
            'employees' => $this->employees(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);
        $data = $request->validate([
            'id_peg' => ['required', 'string', 'max:12', Rule::exists('tb_pegawai', 'id_peg'), Rule::unique('tb_lat_jabatan', 'id_peg')],
            'nama_pelatih' => ['required', 'string', 'max:64'],
            'tahun_lat' => ['required', 'string', 'max:4'],
            'jml_jam' => ['required', 'string', 'max:3'],
        ], [
            'id_peg.unique' => 'Data latihan jabatan untuk pegawai ini sudah tersedia.',
        ]);
        $this->employeeQuery($request)->whereKey($data['id_peg'])->firstOrFail();

        DB::transaction(function () use ($data): void {
            $data['id_lat_jabatan'] = $this->nextId();
            TbLatJabatan::query()->create($data);
        });

        return to_route('latihan-jabatan.index')->with('success', 'Data latihan jabatan berhasil disimpan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $record = TbLatJabatan::query()->findOrFail($id);
        $this->employeeQuery($request)->whereKey($record->id_peg)->firstOrFail();
        $record->update($request->validate([
            'nama_pelatih' => ['required', 'string', 'max:64'],
            'tahun_lat' => ['required', 'string', 'max:4'],
            'jml_jam' => ['required', 'string', 'max:3'],
        ]));

        return to_route('latihan-jabatan.index')->with('success', 'Data latihan jabatan berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($request, $id, $validated): void {
            $record = TbLatJabatan::query()->lockForUpdate()->findOrFail($id);
            $this->employeeQuery($request)->whereKey($record->id_peg)->firstOrFail();
            TbRecycleBin::query()->create([
                'tabel_by' => 'tb_lat_jabatan',
                'data_json' => json_encode($record->getAttributes(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'alasan' => $validated['reason'],
                'created_by' => $request->session()->get('nama_user', 'Admin'),
                'created' => now(),
            ]);
            $record->delete();
        });

        return to_route('latihan-jabatan.index')->with('success', 'Data latihan jabatan dipindahkan ke Recycle Bin.');
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
        $last = TbLatJabatan::query()->lockForUpdate()->orderByDesc('id_lat_jabatan')->value('id_lat_jabatan');
        $next = max(1, ((int) $last) + 1);

        do {
            $id = str_pad((string) $next, 8, '0', STR_PAD_LEFT);
            $next++;
        } while (TbLatJabatan::query()->where('id_lat_jabatan', $id)->exists());

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
