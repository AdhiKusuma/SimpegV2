<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbCuti;
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

class CutiController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureStaffAccess($request);
        $role = strtolower((string) $request->session()->get('hak_akses'));
        $unit = $role === 'kepala' ? (string) $request->session()->get('kode_kantor') : '';
        $search = $request->string('search')->trim()->value();
        $year = $request->string('tahun')->trim()->value();

        $query = TbCuti::query()
            ->from('tb_cuti as cuti')
            ->leftJoin('tb_pegawai as pegawai', 'pegawai.id_peg', '=', 'cuti.id_peg')
            ->when($unit !== '', fn (Builder $builder) => $builder->whereExists(function (QueryBuilder $scope) use ($unit): void {
                $scope->selectRaw('1')->from('tb_jabatan as jabatan_scope')->whereColumn('jabatan_scope.id_peg', 'cuti.id_peg')->where('jabatan_scope.status_jab', 'Aktif')->where('jabatan_scope.unit_kerja', $unit);
            }))
            ->when($year !== '', fn (Builder $builder) => $builder->whereYear('cuti.tgl_mulai', $year))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $like = "%{$search}%";
                $builder->where(function (QueryBuilder $scope) use ($like): void {
                    $scope->where('pegawai.nama', 'like', $like)->orWhere('cuti.id_peg', 'like', $like)->orWhere('cuti.jns_cuti', 'like', $like)->orWhere('cuti.no_suratcuti', 'like', $like)->orWhere('cuti.ket', 'like', $like);
                });
            });

        $items = $query->select('cuti.*', 'pegawai.nama as nama_pegawai')->orderByDesc('cuti.tgl_mulai')->orderByDesc('cuti.id_cuti')->paginate(min(max($request->integer('per_page', 10), 1), 50))->withQueryString()->through(fn (TbCuti $item): array => [
            'id_cuti' => $item->id_cuti,
            'id_peg' => $item->id_peg,
            'nama_pegawai' => $item->nama_pegawai ?: 'Tanpa Nama',
            'jns_cuti' => $item->jns_cuti,
            'no_suratcuti' => $item->no_suratcuti,
            'tgl_suratcuti' => $item->tgl_suratcuti?->format('d-m-Y'),
            'tgl_mulai' => $item->tgl_mulai?->format('d-m-Y'),
            'tgl_selesai' => $item->tgl_selesai?->format('d-m-Y'),
            'ket' => $item->ket,
        ]);

        return Inertia::render('Cuti/Index', [
            'items' => $items,
            'years' => TbCuti::query()->whereNotNull('tgl_mulai')->selectRaw('YEAR(tgl_mulai) as tahun')->distinct()->orderByDesc('tahun')->pluck('tahun')->values(),
            'filters' => ['search' => $search, 'tahun' => $year],
            'canManage' => $this->canManage($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('Cuti/Form', ['mode' => 'create', 'record' => ['id_peg' => $request->string('uid')->trim()->value()], 'employees' => $this->employees()]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->ensureCanManage($request);
        $record = TbCuti::query()->findOrFail($id);

        return Inertia::render('Cuti/Form', ['mode' => 'edit', 'record' => collect($record->getAttributes())->only(['id_cuti', 'id_peg', 'jns_cuti', 'no_suratcuti', 'tgl_suratcuti', 'tgl_mulai', 'tgl_selesai', 'ket'])->map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value)->all(), 'employees' => $this->employees()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);
        $data = $this->validatedData($request, false);
        $data['id_cuti'] = $this->nextId();
        TbCuti::query()->create($data);

        return to_route('cuti.index')->with('success', 'Data cuti berhasil disimpan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $record = TbCuti::query()->findOrFail($id);
        $data = $this->validatedData($request, true);
        unset($data['id_peg']);
        $record->update($data);

        return to_route('cuti.index')->with('success', 'Data cuti berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $id, $validated): void {
            $record = TbCuti::query()->lockForUpdate()->findOrFail($id);
            TbRecycleBin::query()->create(['tabel_by' => 'tb_cuti', 'data_json' => json_encode($record->getAttributes(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'alasan' => $validated['reason'], 'created_by' => $request->session()->get('nama_user', 'Admin'), 'created' => now()]);
            $record->delete();
        });

        return to_route('cuti.index')->with('success', 'Data cuti dipindahkan ke Recycle Bin.');
    }

    private function validatedData(Request $request, bool $isEdit): array
    {
        $rules = ['jns_cuti' => ['required', 'string', 'max:32'], 'no_suratcuti' => ['required', 'string', 'max:32'], 'tgl_suratcuti' => ['required', 'date'], 'tgl_mulai' => ['required', 'date'], 'tgl_selesai' => ['required', 'date'], 'ket' => ['nullable', 'string', 'max:64']];
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
        $last = TbCuti::query()->lockForUpdate()->orderByDesc('id_cuti')->value('id_cuti');
        $next = max(1, ((int) $last) + 1);
        do {
            $id = str_pad((string) $next, 8, '0', STR_PAD_LEFT);
            $next++;
        } while (TbCuti::query()->where('id_cuti', $id)->exists());

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
