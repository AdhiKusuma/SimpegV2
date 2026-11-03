<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbPegawai;
use App\Models\Legacy\TbRecycleBin;
use App\Models\Legacy\TbSeminar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SeminarController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureStaffAccess($request);
        $role = strtolower((string) $request->session()->get('hak_akses'));
        $unit = $role === 'kepala' ? (string) $request->session()->get('kode_kantor') : '';
        $search = $request->string('search')->trim()->value();
        $year = $request->string('tahun')->trim()->value();

        $query = TbSeminar::query()
            ->from('tb_seminar as seminar')
            ->leftJoin('tb_pegawai as pegawai', 'pegawai.id_peg', '=', 'seminar.id_peg')
            ->when($unit !== '', fn (Builder $builder) => $builder->whereExists(function (QueryBuilder $scope) use ($unit): void {
                $scope->selectRaw('1')->from('tb_jabatan as jabatan_scope')->whereColumn('jabatan_scope.id_peg', 'seminar.id_peg')->where('jabatan_scope.status_jab', 'Aktif')->where('jabatan_scope.unit_kerja', $unit);
            }))
            ->when($year !== '', fn (Builder $builder) => $builder->whereYear('seminar.tgl_mulai', $year))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $like = "%{$search}%";
                $builder->where(function (QueryBuilder $scope) use ($like): void {
                    $scope->where('pegawai.nama', 'like', $like)->orWhere('seminar.id_peg', 'like', $like)->orWhere('seminar.seminar', 'like', $like)->orWhere('seminar.tempat', 'like', $like)->orWhere('seminar.penyelenggara', 'like', $like)->orWhere('seminar.no_piagam', 'like', $like);
                });
            });

        $items = $query->select('seminar.*', 'pegawai.nama as nama_pegawai')->orderByDesc('seminar.tgl_mulai')->orderByDesc('seminar.id_seminar')->paginate(min(max($request->integer('per_page', 10), 1), 50))->withQueryString()->through(fn (TbSeminar $item): array => [
            'id_seminar' => $item->id_seminar,
            'id_peg' => $item->id_peg,
            'nama_pegawai' => $item->nama_pegawai ?: 'Tanpa Nama',
            'seminar' => $item->seminar,
            'tempat' => $item->tempat,
            'penyelenggara' => $item->penyelenggara,
            'tgl_mulai' => $item->tgl_mulai?->format('d-m-Y'),
            'tgl_selesai' => $item->tgl_selesai?->format('d-m-Y'),
            'no_piagam' => $item->no_piagam,
            'tgl_piagam' => $item->tgl_piagam?->format('d-m-Y'),
        ]);

        return Inertia::render('Seminar/Index', [
            'items' => $items,
            'years' => TbSeminar::query()->whereNotNull('tgl_mulai')->selectRaw('YEAR(tgl_mulai) as tahun')->distinct()->orderByDesc('tahun')->pluck('tahun')->values(),
            'filters' => ['search' => $search, 'tahun' => $year],
            'canManage' => $this->canManage($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('Seminar/Form', ['mode' => 'create', 'record' => ['id_peg' => $request->string('uid')->trim()->value()], 'employees' => $this->employees()]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->ensureCanManage($request);
        $record = TbSeminar::query()->findOrFail($id);

        return Inertia::render('Seminar/Form', ['mode' => 'edit', 'record' => collect($record->getAttributes())->only(['id_seminar', 'id_peg', 'seminar', 'tempat', 'penyelenggara', 'tgl_mulai', 'tgl_selesai', 'no_piagam', 'tgl_piagam'])->map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value)->all(), 'employees' => $this->employees()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);
        $data = $this->validatedData($request, false);
        $data['id_seminar'] = $this->nextId();
        TbSeminar::query()->create($data);

        return to_route('seminar.index')->with('success', 'Data seminar berhasil disimpan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $record = TbSeminar::query()->findOrFail($id);
        $data = $this->validatedData($request, true);
        unset($data['id_peg']);
        $record->update($data);

        return to_route('seminar.index')->with('success', 'Data seminar berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $id, $validated): void {
            $record = TbSeminar::query()->lockForUpdate()->findOrFail($id);
            TbRecycleBin::query()->create(['tabel_by' => 'tb_seminar', 'data_json' => json_encode($record->getAttributes(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'alasan' => $validated['reason'], 'created_by' => $request->session()->get('nama_user', 'Admin'), 'created' => now()]);
            $record->delete();
        });

        return to_route('seminar.index')->with('success', 'Data seminar dipindahkan ke Recycle Bin.');
    }

    private function validatedData(Request $request, bool $isEdit): array
    {
        $rules = ['seminar' => ['required', 'string', 'max:128'], 'tempat' => ['required', 'string', 'max:32'], 'penyelenggara' => ['required', 'string', 'max:64'], 'tgl_mulai' => ['required', 'date'], 'tgl_selesai' => ['required', 'date'], 'no_piagam' => ['required', 'string', 'max:32'], 'tgl_piagam' => ['nullable', 'date']];
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
        $last = TbSeminar::query()->lockForUpdate()->orderByDesc('id_seminar')->value('id_seminar');
        $next = max(1, ((int) $last) + 1);
        do {
            $id = str_pad((string) $next, 8, '0', STR_PAD_LEFT);
            $next++;
        } while (TbSeminar::query()->where('id_seminar', $id)->exists());

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
