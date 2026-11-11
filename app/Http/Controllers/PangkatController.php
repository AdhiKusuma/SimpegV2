<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbMastergol;
use App\Models\Legacy\TbPangkat;
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

class PangkatController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureStaffAccess($request);

        $role = strtolower((string) $request->session()->get('hak_akses'));
        $unit = $role === 'kepala' ? (string) $request->session()->get('kode_kantor') : '';
        $search = $request->string('search')->trim()->value();
        $golongan = $request->string('gol')->trim()->value();
        $status = $request->string('status')->trim()->value();

        $query = TbPangkat::query()
            ->from('tb_pangkat as pangkat')
            ->leftJoin('tb_pegawai as pegawai', 'pegawai.id_peg', '=', 'pangkat.id_peg')
            ->when($unit !== '', fn (Builder $builder) => $builder->whereExists(function (QueryBuilder $scope) use ($unit): void {
                $scope->selectRaw('1')
                    ->from('tb_jabatan as jabatan_scope')
                    ->whereColumn('jabatan_scope.id_peg', 'pangkat.id_peg')
                    ->where('jabatan_scope.status_jab', 'Aktif')
                    ->where('jabatan_scope.unit_kerja', $unit);
            }))
            ->when($golongan !== '', fn (Builder $builder) => $builder->where('pangkat.gol', $golongan))
            ->when($status === 'Aktif', fn (Builder $builder) => $builder->where('pangkat.status_pan', 'Aktif'))
            ->when($status === 'nonaktif', fn (Builder $builder) => $builder->where(function (QueryBuilder $scope): void {
                $scope->whereNull('pangkat.status_pan')->orWhere('pangkat.status_pan', '');
            }))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $like = "%{$search}%";
                $builder->where(function (QueryBuilder $scope) use ($like): void {
                    $scope->where('pegawai.nama', 'like', $like)
                        ->orWhere('pangkat.id_peg', 'like', $like);
                });
            });

        $items = $query
            ->select('pangkat.*', 'pegawai.nama as nama_pegawai')
            ->orderByDesc('pangkat.status_pan')
            ->orderByDesc('pangkat.tgl_sk')
            ->orderByDesc('pangkat.id_pangkat')
            ->paginate(min(max($request->integer('per_page', 10), 1), 50))
            ->withQueryString()
            ->through(fn (TbPangkat $item): array => $this->present($item));

        return Inertia::render('Pangkat/Index', [
            'items' => $items,
            'golongan' => TbMastergol::query()->orderByDesc('nama_mastergol')->pluck('nama_mastergol')->values(),
            'filters' => ['search' => $search, 'gol' => $golongan, 'status' => $status],
            'canManage' => $this->canManage($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('Pangkat/Form', [
            'mode' => 'create',
            'record' => ['id_peg' => $request->string('uid')->trim()->value()],
            'employees' => $this->employees(),
            'golongan' => $this->golongan(),
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->ensureCanManage($request);
        $record = TbPangkat::query()->findOrFail($id);

        return Inertia::render('Pangkat/Form', [
            'mode' => 'edit',
            'record' => $this->formRecord($record),
            'employees' => $this->employees(),
            'golongan' => $this->golongan(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);
        $data = $this->validatedData($request, false);

        DB::transaction(function () use ($request, $data): void {
            $data['id_pangkat'] = $this->nextId();
            $data['created_by'] = $request->session()->get('nama_user', $request->session()->get('id_user'));
            TbPangkat::query()->create($data);
        });

        return to_route('pangkat.index')->with('success', 'Data pangkat berhasil disimpan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $record = TbPangkat::query()->findOrFail($id);
        $data = $this->validatedData($request, true);
        unset($data['id_peg']);
        $data['updated_at'] = now();
        $data['updated_by'] = $request->session()->get('nama_user', $request->session()->get('id_user'));
        $record->update($data);

        return to_route('pangkat.index')->with('success', 'Data pangkat berhasil diperbarui.');
    }

    public function setup(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $record = TbPangkat::query()->findOrFail($id);
        $employee = TbPegawai::query()->where('id_peg', $record->id_peg)->firstOrFail(['id_peg', 'jk']);

        DB::transaction(function () use ($request, $record, $employee): void {
            TbPangkat::query()
                ->where('id_peg', $record->id_peg)
                ->update(['status_pan' => '']);

            $record->update([
                'status_pan' => 'Aktif',
                'jk_pan' => $employee->jk,
                'updated_at' => now(),
                'updated_by' => $request->session()->get('nama_user', $request->session()->get('id_user')),
            ]);
        });

        return to_route('pangkat.index')->with('success', 'Pangkat aktif pegawai berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($request, $id, $validated): void {
            $record = TbPangkat::query()->lockForUpdate()->findOrFail($id);

            TbRecycleBin::query()->create([
                'tabel_by' => 'tb_pangkat',
                'data_json' => json_encode($record->getAttributes(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'alasan' => $validated['reason'],
                'created_by' => $request->session()->get('nama_user', 'Admin'),
                'created' => now(),
            ]);

            $record->delete();
        });

        return to_route('pangkat.index')->with('success', 'Data pangkat dipindahkan ke Recycle Bin.');
    }

    private function validatedData(Request $request, bool $isEdit): array
    {
        $rules = [
            'pangkat' => ['required', 'string', 'max:64'],
            'gol' => ['required', 'string', 'max:6', Rule::exists('tb_mastergol', 'nama_mastergol')],
            'jns_pangkat' => ['required', 'string', 'max:32'],
            'tmt_pangkat' => ['nullable', 'date'],
            'pejabat_sk' => ['required', 'string', 'max:32'],
            'no_sk' => ['required', 'string', 'max:32'],
            'tgl_sk' => ['required', 'date'],
        ];

        if (! $isEdit) {
            $rules['id_peg'] = ['required', 'string', 'max:12', Rule::exists('tb_pegawai', 'id_peg')];
        }

        return $request->validate($rules);
    }

    private function present(TbPangkat $item): array
    {
        return [
            'id_pangkat' => $item->id_pangkat,
            'id_peg' => $item->id_peg,
            'nama_pegawai' => $item->nama_pegawai ?: 'Tanpa Nama',
            'pangkat' => $item->pangkat,
            'gol' => $item->gol,
            'jns_pangkat' => $item->jns_pangkat,
            'pejabat_sk' => $item->pejabat_sk,
            'no_sk' => $item->no_sk,
            'tgl_sk' => $item->tgl_sk?->format('d-m-Y'),
            'tmt_pangkat' => $item->tmt_pangkat?->format('d-m-Y'),
            'status_pan' => $item->status_pan,
        ];
    }

    private function formRecord(TbPangkat $record): array
    {
        return collect($record->getAttributes())->only([
            'id_pangkat', 'id_peg', 'pangkat', 'gol', 'jns_pangkat', 'pejabat_sk', 'no_sk', 'tgl_sk', 'tmt_pangkat',
        ])->map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value)->all();
    }

    private function employees(): array
    {
        return TbPegawai::query()
            ->orderBy('nama')
            ->get(['id_peg', 'nama'])
            ->map(fn (TbPegawai $employee): array => ['id_peg' => $employee->id_peg, 'nama' => $employee->nama])
            ->all();
    }

    private function golongan(): array
    {
        return TbMastergol::query()->orderByDesc('nama_mastergol')->pluck('nama_mastergol')->values()->all();
    }

    private function nextId(): string
    {
        $last = TbPangkat::query()->lockForUpdate()->orderByDesc('id_pangkat')->value('id_pangkat');
        $next = max(1, ((int) $last) + 1);

        do {
            $id = str_pad((string) $next, 8, '0', STR_PAD_LEFT);
            $next++;
        } while (TbPangkat::query()->where('id_pangkat', $id)->exists());

        return $id;
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
