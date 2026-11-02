<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbBahasa;
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

class BahasaController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureStaffAccess($request);
        $role = strtolower((string) $request->session()->get('hak_akses'));
        $unit = $role === 'kepala' ? (string) $request->session()->get('kode_kantor') : '';
        $search = $request->string('search')->trim()->value();

        $query = TbBahasa::query()
            ->from('tb_bahasa as bahasa')
            ->leftJoin('tb_pegawai as pegawai', 'pegawai.id_peg', '=', 'bahasa.id_peg')
            ->when($unit !== '', fn (Builder $builder) => $builder->whereExists(function (QueryBuilder $scope) use ($unit): void {
                $scope->selectRaw('1')->from('tb_jabatan as jabatan_scope')->whereColumn('jabatan_scope.id_peg', 'bahasa.id_peg')->where('jabatan_scope.status_jab', 'Aktif')->where('jabatan_scope.unit_kerja', $unit);
            }))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $like = "%{$search}%";
                $builder->where(function (QueryBuilder $scope) use ($like): void {
                    $scope->where('pegawai.nama', 'like', $like)->orWhere('bahasa.id_peg', 'like', $like)->orWhere('bahasa.jns_bhs', 'like', $like)->orWhere('bahasa.bahasa', 'like', $like)->orWhere('bahasa.kemampuan', 'like', $like);
                });
            });

        $items = $query->select('bahasa.*', 'pegawai.nama as nama_pegawai')->orderBy('pegawai.nama')->orderBy('bahasa.bahasa')->paginate(min(max($request->integer('per_page', 10), 1), 50))->withQueryString()->through(fn (TbBahasa $item): array => [
            'id_bhs' => $item->id_bhs,
            'id_peg' => $item->id_peg,
            'nama_pegawai' => $item->nama_pegawai ?: 'Tanpa Nama',
            'jns_bhs' => $item->jns_bhs,
            'bahasa' => $item->bahasa,
            'kemampuan' => $item->kemampuan,
        ]);

        return Inertia::render('Bahasa/Index', ['items' => $items, 'filters' => ['search' => $search], 'canManage' => $this->canManage($request)]);
    }

    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('Bahasa/Form', ['mode' => 'create', 'record' => ['id_peg' => $request->string('uid')->trim()->value(), 'kemampuan' => 'Aktif'], 'employees' => $this->employees(), 'abilities' => ['Aktif', 'Pasif']]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->ensureCanManage($request);
        $record = TbBahasa::query()->findOrFail($id);

        return Inertia::render('Bahasa/Form', ['mode' => 'edit', 'record' => $record->only(['id_bhs', 'id_peg', 'jns_bhs', 'bahasa', 'kemampuan']), 'employees' => $this->employees(), 'abilities' => ['Aktif', 'Pasif']]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);
        $data = $this->validatedData($request, false);
        $data['id_bhs'] = $this->nextId();
        TbBahasa::query()->create($data);

        return to_route('bahasa.index')->with('success', 'Data bahasa berhasil disimpan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $record = TbBahasa::query()->findOrFail($id);
        $data = $this->validatedData($request, true);
        unset($data['id_peg']);
        $record->update($data);

        return to_route('bahasa.index')->with('success', 'Data bahasa berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $id, $validated): void {
            $record = TbBahasa::query()->lockForUpdate()->findOrFail($id);
            TbRecycleBin::query()->create(['tabel_by' => 'tb_bahasa', 'data_json' => json_encode($record->getAttributes(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'alasan' => $validated['reason'], 'created_by' => $request->session()->get('nama_user', 'Admin'), 'created' => now()]);
            $record->delete();
        });

        return to_route('bahasa.index')->with('success', 'Data bahasa dipindahkan ke Recycle Bin.');
    }

    private function validatedData(Request $request, bool $isEdit): array
    {
        $rules = ['jns_bhs' => ['required', 'string', 'max:32'], 'bahasa' => ['required', 'string', 'max:32'], 'kemampuan' => ['required', Rule::in(['Aktif', 'Pasif'])]];
        if (! $isEdit) {
            $rules['id_peg'] = ['required', 'string', 'max:12', Rule::exists('tb_pegawai', 'id_peg')];
        }

        return $request->validate($rules);
    }

    private function employees(): array
    {
        return TbPegawai::query()->orderBy('nama')->get(['id_peg', 'nama'])->map(fn (TbPegawai $employee): array => ['id_peg' => $employee->id_peg, 'nama' => $employee->nama])->all();
    }

    private function nextId(): string
    {
        $last = TbBahasa::query()->lockForUpdate()->orderByDesc('id_bhs')->value('id_bhs');
        $next = max(1, ((int) $last) + 1);
        do {
            $id = str_pad((string) $next, 8, '0', STR_PAD_LEFT);
            $next++;
        } while (TbBahasa::query()->where('id_bhs', $id)->exists());

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
